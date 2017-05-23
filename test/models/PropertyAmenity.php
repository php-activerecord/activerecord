<?php

class PropertyAmenity extends ActiveRecord\Model
{
    public static $table_name = 'property_amenities';
    public static $primary_key = 'id';

    public static $belongs_to = [
        'amenity',
        'property'
    ];
}
