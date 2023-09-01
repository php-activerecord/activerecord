<?php

namespace ActiveRecord\Exception;

use ActiveRecord\Connection;

/**
 * Thrown by {@link Expressions}.
 *
 * @package ActiveRecord
 */
class ConnectionException extends ActiveRecordException
{
    public function __construct(Connection $connection)
    {
        parent::__construct(
            join(', ', $connection->connection->errorInfo()),
            intval($connection->connection->errorCode()));
    }
}
