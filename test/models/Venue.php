<?php

class Venue extends ActiveRecord\Model
{
    public static $use_custom_get_state_getter = false;
    public static $use_custom_set_state_setter = false;

    public static $has_many = [
        'events',
        ['hosts', 'through' => 'events']
    ];

    public static $has_one;

    public static $alias_attribute = [
        'marquee' => 'name',
        'mycity' => 'city'
    ];

    public function get_state()
    {
        if (self::$use_custom_get_state_getter) {
            return strtolower($this->read_attribute('state'));
        }

        return $this->read_attribute('state');
    }

    public function set_state($value)
    {
        if (self::$use_custom_set_state_setter) {
            return $this->assign_attribute('state', $value . '#');
        }

        return $this->assign_attribute('state', $value);
    }
}
