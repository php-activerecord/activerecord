<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

/**
 * Interface for the ActiveRecord\DateTime class so that ActiveRecord\Model->assign_attribute() will
 * know to call attribute_of() on passed values. This is so the DateTime object can flag the model
 * as dirty via $model->flag_dirty() when one of its setters is called.
 *
 * @package ActiveRecord
 *
 * @see http://php.net/manual/en/class.datetime.php
 */
interface DateTimeInterface
{
    /**
     * Indicates this object is an attribute of the specified model, with the given attribute name.
     */
    public function attribute_of(Model $model, string $attribute_name): void;

    /**
     * Formats the DateTime to the specified format.
     */
    public function format(string $format = ''): string;

    /**
     * See http://php.net/manual/en/datetime.createfromformat.php
     */
    public static function createFromFormat(string $format, string $time, \DateTimeZone $timezone = null): static;
}
