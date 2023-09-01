<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord\Adapter;

use ActiveRecord\Column;
use ActiveRecord\Connection;
use ActiveRecord\Inflector;

/**
 * Adapter for Postgres (not completed yet)
 *
 * @package ActiveRecord
 */
class PgsqlAdapter extends Connection
{
    public static string $QUOTE_CHARACTER = '"';
    public static int $DEFAULT_PORT = 5432;

    public function supports_sequences(): bool
    {
        return true;
    }

    public function get_sequence_name(string $table, string $column_name): string
    {
        return "{$table}_{$column_name}_seq";
    }

    public function next_sequence_value(string $sequence_name): ?string
    {
        return "nextval('" . str_replace("'", "\\'", $sequence_name) . "')";
    }

    public function limit(string $sql, int $offset = 0, int $limit = 0): string
    {
        return $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    public function query_column_info(string $table): \PDOStatement
    {
        $sql = <<<SQL
SELECT
      a.attname AS field,
      a.attlen,
      REPLACE(pg_catalog.format_type(a.atttypid, a.atttypmod), 'character varying', 'varchar') AS type,
      a.attnotnull AS not_nullable,
      (SELECT 't'
       FROM pg_index
       WHERE c.oid = pg_index.indrelid
         AND a.attnum = ANY (pg_index.indkey)
         AND pg_index.indisprimary = 't'
      ) IS NOT NULL AS pk,
      REGEXP_REPLACE(
        REGEXP_REPLACE(
          REGEXP_REPLACE(
            pg_get_expr(pg_attrdef.adbin, pg_attrdef.adrelid),
            '::[a-z_ ]+', ''
          ),
          '''$',''
        ),
        '^''',''
      ) AS "default"
FROM pg_attribute a
JOIN pg_class c ON a.attrelid = c.oid
JOIN pg_type t ON a.atttypid = t.oid
LEFT JOIN pg_attrdef ON c.oid = pg_attrdef.adrelid AND a.attnum = pg_attrdef.adnum
WHERE c.relname = ?
      AND a.attnum > 0
ORDER BY a.attnum;

SQL;
        $values = [$table];

        return $this->query($sql, $values);
    }

    public function query_for_tables(): \PDOStatement
    {
        return $this->query("SELECT tablename FROM pg_tables WHERE schemaname NOT IN('information_schema','pg_catalog')");
    }

    public function create_column(array $column): Column
    {
        $c = new Column();
        $c->inflected_name    = Inflector::instance()->variablize($column['field']);
        $c->name            = $column['field'];
        $c->nullable        = ($column['not_nullable'] ? false : true);
        $c->pk                = ($column['pk'] ? true : false);
        $c->auto_increment    = false;

        if ('timestamp' == substr($column['type'], 0, 9)) {
            $c->raw_type = 'datetime';
            $c->length = 19;
        } elseif ('date' == $column['type']) {
            $c->raw_type = 'date';
            $c->length = 10;
        } else {
            preg_match('/^([A-Za-z0-9_]+)(\(([0-9]+(,[0-9]+)?)\))?/', $column['type'], $matches);

            $c->raw_type = (count($matches) > 0 ? $matches[1] : $column['type']);
            $c->length = count($matches) >= 4 ? intval($matches[3]) : intval($column['attlen']);

            if ($c->length < 0) {
                $c->length = null;
            }
        }

        $c->map_raw_type();

        if ($column['default']) {
            preg_match("/^nextval\('(.*)'\)$/", $column['default'], $matches);

            if (2 == count($matches)) {
                $c->sequence = $matches[1];
            } else {
                $c->default = $c->cast($column['default'], $this);
            }
        }

        return $c;
    }

    public function set_encoding(string $charset): void
    {
        $this->query("SET NAMES '$charset'");
    }

    public function native_database_types(): array
    {
        return [
            'primary_key' => 'serial primary key',
            'string' => ['name' => 'character varying', 'length' => 255],
            'text' => ['name' => 'text'],
            'integer' => ['name' => 'integer'],
            'float' => ['name' => 'float'],
            'datetime' => ['name' => 'datetime'],
            'timestamp' => ['name' => 'timestamp'],
            'time' => ['name' => 'time'],
            'date' => ['name' => 'date'],
            'binary' => ['name' => 'binary'],
            'boolean' => ['name' => 'boolean']
        ];
    }
}
