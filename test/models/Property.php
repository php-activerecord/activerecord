<?php
namespace test\models;

use ActiveRecord\Model;
class Property extends Model
{
    public static $table_name = 'property';
    public static $primary_key = 'property_id';

    public static $has_many = [
        'property_amenities',
        ['amenities', 'through' => 'property_amenities']
    ];
}
