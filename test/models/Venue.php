<?php

namespace test\models;

use ActiveRecord\Model;

class Venue extends Model
{
    public static bool $use_custom_get_state_getter = false;
    public static bool $use_custom_set_state_setter = false;

    public static array $has_many = [
        'events' => true,
        'hosts' => [
            'through' => 'events'
        ]
    ];

    public static array $has_one;

    public static array $alias_attribute = [
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

    public function get_customState()
    {
        static::$use_custom_get_state_getter = true;

        return $this->get_state();
    }
}
