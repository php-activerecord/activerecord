<?php

namespace test\models;

use ActiveRecord\Model;

class Book extends Model
{
    public static array $belongs_to = [
        'author' => true
    ];
    public static array $has_one = [];

    public function upper_name()
    {
        return strtoupper($this->name);
    }

    public function name()
    {
        return strtolower($this->name);
    }

    public function get_publisher()
    {
        return strtoupper($this->read_attribute('publisher'));
    }

    public function get_upper_name()
    {
        return strtoupper($this->name);
    }

    public function get_lower_name()
    {
        return strtolower($this->name);
    }
}
