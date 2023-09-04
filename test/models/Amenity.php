<?php

namespace test\models;

use ActiveRecord\Model;

class Amenity extends Model
{
    public static string $table_name = 'amenities';
    public static string $primary_key = 'amenity_id';

    public static array $has_many = [
        'property_amenities' => true
    ];
}
