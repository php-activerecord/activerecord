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
class Expressions
{
    public const ParameterMarker = '?';

    private string $expressions;

    /**
     * @var array<mixed>
     */
    private array $values = [];
    private Connection|null $connection;

    /**
     * @param string|array<mixed>|null $expressions
     */
    public function __construct(Connection|null $connection, string|array $expressions = null)
    {
        $values = null;
        $this->connection = $connection;

        if (is_array($expressions)) {
            $glue = func_num_args() > 2 ? func_get_arg(2) : ' AND ';
            list($expressions, $values) = $this->build_sql_from_hash($expressions, $glue);
        }

        if ('' != $expressions) {
            if (!$values) {
                $values = array_slice(func_get_args(), 2);
            }

            $this->values = $values;
            $this->expressions = $expressions;
        }
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
    public function to_s(bool $substitute = false, array $options = []): string
    {
        $values = $options['values'] ?? $this->values;
        $ret = '';
        $num_values = count($values);
        $quotes = 0;

        for ($i = 0, $n = strlen($this->expressions), $j = 0; $i < $n; ++$i) {
            $ch = $this->expressions[$i];

            if (self::ParameterMarker == $ch) {
                if (0 == $quotes % 2) {
                    if ($j > $num_values - 1) {
                        throw new ExpressionsException("No bound parameter for index $j");
                    }
                    $ch = $this->substitute($values, $substitute, $i, $j++);
                }
            } elseif ('\'' == $ch && $i > 0 && '\\' != $this->expressions[$i - 1]) {
                ++$quotes;
            }

            $ret .= $ch;
        }

        return $ret;
    }

    /**
     * @param Attributes $hash
     *
     * @return array<mixed>
     */
    private function build_sql_from_hash(array $hash, string $glue): array
    {
        $sql = $g = '';

        foreach ($hash as $name => $value) {
            if ($this->connection) {
                $name = $this->connection->quote_name($name);
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
    private function substitute(array $values, bool $substitute, int $pos, int $parameter_index)
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

        return $this->expressions[$pos];
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
