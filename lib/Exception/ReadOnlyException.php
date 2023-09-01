<?php

namespace ActiveRecord\Exception;

/**
 * Thrown when attempting to perform a write operation on a {@link Model} that is in read-only mode.
 *
 * @package ActiveRecord
 */
class ReadOnlyException extends ModelException
{
    /**
     * Sets the exception message to show the undefined property's name.
     *
     * @param string $class_name  name of the model that is read only
     * @param string $method_name name of method which attempted to modify the model
     */
    public function __construct($class_name, $method_name)
    {
        $this->message = "{$class_name}::{$method_name}() cannot be invoked because this model is set to read only";
        parent::__construct();
    }
}
