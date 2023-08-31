<?php
namespace test\models;

use ActiveRecord\Model;

class Book extends Model
{
    public static array $belongs_to = ['author'];
    public static $has_one = [];
    public static $use_custom_get_name_getter = false;

    public function upper_name()
    {
        return strtoupper($this->name);
    }

    public function name()
    {
        return $this->name;
    }

    public function get_name()
    {
        return $this->read_attribute('name');
    }
}
