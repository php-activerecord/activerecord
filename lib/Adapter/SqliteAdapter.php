<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord\Adapter;

use ActiveRecord\Column;
use ActiveRecord\Connection;
use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\ConnectionException;
use ActiveRecord\Inflector;
use ActiveRecord\Utils;

/**
 * Adapter for SQLite.
 */
class SqliteAdapter extends Connection
{
    public static string $datetime_format = 'Y-m-d H:i:s';

    protected function __construct(array $info)
    {
        if (!file_exists($info['host'])) {
            throw new ConnectionException('Could not find sqlite db: ' . $info['host']);
        }
        $this->connection = new \PDO('sqlite:' . $info['host'], null, null, static::$PDO_OPTIONS);
    }

    public function limit(string $sql, int $offset = 0, int $limit = 0)
    {
        $offset = 0 == $offset ? '' : $offset . ',';

        return "$sql LIMIT {$offset}$limit";
    }

    public function query_column_info(string $table): \PDOStatement
    {
        $table = $this->quote_name($table);

        return $this->query("pragma table_info($table)");
    }

    public function query_for_tables(): \PDOStatement
    {
        return $this->query('SELECT name FROM sqlite_master');
    }

    /**
     * @param array{
     *  cid: int,
     *  name: string,
     *  type: string,
     *  notnull: int,
     *  dflt_value: mixed,
     *  pk: mixed
     * } $column
     */
    public function create_column(array $column): Column
    {
        $c = new Column();
        $c->inflected_name  = Inflector::variablize($column['name']);
        $c->name            = $column['name'];
        $c->nullable        = !$column['notnull'];
        $c->pk              = (bool) $column['pk'];
        $c->auto_increment  = in_array(
            strtoupper($column['type']),
            ['INT', 'INTEGER']
        ) && $c->pk;

        $type = preg_replace('/ +/', ' ', $column['type']);
        assert(is_string($type));
        $type = str_replace(['(', ')'], ' ', $type);
        assert(is_string($type));
        $type = Utils::squeeze(' ', $type);
        $matches = explode(' ', $type);

        $c->raw_type = strtolower($matches[0]);

        if (count($matches) > 1) {
            $c->length = intval($matches[1]);
        }

        $c->map_raw_type();

        if (Column::DATETIME == $c->type) {
            $c->length = 19;
        } elseif (Column::DATE == $c->type) {
            $c->length = 10;
        }

        // From SQLite3 docs: The value is a signed integer, stored in 1, 2, 3, 4, 6,
        // or 8 bytes depending on the magnitude of the value.
        // so is it ok to assume it's possible an int can always go up to 8 bytes?
        if (Column::INTEGER == $c->type && !$c->length) {
            $c->length = 8;
        }

        $c->default = $c->cast($column['dflt_value'], $this);

        return $c;
    }

    public function set_encoding(string $charset): void
    {
        throw new ActiveRecordException('SqliteAdapter::set_charset not supported.');
    }

    public function not(): string
    {
        return 'NOT ';
    }
}
