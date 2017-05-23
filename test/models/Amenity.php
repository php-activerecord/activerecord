<?php

class Amenity extends ActiveRecord\Model
{
    public static $table_name = 'amenities';
    public static $primary_key = 'amenity_id';

    public static $has_many = [
        'property_amenities'
    ];
}
