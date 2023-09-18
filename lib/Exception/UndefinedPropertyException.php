<?php

namespace ActiveRecord\Exception;

/**
 * Thrown when attempting to access an invalid property on a {@link Model}.
 *
 * @package ActiveRecord
 */
class UndefinedPropertyException extends ModelException
{
    /**
     * Sets the exception message to show the undefined property's name.
     *
     * @param string              $class_name    name of the class with the missing property
     * @param string|list<string> $property_name name of undefined property
     */
    public function __construct(string $class_name, string|array $property_name)
    {
        if (is_array($property_name)) {
            $this->message = implode("\r\n", $property_name);

            return;
        }

        $this->message = "Undefined property: {$class_name}->{$property_name} in {$this->file} on line {$this->line}";
        parent::__construct();
    }
}
