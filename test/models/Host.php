<?php
namespace test\models;

use ActiveRecord\Model;
class Host extends Model
{
    public static $has_many = [
        'events',
        ['venues', 'through' => 'events']
    ];
}
