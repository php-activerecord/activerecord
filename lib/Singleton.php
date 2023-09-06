<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

/**
 * This implementation of the singleton pattern does not conform to the strong definition
 * given by the "Gang of Four." The __construct() method has not be privatized so that
 * a singleton pattern is capable of being achieved; however, multiple instantiations are also
 * possible. This allows the user more freedom with this pattern.
 *
 * @package ActiveRecord
 */
abstract class Singleton
{
    /**
     * Array of cached singleton objects.
     *
     * @var array<string, Singleton>
     */
    private static array $instances = [];

    /**
     * Static method for instantiating a singleton object.
     */
    final public static function instance(): static
    {
        $class_name = get_called_class();

        /*
         * TODO: the proper way to prepare this for static checking
         * with PHPStan is to write a custom extension.
         *
         * @phpstan-ignore-next-line
         */
        return self::$instances[$class_name] ??= new $class_name();
    }
}
