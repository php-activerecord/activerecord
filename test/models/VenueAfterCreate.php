<?php

namespace test\models;

use ActiveRecord\Model;

class VenueAfterCreate extends Model
{
    public static string $table_name = 'venues';
    public static $after_create = ['change_name_after_create_if_name_is_change_me'];

    public function change_name_after_create_if_name_is_change_me()
    {
        if ('change me' == $this->name) {
            $this->name = 'changed!';
            $this->save();
        }
    }
}
