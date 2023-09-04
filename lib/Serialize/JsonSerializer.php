<?php

namespace ActiveRecord\Serialize;

use ActiveRecord\Model;

/**
 * JSON serializer.
 *
 * @package ActiveRecord
 */
class JsonSerializer extends Serialization
{
    public function __construct(Model $model, $options)
    {
        parent::__construct($model, $options);
    }

    public function to_s(): string
    {
        $res = json_encode(!empty($this->options['include_root']) ? [strtolower(get_class($this->model)) => $this->to_a()] : $this->to_a());
        assert(is_string($res));

        return $res;
    }
}
