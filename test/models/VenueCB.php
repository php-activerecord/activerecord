<?php

class VenueCB extends ActiveRecord\Model
{
    public static $table_name = 'venues';
    public static $before_save;
    public static $before_update;
    public static $before_create;
    public static $before_validation;
    public static $before_destroy = 'before_destroy_using_string';
    public static $after_destroy = ['after_destroy_one', 'after_destroy_two'];
    public static $after_create;

    // DO NOT add a static $after_construct for this. we are testing
    // auto registration of callback with this
    public function after_construct()
    {
    }

    public function non_generic_after_construct()
    {
    }

    public function after_destroy_one()
    {
    }

    public function after_destroy_two()
    {
    }

    public function before_destroy_using_string()
    {
    }

    public function before_update_halt_execution()
    {
        return false;
    }

    public function before_destroy_halt_execution()
    {
        return false;
    }

    public function before_create_halt_execution()
    {
        return false;
    }

    public function before_validation_halt_execution()
    {
        return false;
    }
}
