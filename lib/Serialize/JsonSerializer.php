<?php

namespace ActiveRecord\Serialize;

/**
 * JSON serializer.
 *
 * @package ActiveRecord
 */
class JsonSerializer extends ArraySerializer
{
    public static $include_root = false;

    public function to_s()
    {
        parent::$include_root = self::$include_root;

        return json_encode(parent::to_s());
    }
}
