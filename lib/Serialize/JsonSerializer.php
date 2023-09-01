<?php

namespace ActiveRecord\Serialize;

/**
 * JSON serializer.
 *
 * @package ActiveRecord
 */
class JsonSerializer extends Serialization
{
    public function to_s(): string
    {
        $res = !empty($this->options['include_root']) ? [strtolower(get_class($this->model)) => $this->to_a()] : $this->to_a();

        return json_encode($res);
    }
}
