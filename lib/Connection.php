<?php

/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

require_once 'Column.php';

use ActiveRecord\Adapter\SqliteAdapter;
use ActiveRecord\Exception\ConnectionException;
use ActiveRecord\Exception\DatabaseException;
use Closure;
use PDO;

/**
 * The base class for database connection adapters.
 *
 * @package ActiveRecord
 */
abstract class Connection
{
    /**
     * The DateTime format to use when translating other DateTime-compatible objects.
     *
     * NOTE!: The DateTime "format" used must not include a time-zone (name, abbreviation, etc) or offset.
     * Including one will cause PHP to ignore the passed in time-zone in the 3rd argument.
     * See bug: https://bugs.php.net/bug.php?id=61022
     *
     * @var string
     */
    public const DATETIME_TRANSLATE_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * The PDO connection object.
     */
    public \PDO $connection;
    /**
     * The last query run.
     */
    public string $last_query;
    /**
     * Switch for logging.
     *
     * @var bool
     */
    private $logging = false;
    /**
     * Contains a Logger object that must implement a log() method.
     *
     * @var object
     */
    private $logger;
    /**
     * The name of the protocol that is used.
     *
     * @var string
     */
    public $protocol;
    /**
     * Database's date format
     *
     * @var string
     */
    public static $date_format = 'Y-m-d';
    /**
     * Database's datetime format
     *
     * @var string
     */
    public static $datetime_format = 'Y-m-d H:i:s';
    /**
     * Default PDO options to set for each connection.
     *
     * @var array<mixed>
     */
    public static array $PDO_OPTIONS = [
        \PDO::ATTR_CASE => \PDO::CASE_LOWER,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
        \PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    /**
     * The quote character for stuff like column and field names.
     */
    public static string $QUOTE_CHARACTER = '`';

    /**
     * Default port.
     */
    public static int $DEFAULT_PORT = 0;

    /**
     * @param array<string, string|null> $column
     */
    abstract public function create_column(array $column): Column;

    /**
     * Retrieve a database connection.
     *
     * @param string|null $connection_string_or_connection_name A database connection string (ex. mysql://user:pass@host[:port]/dbname)
     *                                                          Everything after the protocol:// part is specific to the connection adapter.
     *                                                          OR
     *                                                          A connection name that is set in ActiveRecord\Config
     *                                                          If null it will use the default connection specified by ActiveRecord\Config->set_default_connection
     *
     * @return Connection
     *
     * @see parse_connection_url
     */
    public static function instance(string $connection_string_or_connection_name = null)
    {
        $config = Config::instance();

        if (!str_contains($connection_string_or_connection_name ?? '', '://')) {
            $connection_string = $connection_string_or_connection_name ?
                $config->get_connection($connection_string_or_connection_name) :
                $config->get_default_connection_string();
        } else {
            $connection_string = $connection_string_or_connection_name;
        }

        if (!$connection_string) {
            throw new DatabaseException('Empty connection string');
        }
        $info = static::parse_connection_url($connection_string);
        $fqclass = static::load_adapter_class($info->protocol);

        try {
            $connection = new $fqclass($info);
            $connection->protocol = $info->protocol;
            $connection->logging = $config->get_logging();
            $connection->logger = $connection->logging ? $config->get_logger() : null;

            if (isset($info->charset)) {
                $connection->set_encoding($info->charset);
            }
        } catch (\PDOException $e) {
            throw new DatabaseException($e);
        }

        return $connection;
    }

    /**
     * Loads the specified class for an adapter.
     *
     * @param string $adapter name of the adapter
     *
     * @return class-string the full name of the class including namespace
     */
    protected static function load_adapter_class(string $adapter)
    {
        $class = ucwords($adapter) . 'Adapter';
        $fqclass = 'ActiveRecord\\Adapter\\' . $class;
        $source = __DIR__ . "/Adapter/$class.php";

        if (!file_exists($source)) {
            throw new DatabaseException("$fqclass not found!");
        }
        require_once $source;

        return $fqclass;
    }

    /**
     * Use this for any adapters that can take connection info in the form below
     * to set the adapters connection info.
     *
     * ```
     * protocol://username:password@host[:port]/dbname
     * protocol://urlencoded%20username:urlencoded%20password@host[:port]/dbname?decode=true
     * protocol://username:password@unix(/some/file/path)/dbname
     * ```
     *
     * Sqlite has a special syntax, as it does not need a database name or user authentication:
     *
     * ```
     * sqlite://file.db
     * sqlite://../relative/path/to/file.db
     * sqlite://unix(/absolute/path/to/file.db)
     * sqlite://windows(c%2A/absolute/path/to/file.db)
     * ```
     *
     * @param string $connection_url A connection URL
     *
     * @return object the parsed URL as an object
     */
    public static function parse_connection_url(string $connection_url)
    {
        $url = @parse_url($connection_url);

        if (!isset($url['host'])) {
            throw new DatabaseException('Database host must be specified in the connection string. If you want to specify an absolute filename, use e.g. sqlite://unix(/path/to/file)');
        }
        $info = new \stdClass();
        $info->protocol = $url['scheme'];
        $info->host = $url['host'];
        $info->db = isset($url['path']) ? substr($url['path'], 1) : null;
        $info->user = isset($url['user']) ? $url['user'] : null;
        $info->pass = isset($url['pass']) ? $url['pass'] : null;

        $allow_blank_db = ('sqlite' == $info->protocol);

        if ('unix(' == $info->host) {
            $socket_database = $info->host . '/' . $info->db;

            if ($allow_blank_db) {
                $unix_regex = '/^unix\((.+)\)\/?().*$/';
            } else {
                $unix_regex = '/^unix\((.+)\)\/(.+)$/';
            }

            if (preg_match_all($unix_regex, $socket_database, $matches) > 0) {
                $info->host = $matches[1][0];
                $info->db = $matches[2][0];
            }
        } elseif ('windows(' == substr($info->host, 0, 8)) {
            $info->host = urldecode(substr($info->host, 8) . '/' . substr($info->db, 0, -1));
            $info->db = null;
        }

        if ($allow_blank_db && $info->db) {
            $info->host .= '/' . $info->db;
        }

        if (isset($url['port'])) {
            $info->port = $url['port'];
        }

        if (false !== strpos($connection_url, 'decode=true')) {
            if ($info->user) {
                $info->user = urldecode($info->user);
            }

            if ($info->pass) {
                $info->pass = urldecode($info->pass);
            }
        }

        if (isset($url['query'])) {
            foreach (explode('/&/', $url['query']) as $pair) {
                list($name, $value) = explode('=', $pair);

                if ('charset' == $name) {
                    $info->charset = $value;
                }
            }
        }

        return $info;
    }

    /**
     * Class Connection is a singleton. Access it via instance().
     */
    protected function __construct(\stdClass $info)
    {
        try {
            // unix sockets start with a /
            if ('/' != $info->host[0]) {
                $host = "host=$info->host";

                if (isset($info->port)) {
                    $host .= ";port=$info->port";
                }
            } else {
                $host = "unix_socket=$info->host";
            }

            $dsn = "$info->protocol:$host;dbname=$info->db";
            $this->connection = new \PDO($dsn, $info->user, $info->pass, static::$PDO_OPTIONS);
        } catch (\PDOException $e) {
            throw new Exception\ConnectionException($e);
        }
    }

    /**
     * Retrieves column metadata for the specified table.
     *
     * @param string $table Name of a table
     *
     * @return array<Column> an array of {@link Column} objects
     */
    public function columns(string $table): array
    {
        $columns = [];
        $sth = $this->query_column_info($table);

        while ($row = $sth->fetch()) {
            $c = $this->create_column($row);
            $columns[$c->name] = $c;
        }

        return $columns;
    }

    /**
     * Escapes quotes in a string.
     *
     * @param string $string the string to be quoted
     *
     * @return string the string with any quotes in it properly escaped
     */
    public function escape($string)
    {
        return $this->connection->quote($string);
    }

    /**
     * Retrieve the insert id of the last model saved.
     *
     * @param string $sequence Optional name of a sequence to use
     */
    public function insert_id($sequence = null): int
    {
        return (int) $this->connection->lastInsertId($sequence);
    }

    /**
     * Execute a raw SQL query on the database.
     *
     * @param string       $sql     raw SQL string to execute
     * @param array<mixed> &$values Optional array of bind values
     *
     * @return mixed A result set object
     */
    public function query(string $sql, array &$values = [])
    {
        if ($this->logging) {
            $this->logger->info($sql);
            if ($values) {
                $this->logger->info(var_export($values, true));
            }
        }

        $this->last_query = $sql;

        try {
            if (!($sth = $this->connection->prepare($sql))) {
                throw new DatabaseException();
            }
        } catch (\PDOException $e) {
            if ($this instanceof SqliteAdapter && 'HY000' === $e->getCode()) {
                throw new DatabaseException($e);
            }
            throw new ConnectionException($e);
        }

        $sth->setFetchMode(\PDO::FETCH_ASSOC);

        $msg = "couldn't execute query on " . get_class($this) . '. ';
        $msg .= 'user: ' . getenv('USER');
        try {
            if (!$sth->execute($values)) {
                throw new DatabaseException($msg);
            }
        } catch (\PDOException $e) {
            throw new DatabaseException($msg . ': ' . $e->getMessage());
        }

        return $sth;
    }

    /**
     * Execute a query that returns maximum of one row with one field and return it.
     *
     * @param string       $sql     raw SQL string to execute
     * @param array<mixed> &$values Optional array of values to bind to the query
     */
    public function query_and_fetch_one(string $sql, array &$values = []): int
    {
        $sth = $this->query($sql, $values);
        $row = $sth->fetch(\PDO::FETCH_NUM);

        return $row[0];
    }

    /**
     * Execute a raw SQL query and fetch the results.
     *
     * @param string   $sql     raw SQL string to execute
     * @param \Closure $handler closure that will be passed the fetched results
     */
    public function query_and_fetch(string $sql, \Closure $handler): void
    {
        $sth = $this->query($sql);

        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $handler($row);
        }
    }

