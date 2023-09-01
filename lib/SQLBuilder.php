<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ActiveRecordException;

/**
 * Helper class for building sql statements progmatically.
 *
 * @phpstan-import-type Attributes from Model
 *
 * @package ActiveRecord
 */
class SQLBuilder
{
    private Connection $connection;
    private string $operation = 'SELECT';
    private string $table;
    private string $select = '*';

    private string $joins = '';
    private string $order = '';
    private int $limit = 0;
    private int $offset = 0;
    private string $group = '';
    private string $having = '';
    private string $update = '';

    // for where
    private string $where = '';

    /**
     * @var array<mixed>
     */
    private array $where_values = [];

    /**
     * for insert/update
     *
     * @var Attributes
     */
    private array $data = [];

    /**
     * @var array<string>
     */
    private array $sequence = [];

    /**
     * Constructor.
     *
     * @param Connection $connection A database connection object
     * @param string     $table      Name of a table
     *
     * @throws ActiveRecordException if connection was invalid
     *
     * @return SQLBuilder
     */
    public function __construct(Connection $connection, string $table)
    {
        $this->connection    = $connection;
        $this->table        = $table;
    }

    /**
     * Returns the SQL string.
     */
    public function __toString(): string
    {
        return $this->to_s();
    }

    /**
     * Returns the SQL string.
     *
     * @see __toString
     */
    public function to_s(): string
    {
        $func = 'build_' . strtolower($this->operation);

        return $this->$func();
    }

    /**
     * Returns the bind values.
     *
     * @return array<mixed>
     */
    public function bind_values(): array
    {
        $ret = [];

        if ($this->data) {
            $ret = array_values($this->data);
        }

        if ($this->get_where_values()) {
            $ret = array_merge($ret, $this->get_where_values());
        }

        return array_flatten($ret);
    }

    /**
     * @return array<mixed>
     */
    public function get_where_values(): array
    {
        return $this->where_values;
    }

    public function where(/* (conditions, values) || (hash) */): static
    {
        $this->apply_where_conditions(func_get_args());

        return $this;
    }

