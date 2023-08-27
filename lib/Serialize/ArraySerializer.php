<?php

namespace ActiveRecord\Serialize;

/**
 * Array serializer.
 *
 * @package ActiveRecord
 */
class ArraySerializer extends Serialization
{
    public static $include_root = false;

    public function to_s()
    {
        return self::$include_root ? [strtolower(get_class($this->model)) => $this->to_a()] : $this->to_a();
    }
}
