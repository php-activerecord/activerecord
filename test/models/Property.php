<?php

namespace test\models;

use ActiveRecord\Model;

class Property extends Model
{
    public static string $table_name = 'property';
    public static string $primary_key = 'property_id';

    public static array $has_many = [
        'property_amenities' => true,
        'amenities' => [
            'through' => 'property_amenities'
        ]
    ];
}
