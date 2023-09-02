<?php
namespace test\models;

use ActiveRecord\Model;

class Book extends Model
{
    public static array $belongs_to = ['author'];
    public static $has_one = [];
    public function upper_name()
    {
        return strtoupper($this->name); // keep?
    }

    public function name()
    {
        return strtolower($this->name); // keep
    }

    public function get_publisher()
    {
        return strtoupper($this->read_attribute('publisher')); // keep
    }

    public function get_upper_name()
    {
        return strtoupper($this->name); // keep
    }

    public function get_lower_name()
    {
        return strtolower($this->name);
    }
}
