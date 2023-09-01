<?php

namespace ActiveRecord\Exception;

use ActiveRecord\Connection;

/**
 * Thrown by {@link Expressions}.
 *
 * @package ActiveRecord
 */
class PdoException extends ActiveRecordException
{
    public function __construct(\PDOStatement $pdo)
    {
        parent::__construct(
            join(', ', $pdo->errorInfo()),
            intval($pdo->errorCode()));
    }
}
