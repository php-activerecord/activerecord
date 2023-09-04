<?php

namespace test\models;

use ActiveRecord\Model;

class PropertyAmenity extends Model
{
    public static string $table_name = 'property_amenities';
    public static string $primary_key = 'id';

    public static array $belongs_to = [
        'amenity' => true,
        'property' => true
    ];
}