    /**
     * Returns all tables for the current database.
     *
     * @return array<string> array containing table names
     */
    public function tables(): array
    {
        $tables = [];
        $sth = $this->query_for_tables();

        while ($row = $sth->fetch(\PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * Starts a transaction.
     */
    public function transaction(): void
    {
        if (!$this->connection->beginTransaction()) {
            throw new DatabaseException();
        }
    }

    /**
     * Commits the current transaction.
     *
     * @throws DatabaseException
     */
    public function commit(): void
    {
        if (!$this->connection->commit()) {
            throw new DatabaseException();
        }
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void
    {
        if (!$this->connection->rollback()) {
            throw new DatabaseException();
        }
    }

    /**
     * Tells you if this adapter supports sequences or not.
     */
    public function supports_sequences(): bool
    {
        return false;
    }

    /**
     * Return a default sequence name for the specified table.
     *
     * @param string $table       Name of a table
     * @param string $column_name Name of column sequence is for
     *
     * @return string sequence name or null if not supported
     */
    public function get_sequence_name(string $table, string $column_name): string
    {
        return "{$table}_seq";
    }

    /**
     * Return SQL for getting the next value in a sequence.
     */
    public function next_sequence_value(string $sequence_name): ?string
    {
        return null;
    }

    /**
     * Quote a name like table names and field names.
     *
     * @param string $string string to quote
     *
     * @return string
     */
    public function quote_name($string)
    {
        return $string[0] === static::$QUOTE_CHARACTER || $string[strlen($string) - 1] === static::$QUOTE_CHARACTER ?
            $string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
    }

    /**
     * Return a date time formatted into the database's date format.
     *
     * @param DateTime $datetime The DateTime object
     *
     * @return string
     */
    public function date_to_string($datetime)
    {
        return $datetime->format(static::$date_format);
    }

    /**
     * Return a date time formatted into the database's datetime format.
     *
     * @param DateTime $datetime The DateTime object
     *
     * @return string
     */
    public function datetime_to_string(\DateTime $datetime)
    {
        return $datetime->format(static::$datetime_format);
    }

    /**
     * Converts a string representation of a datetime into a DateTime object.
     *
     * @param string $string A datetime in the form accepted by date_create()
     *
     * @return ?DateTime The date_class set in Config
     */
    public function string_to_datetime(string $string): ?DateTime
    {
        $date = date_create($string);
        $errors = \DateTime::getLastErrors();

        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        $date_class = Config::instance()->get_date_class();

        return $date_class::createFromFormat(
            static::DATETIME_TRANSLATE_FORMAT,
            $date->format(static::DATETIME_TRANSLATE_FORMAT),
            $date->getTimezone()
        );
    }

    /**
     * Adds a limit clause to the SQL query.
     *
     * @param string $sql    the SQL statement
     * @param int    $offset row offset to start at
     * @param int    $limit  maximum number of rows to return
     *
     * @return string The SQL query that will limit results to specified parameters
     */
    abstract public function limit(string $sql, int $offset = 0, int $limit = 0);

    /**
     * Query for column meta info and return statement handle.
     */
    abstract public function query_column_info(string $table): \PDOStatement;

    /**
     * Query for all tables in the current database. The result must only
     * contain one column which has the name of the table.
     */
    abstract public function query_for_tables(): \PDOStatement;

    /**
     * Executes query to specify the character set for this connection.
     */
    abstract public function set_encoding(string $charset): void;

    /*
     * Returns an array mapping of native database types
     */

    /**
     * @return array<string, string|array<string, mixed>>
     */
    abstract public function native_database_types(): array;

    /**
     * Specifies whether adapter can use LIMIT/ORDER clauses with DELETE & UPDATE operations
     *
     * @internal
     *
     * @returns boolean (FALSE by default)
     */
    public function accepts_limit_and_order_for_update_and_delete(): bool
    {
        return false;
    }
}
