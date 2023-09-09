<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ExpressionsException;

/**
 * Templating like class for building SQL statements.
 *
 * Examples:
 * 'name = :name AND author = :author'
 * 'id = IN(:ids)'
 * 'id IN(:subselect)'
 *
 * @phpstan-import-type Attributes from Types
 */
class WhereClause
{
    public const ParameterMarker = '?';

    private array|string $expression;

    private bool $inverse = false;

    /**
     * @var array<mixed>
     */
    private array $values = [];
    private Connection|null $connection;

    /**
     * @param string|array<mixed> $expression
     */
    public function __construct(string|array $expression, array $values=[], bool $inverse = false)
    {
        $this->inverse = $inverse;
        $this->expression = $expression;
        $this->values = $values;

//        $values = null;

//        if (is_array($expression)) {
//            $glue = func_num_args() > 2 ? func_get_arg(2) : ' AND ';
//            list($expression, $values) = $this->build_sql_from_hash($expression, $glue);
//        }
//
//        if ('' != $expression) {
//            if (!$values) {
//                $values = array_slice(func_get_args(), 2);
//            }
//
//            $this->values = $values;
//            $this->expression = $expression;
//        }
    }

    public function inverse(): bool {
        return $this->inverse;
    }

    /**
     * Bind a value to the specific one based index. There must be a bind marker
     * for each value bound or to_s() will throw an exception.
     */
    public function bind(int $parameter_number, mixed $value): void
    {
        if ($parameter_number <= 0) {
            throw new ExpressionsException("Invalid parameter index: $parameter_number");
        }
        $this->values[$parameter_number - 1] = $value;
    }

    /**
     * @param array<mixed> $values
     */
    public function bind_values(array $values): void
    {
        $this->values = $values;
    }

    /**
     * Returns all the values currently bound.
     *
     * @return array<mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Returns the connection object.
     */
    public function get_connection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Sets the connection object. It is highly recommended to set this so we can
     * use the adapter's native escaping mechanism.
     *
     * @param Connection $connection a Connection instance
     */
    public function set_connection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @param array{
     *   values?: array<mixed>
     * } $options
     *
     * @throws ExpressionsException
     */
    public function to_s(bool $prependTableName = false, array $mappedNames = []): array
    {
        $values = $this->values;
        $expression = $this->expression;
        if(is_hash($expression)) {
           $expression = $this->map_names($expression, $mappedNames);
           list($expression, $values) = $this->build_sql_from_hash($expression, $prependTableName);
        }

        $ret = '';
        $num_values = count($values);
        $quotes = 0;

        for ($i = 0, $j = 0; $i < strlen($expression); ++$i) {
            $ch = $expression[$i];

            if (self::ParameterMarker == $ch) {
                if (0 == $quotes % 2) {
                    if ($j > $num_values - 1) {
                        throw new ExpressionsException("No bound parameter for index $j");
                    }
                    $ch = $this->substitute($expression, $values, false, $i, $j++);
                }
            } elseif ('\'' == $ch && $i > 0 && '\\' != $expression[$i - 1]) {
                ++$quotes;
            }

            $ret .= $ch;
        }

        return [$ret, $values];
    }

    /**
     * Converts a string like "id_and_name_or_z" into a conditions value like
     * ["id=? AND name=? OR z=?", values, ...].
     *
     * @param string               $name   Underscored string
     * @param array<mixed>         $values Array of values for the field names. This is used
     *                                     to determine what kind of bind marker to use: =?, IN(?), IS NULL
     * @param array<string,string> $map    A hash of "mapped_column_name" => "real_column_name"
     *
     * @return array<mixed>
     */
    public static function from_underscored_string(
        Connection $connection, ?string $name, array $values = [], array $map = []): ?WhereClause
    {
        if (!$name) {
            return null;
        }

        $expression = '';

        $num_values = count((array) $values);
        $conditionValues = [];

        $parts = SQLBuilder::underscored_string_to_parts($name);

        for ($i = 0, $j = 0, $n = count($parts); $i < $n; $i += 2, ++$j) {
            if ($i >= 2) {
                $res = preg_replace(['/_and_/i', '/_or_/i'], [' AND ', ' OR '], $parts[$i - 1]);
                assert(is_string($res));
                $expression .= $res;
            }

            if ($j < $num_values) {
                if (!is_null($values[$j])) {
                    $bind = is_array($values[$j]) ? ' IN(?)' : '=?';
                    $conditionValues[] = $values[$j];
                } else {
                    $bind = ' IS NULL';
                }
            } else {
                $bind = ' IS NULL';
            }

            // map to correct name if $map was supplied
            $name = $map && isset($map[$parts[$i]]) ? $map[$parts[$i]] : $parts[$i];

            $expression .= $connection->quote_name($name) . $bind;
        }

        return new WhereClause($expression, $conditionValues);
    }

    public static function from_arg(mixed $arg) {
        // user passed in a string, a hash, or an array consisting of a string and values
        if (is_string($arg) || is_hash($arg)) {
            $expression = new WhereClause($arg, []);
        } else {
            $expression = new WhereClause($arg[0], array_slice($arg, 1));
        }
        return $expression;
    }

    /**
     * Replaces any aliases used in a hash based condition.
     *
     * @param array<string, string> $hash A hash
     * @param array<string, string> $map  Hash of used_name => real_name
     *
     * @return array<string, string> Array with any aliases replaced with their read field name
     */
    private function map_names(array &$hash, array &$map): array
    {
        $ret = [];

        foreach ($hash as $name => &$value) {
            if (array_key_exists($name, $map)) {
                $name = $map[$name];
            }

            $ret[$name] = $value;
        }

        return $ret;
    }

    /**
     * @param Attributes $hash
     *
     * @return array<mixed>
     */
    private function build_sql_from_hash(array $hash, bool $prependTableName): array
    {
        $sql = $g = '';
        $glue = ' AND ';

        $table = $prependTableName ? $this->connection->quote_name($this->table) : '';

        foreach ($hash as $name => $value) {
            if ($this->connection) {
                $name = $this->connection->quote_name($name);
            }

            if($prependTableName) {
                $name = $table . '.' . $name;
            }

            if (is_array($value)) {
                $sql .= "$g$name IN(?)";
            } elseif (is_null($value)) {
                $sql .= "$g$name IS ?";
            } else {
                $sql .= "$g$name=?";
            }

            $g = $glue;
        }

        return [$sql, array_values($hash)];
    }

    /**
     * @param array<mixed> $values
     *
     * @return mixed|string
     */
    private function substitute(string $expression, array $values, bool $substitute, int $pos, int $parameter_index)
    {
        $value = $values[$parameter_index];

        if (is_array($value)) {
            $value_count = count($value);

            if (0 === $value_count) {
                if ($substitute) {
                    return 'NULL';
                }

                return self::ParameterMarker;
            }

            if ($substitute) {
                $ret = '';

                for ($i = 0, $n = $value_count; $i < $n; ++$i) {
                    $ret .= ($i > 0 ? ',' : '') . $this->stringify_value($value[$i]);
                }

                return $ret;
            }

            return join(',', array_fill(0, $value_count, self::ParameterMarker));
        }

        if ($substitute) {
            return $this->stringify_value($value);
        }

        return $expression[$pos];
    }

    private function stringify_value(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        return is_string($value) ? $this->quote_string($value) : $value;
    }

    private function quote_string(string $value): string
    {
        if ($this->connection) {
            return $this->connection->escape($value);
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }
}
