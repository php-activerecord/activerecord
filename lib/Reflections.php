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
    public function add(string $class = null): Reflections
    {
        $class = $this->get_class($class);

        if (!isset($this->reflections[$class])) {
            $this->reflections[$class] = new \ReflectionClass($class);
        }

        return $this;
    }

    /**
     * Destroys the cached ReflectionClass.
     *
     * Put this here mainly for testing purposes.
     *
     * @param class-string $class name of a class
     */
    public function destroy(string $class): void
    {
        if (isset($this->reflections[$class])) {
            $this->reflections[$class] = null;
        }
    }

    /**
     * Get a cached ReflectionClass.
     *
     * @param class-string|object $className Optional name of a class or an instance of the class
     *
     * @throws ActiveRecordException if class was not found
     *
     * @return mixed null or a ReflectionClass instance
     */
    public function get($className)
    {
        if (isset($this->reflections[$className])) {
            return $this->reflections[$className];
        }

        throw new ActiveRecordException("Class not found: $className");
    }

    /**
     * Retrieve a class name to be reflected.
     *
     * @param class-string|object $class An object or name of a class
     *
     * @return string
     */
    private function get_class(string|object $class = null)
    {
        if (is_object($class)) {
            return get_class($class);
        }

        if (!is_null($class)) {
            return $class;
        }

        return $this->get_called_class();
    }
}
