<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord\Adapter;

use ActiveRecord\Column;
use ActiveRecord\Connection;
use ActiveRecord\Inflector;

/**
 * Adapter for MySQL.
 *
 * @package ActiveRecord
 */
class MysqlAdapter extends Connection
{
    public static int $DEFAULT_PORT = 3306;

    public function limit(string $sql, int $offset = 0, int $limit = 0): string
    {
        $offset = 0 == $offset ? '' : $offset . ',';

        return "$sql LIMIT {$offset}$limit";
    }

    public function query_column_info(string $table): \PDOStatement
    {
        return $this->query("SHOW COLUMNS FROM $table");
    }

    public function query_for_tables(): \PDOStatement
    {
        return $this->query('SHOW TABLES');
    }

    /**
     * @param array<string, string|null> $column
     */
    public function create_column(array $column): Column
    {
        $c = new Column();
        $c->inflected_name    = Inflector::instance()->variablize($column['field']);
        $c->name            = $column['field'];
        $c->nullable        = ('YES' === $column['null'] ? true : false);
        $c->pk                = ('PRI' === $column['key'] ? true : false);
        $c->auto_increment    = ('auto_increment' === $column['extra'] ? true : false);

        if ('timestamp' == $column['type'] || 'datetime' == $column['type']) {
            $c->raw_type = 'datetime';
            $c->length = 19;
        } elseif ('date' == $column['type']) {
            $c->raw_type = 'date';
            $c->length = 10;
        } elseif ('time' == $column['type']) {
            $c->raw_type = 'time';
            $c->length = 8;
        } else {
            preg_match('/^([A-Za-z0-9_]+)(\(([0-9]+(,[0-9]+)?)\))?/', $column['type'], $matches);

            $c->raw_type = (count($matches) > 0 ? $matches[1] : $column['type']);

            if (count($matches) >= 4) {
                $c->length = intval($matches[3]);
            }
        }

        $c->map_raw_type();
        $c->default = $c->cast($column['default'], $this);

        return $c;
    }

    public function set_encoding(string $charset): void
    {
        $params = [$charset];
        $this->query('SET NAMES ?', $params);
    }

    public function accepts_limit_and_order_for_update_and_delete(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function native_database_types(): array
    {
        return [
            'primary_key' => 'int(11) UNSIGNED DEFAULT NULL auto_increment PRIMARY KEY',
            'string' => ['name' => 'varchar', 'length' => 255],
            'text' => ['name' => 'text'],
            'integer' => ['name' => 'int', 'length' => 11],
            'float' => ['name' => 'float'],
            'datetime' => ['name' => 'datetime'],
            'timestamp' => ['name' => 'datetime'],
            'time' => ['name' => 'time'],
            'date' => ['name' => 'date'],
            'binary' => ['name' => 'blob'],
            'boolean' => ['name' => 'tinyint', 'length' => 1]
        ];
    }
}
