<?php
namespace test\models;

use ActiveRecord\Model;
class Event extends Model
{
    public static $belongs_to = [
        'host',
        'venue'
    ];

    public static $delegate = [
        ['state', 'address', 'to' => 'venue'],
        ['name', 'to' => 'host', 'prefix' => 'woot']
    ];
}
