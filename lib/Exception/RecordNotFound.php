<?php

namespace ActiveRecord\Exception;

/**
 * Thrown when a record cannot be found.
 *
 * @package ActiveRecord
 */
class RecordNotFound extends ActiveRecordException
{

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
