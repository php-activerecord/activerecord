<?php

/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\DatabaseException;
use Psr\Log\LoggerInterface;

/**
 * The base class for database connection adapters.
 *
 * @phpstan-type ConnectionInfo array{
 *     protocol: string,
 *     host: string,
 *     db: string|null,
 *     user: string|null,
 *     pass: string|null,
 *     port?: int|null,
 *     charset?: string|null
 * }
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
     */
    private bool $logging = false;
    /**
     * Contains a Logger object that must implement a log() method.
     */
    private LoggerInterface|null $logger;
    /**
     * The name of the protocol that is used.
     */
    public string $protocol;
    /**
     * Database's date format
     *
     * @var string
     */
    public static $date_format = 'Y-m-d';
    /**
     * Database's datetime format
     */
    public static string $datetime_format = 'Y-m-d H:i:s';
    /**
     * Default PDO options to set for each connection.
     *
     * @var list<int|bool>
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
            $connection_string = $config->get_connection($connection_string_or_connection_name ?? '') ??
                $config->get_default_connection_string();
        } else {
            $connection_string = $connection_string_or_connection_name;
        }

        assert(!empty($connection_string), 'Empty connection string');
        $info = static::parse_connection_url($connection_string);
        $fqclass = static::load_adapter_class($info['protocol']);

        $connection = new $fqclass($info);
        assert($connection instanceof Connection);
        $connection->protocol = $info['protocol'];
        $connection->logging = $config->get_logging();
        $connection->logger = $config->get_logger();

        if (isset($info['charset'])) {
            $connection->set_encoding($info['charset']);
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

        assert(class_exists($fqclass));

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
     * @return ConnectionInfo
     */
    public static function parse_connection_url(string $connection_url): array
    {
        $url = @parse_url($connection_url);

        if (!isset($url['host'])) {
            throw new DatabaseException('Database host must be specified in the connection string. If you want to specify an absolute filename, use e.g. sqlite://unix(/path/to/file)');
        }
        $host = $url['host'];
        $db = isset($url['path']) ? substr($url['path'], 1) : null;
        $user = $url['user'] ?? null;
        $pass = $url['pass'] ?? null;
        $protocol = $url['scheme'] ?? null;
        $charset = null;

        $allow_blank_db = ('sqlite' == $protocol);

        if ('unix(' == $host) {
            $socket_database = $host . '/' . $db;

            if ($allow_blank_db) {
                $unix_regex = '/^unix\((.+)\)\/?().*$/';
            } else {
                $unix_regex = '/^unix\((.+)\)\/(.+)$/';
            }

            if (preg_match_all($unix_regex, $socket_database, $matches) > 0) {
                $host = $matches[1][0];
                $db = $matches[2][0];
            }
        } elseif ('windows(' == substr($host, 0, 8)) {
            $host = urldecode(substr($host, 8) . '/' . substr($db ?? '', 0, -1));
            $db = null;
        }

        if ($allow_blank_db && $db) {
            $host .= '/' . $db;
        }

        if (false !== strpos($connection_url, 'decode=true')) {
            if ($user) {
                $user = urldecode($user);
            }

            if ($pass) {
                $pass = urldecode($pass);
            }
        }

        if (isset($url['query'])) {
            foreach (explode('/&/', $url['query']) as $pair) {
                list($name, $value) = explode('=', $pair);

                if ('charset' == $name) {
                    $charset = $value;
                }
            }
        }

        assert(!is_null($protocol));

        return [
            'charset' => $charset,
            'protocol' => $protocol,
            'host' => $host,
            'db' => $db,
            'user' => $user,
            'pass' => $pass,
            'port' => $url['port'] ?? null
        ];
    }

    /**
     * Class Connection is a singleton. Access it via instance().
     *
     * @param ConnectionInfo $info
     */
    protected function __construct(array $info)
    {
        try {
            $dsn = static::data_source_name($info);
            $this->connection = new \PDO($dsn, $info['user'], $info['pass'], static::$PDO_OPTIONS);
        } catch (\PDOException $e) {
            throw new Exception\ConnectionException($e);
        }
    }

    /**
     * @param ConnectionInfo $info
     */
    public static function data_source_name(array $info): string
    {
        // unix sockets start with a /
        if ('/' != $info['host'][0]) {
            $host = 'host=' . $info['host'];

            if (isset($info['port'])) {
                $host .= ';port=' . $info['port'];
            }
        } else {
            $host = 'unix_socket=' . $info['host'];
        }

        return $info['protocol'] . ":$host;dbname=" . $info['db'];
    }

    /**
     * Retrieves column metadata for the specified table.
     *
     * @param string $table Name of a table
     *
     * @return array<string,Column> an array of {@link Column} objects
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

    public function eqToken(mixed $value): string
    {
        if (is_array($value)) {
            return 'IN(?)';
        } elseif (is_null($value)) {
            return 'IS ?';
        }

        return '= ?';
    }

    /**
     * Execute a raw SQL query on the database.
     *
     * @param string      $sql     raw SQL string to execute
     * @param list<mixed> &$values Optional array of bind values
     *
     * @return mixed A result set object
     */
    public function query(string $sql, array &$values = [])
    {
        if ($this->logging) {
            $this->logger?->info($sql);
            if ($values) {
                $this->logger?->info(var_export($values, true));
            }
        }

        $this->last_query = $sql;

        try {
            if (!($sth = $this->connection->prepare($sql))) {
                throw new DatabaseException();
            }
        } catch (\PDOException $e) {
            throw new DatabaseException($e);
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

    public function not(): string
    {
        return '!';
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
     * @param string      $sql    raw SQL string to execute
     * @param list<mixed> $values
     */
    public function query_and_fetch(string $sql, array $values = [], int $method = \PDO::FETCH_ASSOC): \Generator
    {
        $sth = $this->query($sql, $values);

        while ($row = $sth->fetch($method)) {
            yield $row;
        }
    }

    /**
     * Returns all tables for the current database.
     *
     * @return list<string> array containing table names
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
        assert($this->connection->beginTransaction(), new DatabaseException());
    }

    /**
     * Commits the current transaction.
     *
     * @throws DatabaseException
     */
    public function commit(): void
    {
        assert($this->connection->commit(), new DatabaseException('Failed to commit'));
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void
    {
        assert($this->connection->rollback(), new DatabaseException('Failed to roll back'));
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
     * @return string sequence name or null if not supported
     */
    public function init_sequence_name(Table $table): string
    {
        return '';
    }

    /**
     * @param array<string> $sequence
     */
    public function buildInsert(string $table, string $keys, array $sequence): string
    {
        return "INSERT INTO $table($keys) VALUES(?)";
    }

    /**
     * Quote a name like table names and field names.
     *
     * @param string $string string to quote
     */
    public function quote_name(string $string): string
    {
        return $string[0] === static::$QUOTE_CHARACTER || $string[strlen($string) - 1] === static::$QUOTE_CHARACTER ?
            $string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
    }

    public function guard_name(string $string): string
    {
        return $string;
    }

    /**
     * Escape the column names in the where phrases
     *
     * @param string       $expression The where clause to be escaped
     * @param list<string> $columns    The columns of the table
     */
    public function escapeColumns(string $expression, array $columns): string
    {
        return $expression;
    }

    public function date_string(\DateTimeInterface $datetime): string
    {
        return $datetime->format(static::$date_format);
    }

    /**
     * Return a date time formatted into the database's datetime format.
     */
    public function datetime_string(\DateTimeInterface $datetime): string
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

        assert($date instanceof \DateTime);

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
