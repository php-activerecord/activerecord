<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ActiveRecordException;
use ReflectionClass;

/**
 * Simple class that caches reflections of classes.
 *
 * @package ActiveRecord
 */
class Reflections extends Singleton
{
    /**
     * Current reflections.
     *
     * @var array<string, \ReflectionClass<Model>>
     */
    private $reflections = [];

    /**
     * Instantiates a new ReflectionClass for the given class.
     *
     * @param class-string $class Name of a class
     *
     * @return Reflections $this so you can chain calls like Reflections::instance()->add('class')->get()
     */
    public function add(string $class): Reflections
    {
        if (!isset($this->reflections[$class])) {
            /* @phpstan-ignore-next-line */
            $this->reflections[$class] = new \ReflectionClass($class);
        }

        return $this;
    }

    /**
     * Get a cached ReflectionClass.
     *
     * @param class-string $className Optional name of a class or an instance of the class
     *
     * @throws ActiveRecordException if class was not found
     */
    public function get(string $className): \ReflectionClass
    {
        return $this->reflections[$className] ?? throw new ActiveRecordException("Class not found: $className");
    }
}
