<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

/**
 * Class for a table column.
 *
 * @package ActiveRecord
 */
class Column
{
    // types for $type
    public const STRING    = 1;
    public const INTEGER    = 2;
    public const DECIMAL    = 3;
    public const DATETIME    = 4;
    public const DATE        = 5;
    public const TIME        = 6;

    /**
     * Map type to column type.
     *
     * @var array<string, int>
     */
    public static array $TYPE_MAPPING = [
        'datetime'    => self::DATETIME,
        'timestamp'    => self::DATETIME,
        'date'        => self::DATE,
        'time'        => self::TIME,

        'tinyint'    => self::INTEGER,
        'smallint'    => self::INTEGER,
        'mediumint'    => self::INTEGER,
        'int'        => self::INTEGER,
        'bigint'    => self::INTEGER,

        'float'        => self::DECIMAL,
        'double'    => self::DECIMAL,
        'numeric'    => self::DECIMAL,
        'decimal'    => self::DECIMAL,
        'dec'        => self::DECIMAL];

    /**
     * The true name of this column.
     */
    public string $name = '';

    /**
     * The inflected name of this column. hyphens and spaces will become => _.
     */
    public string $inflected_name;

    /**
     * The type of this column: STRING, INTEGER, ...
     */
    public int $type;

    /**
     * The raw database specific type.
     */
    public string $raw_type;

    /**
     * The maximum length of this column.
     */
    public int|null $length = null;

    /**
     * True if this column allows null.
     */
    public bool $nullable;

    /**
     * True if this column is a primary key.
     */
    public bool $pk;

    /**
     * The default value of the column.
     */
    public mixed $default;

    /**
     * True if this column is set to auto_increment.
     */
    public bool $auto_increment;

    /**
     * Name of the sequence to use for this column if any.
     */
    public string $sequence;

    /**
     * Cast a value to an integer type safely
     *
     * This will attempt to cast a value to an integer,
     * unless its detected that the casting will cause
     * the number to overflow or lose precision, in which
     * case the number will be returned as a string, so
     * that large integers (BIGINTS, unsigned INTS, etc)
     * can still be stored without error
     *
     * This would ideally be done with bcmath or gmp, but
     * requiring a new PHP extension for a bug-fix is a
     * little ridiculous
     *
     * @param mixed $value The value to cast
     *
     * @return int|string type-casted value
     */
    public static function castIntegerSafely($value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        // string representation of a float
        elseif ((string) (float) $value === $value && (string) (int) $value !== $value) {
            return (int) $value;
        }

        // If adding 0 to a string causes a float conversion,
        // we have a number over PHP_INT_MAX
        // @phpstan-ignore-next-line
        elseif (is_string($value) && is_float($value + 0)) {
            return (string) $value;
        }

        // If a float was passed and is greater than PHP_INT_MAX
        // (which could be wrong due to floating point precision)
        // We'll also check for equal to (>=) in case the precision
        // loss creates an overflow on casting
        elseif (is_float($value) && $value >= PHP_INT_MAX) {
            return number_format($value, 0, '', '');
        }

        return (int) $value;
    }

    /**
     * Casts a value to the column's type.
     *
     * @param mixed      $value      The value to cast
     * @param Connection $connection The Connection this column belongs to
     *
     * @return mixed type-casted value
     */
    public function cast($value, $connection): mixed
    {
        if (null === $value) {
            return null;
        }

        switch ($this->type) {
            case self::STRING:    return (string) $value;
            case self::INTEGER:    return static::castIntegerSafely($value);
            case self::DECIMAL:    return (float) $value;
            default: // DATETIME, DATE, TIME
                if ('' === $value) {
                    return null;
                }

                $date_class = Config::instance()->get_date_class();

                if ($value instanceof $date_class) {
                    return $value;
                }

                if ($value instanceof \DateTime) {
                    return $date_class::createFromFormat(
                        Connection::DATETIME_TRANSLATE_FORMAT,
                        $value->format(Connection::DATETIME_TRANSLATE_FORMAT),
                        $value->getTimezone()
                    );
                }

                return $connection->string_to_datetime($value);
        }
    }

    /**
     * Sets the $type member variable.
     */
    public function map_raw_type(): int
    {
        if ('integer' == $this->raw_type) {
            $this->raw_type = 'int';
        }

        if (array_key_exists($this->raw_type, self::$TYPE_MAPPING)) {
            $this->type = self::$TYPE_MAPPING[$this->raw_type];
        } else {
            $this->type = self::STRING;
        }

        return $this->type;
    }
}
