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
     * @var array
     */
    private $reflections = [];

    /**
     * Instantiates a new ReflectionClass for the given class.
     *
     * @param string $class Name of a class
     *
     * @return Reflections $this so you can chain calls like Reflections::instance()->add('class')->get()
     */
    public function add($class=null)
    {
        $class = $this->get_class($class);

        if (!isset($this->reflections[$class])) {
            $this->reflections[$class] = new ReflectionClass($class);
        }

        return $this;
    }

    /**
     * Destroys the cached ReflectionClass.
     *
     * Put this here mainly for testing purposes.
     *
     * @param string $class name of a class
     */
    public function destroy($class)
    {
        if (isset($this->reflections[$class])) {
            $this->reflections[$class] = null;
        }
    }

    /**
     * Get a cached ReflectionClass.
     *
     * @param string|object $class Optional name of a class or an instance of the class
     *
     * @throws ActiveRecordException if class was not found
     *
     * @return mixed null or a ReflectionClass instance
     */
    public function get(string $className=null)
    {
        if (isset($this->reflections[$className])) {
            return $this->reflections[$className];
        }

        throw new ActiveRecordException("Class not found: $className");
    }

    /**
     * Retrieve a class name to be reflected.
     *
     * @param mixed string|object An object or name of a class
     *
     * @return string
     */
    private function get_class(string|object $mixed=null)
    {
        if (is_object($mixed)) {
            return get_class($mixed);
        }

        if (!is_null($mixed)) {
            return $mixed;
        }

        return $this->get_called_class();
    }
}
