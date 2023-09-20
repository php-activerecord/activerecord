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
     * @throws ExpressionsException
     */
    public function to_s(Connection $connection, string $prependTableName = '', array $mappedNames = [],
        bool $substitute=false, string $glue=' AND ', array $values=null): string
    {
        $values = $values ?? $this->values;
        $expression = $this->expression;
        if (is_hash($expression)) {
            $hash = self::map_names($expression, $mappedNames);
            list($expression, $values) = self::build_sql_from_hash($connection, $hash, $prependTableName, $glue);
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

            $expression .= $connection->quote_name($name) . $bind;
        }

        return new WhereClause($expression, $conditionValues);
    }

    /**
     * @param array<mixed> $options
     */
    public static function from_arg(mixed $arg, array $options, Connection $connection = null, string $table = '', bool $negated=false, bool $isOr=false): WhereClause
    {
        if ($arg instanceof Relation) {
            [$template, $values] = self::glueWhereClauses($arg, $connection, $table, $options, $isOr ? ' AND ' : ' OR ');

            return new WhereClause($template, $values, $negated);
        }

        // user passed in a string, a hash, or an array consisting of a string and values
        if (is_string($arg) || is_hash($arg)) {
            return new WhereClause($arg, [], $negated);
        }

        return new WhereClause($arg[0], array_slice($arg, 1), $negated);
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private static function glueWhereClauses(Relation $arg, ?Connection $connection, string $table, array $options, string $glue): array
    {
        $clauses = $arg->getWhereConditions();

        $expressions = [];
        $values = [];

        foreach ($clauses as $clause) {
            if (is_hash($clause->expression)) {
                $mappedNames = $options['mapped_names'] ?? [];
                $hash = self::map_names($expression, $mappedNames);
                list($expression, $values) = self::build_sql_from_hash($connection, $hash, !empty($options['joins']) ? $table : '', ' AND ');
            } else {
                $expression = $clause->expression;
            }

            $negated = $clause->negated ? '!(' : '';
            $negatedEnd = $clause->negated ? ')' : '';
            $expressions[] = "{$negated}{$expression}{$negatedEnd}";
            $values = array_merge($values, $clause->values);
        }

        if (count($expressions) > 1) {
            $expression = '((' . implode("){$glue}(", $expressions) . '))';
        } else {
            $expression = (0 === count($expressions)) ? '' : $expressions[0];
        }

        return [$expression, array_flatten($values)];
    }

    /**
     * Replaces any aliases used in a hash based condition.
     *
     * @param array<string, string> $hash A hash
     * @param array<string, string> $map  Hash of used_name => real_name
     *
     * @return array<string, string> Array with any aliases replaced with their read field name
     */
    private static function map_names(array &$hash, array &$map): array
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
    private static function build_sql_from_hash(?Connection $connection, array $hash, string $prependTableName, string $glue = ' AND '): array
    {
        $sql = $g = '';

        $table = !empty($prependTableName) && null != $connection ? $connection->quote_name($prependTableName) : '';

        foreach ($hash as $name => $value) {
            if (!empty($prependTableName)) {
                $name = $table . '.' . $name;
            }

            if (is_array($value)) {
                $sql .= "$g$name IN(?)";
            } elseif (is_null($value)) {
                $sql .= "$g$name IS ?";
            } else {
                $sql .= "$g$name = ?";
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
    private function substitute(Connection $connection, string $expression, array $values, bool $substitute, int $pos, int $parameter_index)
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
