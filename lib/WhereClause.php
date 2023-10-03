<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\DatabaseException;

/**
 * Templating like class for building SQL statements.
 *
 * Examples:
 * 'name = :name AND author = :author'
 * 'id = IN(:ids)'
 * 'id IN(:subselect)'
 *
 * @phpstan-import-type Attributes from Types
 *
 * @phpstan-type Expression string|array<mixed>
 */
class WhereClause
{
    public const ParameterMarker = '?';

    /**
     * @var Expression
     */
    private array|string $expression;

    private bool $negated = false;

    /**
     * @var array<mixed>
     */
    private array $values = [];

    /**
     * @param Expression   $expression
     * @param array<mixed> $values
     * @param bool         $negated    if true, then this where clause will be a logical
     *                                 not when the SQL is generated
     */
    public function __construct(string|array $expression, array $values=[], bool $negated = false)
    {
        $this->negated = $negated;
        $this->expression = $expression;
        $this->values = $values;
    }

    public function negated(): bool
    {
        return $this->negated;
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
     * @return Expression
     */
    public function expression(): array|string
    {
        return $this->expression;
    }

    /**
     * @param array<string,string> $mappedNames
     * @param array<mixed>         $values
     *
     * @throws DatabaseException
     */
    public function to_s(Connection $connection, string $prependTableName = '', array $mappedNames = [],
        bool $substitute=false, string $glue=' AND ', array $values=null): string
    {
        $values = $values ?? $this->values;
        $expression = $this->expression;
        if (is_hash($expression)) {
            $hash = $this->map_names($expression, $mappedNames);
            list($expression, $values) = $this->build_sql_from_hash($connection, $hash, $prependTableName, $glue);
        }

        $ret = '';
        $num_values = count($values);
        $quotes = 0;

        if (1 == $num_values && is_array($values[0]) && 0 == count($values[0])) {
            return '1=0';
        }

        for ($i = 0, $j = 0; $i < strlen($expression); ++$i) {
            $ch = $expression[$i];

            if (self::ParameterMarker == $ch) {
                if (0 == $quotes % 2) {
                    if ($j > $num_values - 1) {
                        throw new DatabaseException("No bound parameter for index $j");
                    }
                    $ch = $this->substitute($connection, $expression, $values, $substitute, $i, $j++);
                }
            } elseif ('\'' == $ch && $i > 0 && '\\' != $expression[$i - 1]) {
                ++$quotes;
            }

            $ret .= $ch;
        }

        $this->values = $values;

        return $ret;
    }

    /**
     * Converts a string like "id_and_name_or_z" into a conditions value like
     * ["id=? AND name=? OR z=?", values, ...].
     *
     * @param string               $name   Underscored string
     * @param array<mixed>         $values Array of values for the field names. This is used
     *                                     to determine what kind of bind marker to use: =?, IN(?), IS NULL
     * @param array<string,string> $map    A hash of "mapped_column_name" => "real_column_name"
     */
    public static function from_underscored_string(
        Connection $connection, string $name, array $values = [], array $map = []): WhereClause
    {
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

            $expression .= $name . $bind;
        }

        return new WhereClause($expression, $conditionValues);
    }

    public static function from_arg(mixed $arg, bool $negated=false): WhereClause
    {
        // user passed in a string, a hash, or an array consisting of a string and values
        if (is_string($arg) || is_hash($arg)) {
            $expression = new WhereClause($arg, [], $negated);
        } else {
            $expression = new WhereClause($arg[0], array_slice($arg, 1), $negated);
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
    private function build_sql_from_hash(Connection $connection, array $hash, string $prependTableName, string $glue = ' AND '): array
    {
        $sql = $g = '';

        $table = !empty($prependTableName) ? $connection->quote_name($prependTableName) : '';

        foreach ($hash as $name => $value) {
            if (!empty($prependTableName)) {
                $name = $table . '.' . $name;
            }

            $sql .=  "$g$name " . $connection->eqToken($value);

            $g = $glue;
        }

        return [$sql, array_values($hash)];
    }

    /**
     * @param array<mixed> $values
     *
     * @return mixed|string
     */
    private function substitute(Connection $connection, string $expression, array $values, bool $substitute, int $pos, int $parameter_index)
    {
        $value = $values[$parameter_index];

        if (is_array($value)) {
            $value_count = count($value);
            if ($substitute) {
                $ret = '';

                for ($i = 0, $n = $value_count; $i < $n; ++$i) {
                    $ret .= ($i > 0 ? ',' : '') . $this->stringify_value($connection, $value[$i]);
                }

                return $ret;
            }

            return join(',', array_fill(0, $value_count, self::ParameterMarker));
        }

        if ($substitute) {
            return $this->stringify_value($connection, $value);
        }

        return $expression[$pos];
    }

    private function stringify_value(Connection $connection, mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        return is_string($value) ? $connection->escape($value) : $value;
    }
}
