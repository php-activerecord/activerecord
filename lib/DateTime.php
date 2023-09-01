<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

/**
 * An extension of PHP's DateTime class to provide dirty flagging and easier formatting options.
 *
 * All date and datetime fields from your database will be created as instances of this class.
 *
 * Example of formatting and changing the default format:
 *
 * ```
 * $now = new ActiveRecord\DateTime('2010-01-02 03:04:05');
 * ActiveRecord\DateTime::$DEFAULT_FORMAT = 'short';
 *
 * echo $now->format();         # 02 Jan 03:04
 * echo $now->format('atom');   # 2010-01-02T03:04:05-05:00
 * echo $now->format('Y-m-d');  # 2010-01-02
 *
 * # __toString() uses the default formatter
 * echo (string)$now;           # 02 Jan 03:04
 * ```
 *
 * You can also add your own pre-defined friendly formatters:
 *
 * ```
 * ActiveRecord\DateTime::$FORMATS['awesome_format'] = 'H:i:s m/d/Y';
 * echo $now->format('awesome_format')  # 03:04:05 01/02/2010
 * ```
 *
 * @package ActiveRecord
 *
 * @phpstan-consistent-constructor
 *
 * @see http://php.net/manual/en/class.datetime.php
 */
final class DateTime extends \DateTime implements DateTimeInterface
{
    /**
     * Default format used for format() and __toString()
     */
    public static string $DEFAULT_FORMAT = 'rfc2822';

    /**
     * Pre-defined format strings.
     *
     * @var array<string, string>
     */
    public static array $FORMATS = [
        'db'      => 'Y-m-d H:i:s',
        'number'  => 'YmdHis',
        'time'    => 'H:i',
        'short'   => 'd M H:i',
        'long'    => 'F d, Y H:i',
        'atom'    => \DateTime::ATOM,
        'cookie'  => \DateTime::COOKIE,
        'iso8601' => \DateTime::ISO8601,
        'rfc822'  => \DateTime::RFC822,
        'rfc850'  => \DateTime::RFC850,
        'rfc1036' => \DateTime::RFC1036,
        'rfc1123' => \DateTime::RFC1123,
        'rfc2822' => \DateTime::RFC2822,
        'rfc3339' => \DateTime::RFC3339,
        'rss'     => \DateTime::RSS,
        'w3c'     => \DateTime::W3C];

    private Model|null $model = null;
    private string|null $attribute_name = null;

    public function attribute_of(Model $model, string $attribute_name): void
    {
        $this->model = $model;
        $this->attribute_name = $attribute_name;
    }

    /**
     * Formats the DateTime to the specified format.
     *
     * ```
     * $datetime->format();         # uses the format defined in DateTime::$DEFAULT_FORMAT
     * $datetime->format('short');  # d M H:i
     * $datetime->format('Y-m-d');  # Y-m-d
     * ```
     *
     * @see FORMATS
     * @see get_format
     *
     * @param string $format A format string accepted by get_format()
     *
     * @return string formatted date and time string
     */
    public function format(string $format = ''): string
    {
        return parent::format(self::get_format($format));
    }

    /**
     * Returns the format string.
     *
     * If $format is a pre-defined format in $FORMATS it will return that otherwise
     * it will assume $format is a format string itself.
     *
     * @see FORMATS
     *
     * @param string $format A pre-defined string format or a raw format string
     *
     * @return string a format string
     */
    public static function get_format(string $format = null): string
    {
        // use default format if no format specified
        if (!$format) {
            $format = self::$DEFAULT_FORMAT;
        }

        // format is a friendly
        if (array_key_exists($format, self::$FORMATS)) {
            return self::$FORMATS[$format];
        }

        // raw format
        return $format;
    }

    /**
     * This needs to be overridden so it returns an instance of this class instead of PHP's \DateTime.
     * See http://php.net/manual/en/datetime.createfromformat.php
     */
    public static function createFromFormat(string $format, string $time, \DateTimeZone $timezone = null): static
    {
        $phpDate = parent::createFromFormat($format, $time, $timezone);
        assert($phpDate);

        // convert to this class using the timestamp
        $ourDate = new static('', $phpDate->getTimezone());
        $ourDate->setTimestamp($phpDate->getTimestamp());

        return $ourDate;
    }

    public function __toString()
    {
        return $this->format();
    }

    /**
     * Handle PHP object `clone`.
     *
     * This makes sure that the object doesn't still flag an attached model as
     * dirty after cloning the DateTime object and making modifications to it.
     */
    public function __clone()
    {
        $this->model = null;
        $this->attribute_name = null;
    }

    private function flag_dirty(): void
    {
        $this->model?->flag_dirty($this->attribute_name);
    }

    public function setDate(int $year, int $month, int $day): DateTime
    {
        $this->flag_dirty();

        return parent::setDate($year, $month, $day);
    }

    public function setISODate(int $year, int $week, int $dayOfWeek = 1): DateTime
    {
        $this->flag_dirty();

        return parent::setISODate($year, $week, $dayOfWeek);
    }

    public function setTime(int $hour, int $minute, int $second = 0, int $microsecond = 0): DateTime
    {
        $this->flag_dirty();

        return parent::setTime($hour, $minute, $second);
    }

    public function setTimestamp(int $timestamp): DateTime
    {
        $this->flag_dirty();

        return parent::setTimestamp($timestamp);
    }

    public function setTimezone(\DateTimeZone $timezone): DateTime
    {
        $this->flag_dirty();

        return parent::setTimezone($timezone);
    }

    public function modify(string $modify = ''): DateTime|false
    {
        $this->flag_dirty();

        return parent::modify($modify);
    }

    public function add(\DateInterval $interval): DateTime
    {
        $this->flag_dirty();

        return parent::add($interval);
    }

    public function sub(\DateInterval $interval): DateTime
    {
        $this->flag_dirty();

        return parent::sub($interval);
    }
}
