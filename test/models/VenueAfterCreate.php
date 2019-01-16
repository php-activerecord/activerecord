<?php

class VenueAfterCreate extends ActiveRecord\Model
{
    public static $table_name = 'venues';
    public static $after_create = ['change_name_after_create_if_name_is_change_me'];

    public function change_name_after_create_if_name_is_change_me()
    {
        if ('change me' == $this->name) {
            $this->name = 'changed!';
            $this->save();
        }
    }
}
