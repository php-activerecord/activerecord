<?php
namespace test\models;

use ActiveRecord\Model;
class PropertyAmenity extends Model
{
    public static $table_name = 'property_amenities';
    public static $primary_key = 'id';

    public static $belongs_to = [
        'amenity',
        'property'
    ];
}