    public function order(string $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function having(string $having): static
    {
        $this->having = $having;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function select(string $select): static
    {
        $this->operation = 'SELECT';
        $this->select = $select;

        return $this;
    }

    /**
     * @param string|array<string> $joins
     *
     * @return $this
     */
    public function joins(string|array $joins): static
    {
        $this->joins = $joins;

        return $this;
    }

    /**
     * @param Attributes $hash
     *
     * @throws ActiveRecordException
     *
     * @return $this
     */
    public function insert(array $hash, mixed $pk = null, string $sequence_name = null): static
    {
        if (!is_hash($hash)) {
            throw new ActiveRecordException('Inserting requires a hash.');
        }
        $this->operation = 'INSERT';
        $this->data = $hash;

        if ($pk && $sequence_name) {
            $this->sequence = [$pk, $sequence_name];
        }

        return $this;
    }

    /**
     * @param array<string,string>|string $mixed
     *
     * @throws ActiveRecordException
     */
    public function update(array|string $mixed): static
    {
        $this->operation = 'UPDATE';

        if (is_hash($mixed)) {
            $this->data = $mixed;
        } elseif (is_string($mixed)) {
            $this->update = $mixed;
        } else {
            throw new ActiveRecordException('Updating requires a hash or string.');
        }

        return $this;
    }

    public function delete(): static
    {
        $this->operation = 'DELETE';
        $this->apply_where_conditions(func_get_args());

        return $this;
    }

    /**
     * Reverses an order clause.
     */
    public static function reverse_order(string $order = ''): string
    {
        if (!trim($order)) {
            return $order;
        }

        $parts = explode(',', $order);

        for ($i = 0, $n = count($parts); $i < $n; ++$i) {
            $v = strtolower($parts[$i]);

            if (false !== strpos($v, ' asc')) {
                $parts[$i] = preg_replace('/asc/i', 'DESC', $parts[$i]);
            } elseif (false !== strpos($v, ' desc')) {
                $parts[$i] = preg_replace('/desc/i', 'ASC', $parts[$i]);
            } else {
                $parts[$i] .= ' DESC';
            }
        }

        return join(',', $parts);
    }

    /**
     * Converts a string like "id_and_name_or_z" into a conditions value like array("id=? AND name=? OR z=?", values, ...).
     *
     * @param string               $name   Underscored string
     * @param array<mixed>         $values Array of values for the field names. This is used
     *                                     to determine what kind of bind marker to use: =?, IN(?), IS NULL
     * @param array<string,string> $map    A hash of "mapped_column_name" => "real_column_name"
     *
     * @return array<mixed>
     */
    public static function create_conditions_from_underscored_string(Connection $connection, ?string $name, array $values = [], array $map = []): ?array
    {
        if (!$name) {
            return null;
        }

        $parts = preg_split('/(_and_|_or_)/i', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
        $num_values = count((array) $values);
        $conditions = [''];

        for ($i = 0, $j = 0, $n = count($parts); $i < $n; $i += 2, ++$j) {
            if ($i >= 2) {
                $res = preg_replace(['/_and_/i', '/_or_/i'], [' AND ', ' OR '], $parts[$i - 1]);
                assert(is_string($res));
                $conditions[0] .= $res;
            }

            if ($j < $num_values) {
                if (!is_null($values[$j])) {
                    $bind = is_array($values[$j]) ? ' IN(?)' : '=?';
                    $conditions[] = $values[$j];
                } else {
                    $bind = ' IS NULL';
                }
            } else {
                $bind = ' IS NULL';
            }

            // map to correct name if $map was supplied
            $name = $map && isset($map[$parts[$i]]) ? $map[$parts[$i]] : $parts[$i];

            $conditions[0] .= $connection->quote_name($name) . $bind;
        }

        return $conditions;
    }

    /**
     * Like create_conditions_from_underscored_string but returns a hash of name => value array instead.
     *
     * @param string               $name   A string containing attribute names connected with _and_ or _or_
     * @param array<mixed>         $values Array of values for each attribute in $name
     * @param array<string,string> $map    A hash of "mapped_column_name" => "real_column_name"
     *
     * @return array<string,mixed> A hash of array(name => value, ...)
     */
    public static function create_hash_from_underscored_string(string $name, array $values = [], array &$map = [])
    {
        $parts = preg_split('/(_and_|_or_)/i', $name);
        assert(is_array($parts));
        $hash = [];

        for ($i = 0, $n = count($parts); $i < $n; ++$i) {
            // map to correct name if $map was supplied
            $name = $map[$parts[$i]] ?? $parts[$i];
            assert(is_string($name));
            $hash[$name] = $values[$i];
        }

        return $hash;
    }

    /**
     * Prepends table name to hash of field names to get around ambiguous fields when SQL builder
     * has joins
     *
     * @param array<string,string> $hash
     *
     * @return array<string,string> $new
     */
    private function prepend_table_name_to_fields(array $hash = [])
    {
        $new = [];
        $table = $this->connection->quote_name($this->table);

        foreach ($hash as $key => $value) {
            $k = $this->connection->quote_name($key);
            $new[$table . '.' . $k] = $value;
        }

        return $new;
    }

    /**
     * @param array<string|array<string,mixed>> $args
     *
     * @throws Exception\ExpressionsException
     */
    private function apply_where_conditions(array $args): void
    {
        $num_args = count($args);

        if (1 == $num_args && is_hash($args[0])) {
            $hash = empty($this->joins) ? $args[0] : $this->prepend_table_name_to_fields($args[0]);
            $e = new Expressions($this->connection, $hash);
            $this->where = $e->to_s();
            $this->where_values = array_flatten($e->values());
        } elseif ($num_args > 0) {
            // if the values has a nested array then we'll need to use Expressions to expand the bind marker for us
            $values = array_slice($args, 1);

            foreach ($values as $name => &$value) {
                if (is_array($value)) {
                    $e = new Expressions($this->connection, $args[0]);
                    $e->bind_values($values);
                    $this->where = $e->to_s();
                    $this->where_values = array_flatten($e->values());

                    return;
                }
            }

            // no nested array so nothing special to do
            $this->where = $args[0] ?? '';
            $this->where_values = &$values;
        }
    }

    private function build_delete(): string
    {
        $sql = "DELETE FROM $this->table";

        if ($this->where) {
            $sql .= " WHERE $this->where";
        }

        if ($this->connection->accepts_limit_and_order_for_update_and_delete()) {
            if ($this->order) {
                $sql .= " ORDER BY $this->order";
            }

            if ($this->limit) {
                $sql = $this->connection->limit($sql, 0, $this->limit);
            }
        }

        return $sql;
    }

    private function build_insert(): string
    {
        require_once 'Expressions.php';
        $keys = join(',', $this->quoted_key_names());

        if ($this->sequence) {
            $sql =
                "INSERT INTO $this->table($keys," . $this->connection->quote_name($this->sequence[0]) .
                ') VALUES(?,' . $this->connection->next_sequence_value($this->sequence[1]) . ')';
        } else {
            $sql = "INSERT INTO $this->table($keys) VALUES(?)";
        }

        $e = new Expressions($this->connection, $sql, array_values($this->data));

        return $e->to_s();
    }

    private function build_select(): string
    {
        $sql = "SELECT $this->select FROM $this->table";

        if (!empty($this->joins)) {
            $sql .= ' ' . $this->joins;
        }

        if ($this->where) {
            $sql .= " WHERE $this->where";
        }

        if ($this->group) {
            $sql .= " GROUP BY $this->group";
        }

        if ($this->having) {
            $sql .= " HAVING $this->having";
        }

        if ($this->order) {
            $sql .= " ORDER BY $this->order";
        }

        if ($this->limit || $this->offset) {
            $sql = $this->connection->limit($sql, $this->offset, $this->limit);
        }

        return $sql;
    }

    private function build_update(): string
    {
        if (strlen($this->update ?? '') > 0) {
            $set = $this->update;
        } else {
            $set = join('=?, ', $this->quoted_key_names()) . '=?';
        }

        $sql = "UPDATE $this->table SET $set";

        if ($this->where) {
            $sql .= " WHERE $this->where";
        }

        if ($this->connection->accepts_limit_and_order_for_update_and_delete()) {
            if ($this->order) {
                $sql .= " ORDER BY $this->order";
            }

            if ($this->limit) {
                $sql = $this->connection->limit($sql, 0, $this->limit);
            }
        }

        return $sql;
    }

    /**
     * @return array<string>
     */
    private function quoted_key_names(): array
    {
        $keys = [];

        foreach ($this->data as $key => $value) {
            $keys[] = $this->connection->quote_name($key);
        }

        return $keys;
    }
}
