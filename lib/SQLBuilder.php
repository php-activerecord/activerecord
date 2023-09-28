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

    private bool $distinct = false;
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
     * @var list<string>
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

    /**
     * @param list<WhereClause>    $clauses
     * @param array<string,string> $mappedNames
     * @param array<string>        $columns     Table column names
     *
     * @return $this
     */
    public function where(array $clauses=[], array $mappedNames=[], array $columns=[]): static
    {
        $values = [];
        $sql = '';
        $glue = ' AND ';
        foreach ($clauses as $idx => $clause) {
            $expression = $clause->to_s($this->connection, !empty($this->joins) ? $this->table : '', $mappedNames);
            $expression = $this->connection->escapeColumns($expression, $columns);
            $values = array_merge($values, array_flatten($clause->values()));
            $inverse = $clause->negated() ? $this->connection->not() : '';
            $wrappedExpression = $inverse || count($clauses) > 1 ? '(' . $expression . ')' : $expression;
            $sql .=  $inverse . $wrappedExpression . ($idx < (count($clauses) - 1) ? $glue : '');
        }

        $this->where = $sql;
        $this->where_values = $values;

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

    public function select(string $select, bool $distinct = false): static
    {
        $this->distinct = $distinct;
        $this->operation = 'SELECT';
        $this->select = $select;

        return $this;
    }

    public function joins(string $joins): static
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
     * @param string|Attributes $data
     *
     * @throws ActiveRecordException
     */
    public function update(array|string $data): static
    {
        $this->operation = 'UPDATE';

        if (is_hash($data)) {
            $this->data = $data;
        } elseif (is_string($data)) {
            $this->update = $data;
        }

        return $this;
    }

    public function delete(): static
    {
        $this->operation = 'DELETE';

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
            assert(is_string($parts[$i]));
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
     * @return array<mixed>
     */
    public static function underscored_string_to_parts(string $string, int $flags=PREG_SPLIT_DELIM_CAPTURE): array
    {
        $res = preg_split('/(_and_|_or_)/i', $string, -1, $flags);
        assert(is_array($res));

        return $res;
    }

    /**
     * Like create_conditions_from_underscored_string but returns a hash of name => value array instead.
     *
     * @param string               $name   A string containing attribute names connected with _and_ or _or_
     * @param array<mixed>         $values Array of values for each attribute in $name
     * @param array<string,string> $map    A hash of "mapped_column_name" => "real_column_name"
     *
     * @return array<string,mixed>
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
        $keys = join(',', $this->quoted_key_names());
        $sql = $this->connection->buildInsert($this->table, $keys, $this->sequence);

        $e = new WhereClause($sql, [array_values($this->data)]);

        return $e->to_s(ConnectionManager::get_connection());
    }

    private function build_select(): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . "$this->select FROM $this->table";

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
     * @return list<string>
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
