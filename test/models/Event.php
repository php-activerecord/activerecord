<?php
namespace test\models;

use ActiveRecord\Model;
class Event extends Model
{
    public static array $belongs_to = [
        'host',
        'venue'
    ];

    public static array $delegate = [
        ['state', 'address', 'to' => 'venue'],
        ['name', 'to' => 'host', 'prefix' => 'woot']
    ];
}
