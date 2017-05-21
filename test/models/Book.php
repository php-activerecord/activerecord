<?php

class Book extends ActiveRecord\Model
{
    public static $belongs_to = ['author'];
    public static $has_one = [];
    public static $use_custom_get_name_getter = false;

    public function upper_name()
    {
        return strtoupper($this->name);
    }

    public function name()
    {
        return strtolower($this->name);
    }

    public function get_name()
    {
        if (self::$use_custom_get_name_getter) {
            return strtoupper($this->read_attribute('name'));
        }

        return $this->read_attribute('name');
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
