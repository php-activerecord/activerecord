<?php

class Event extends ActiveRecord\Model
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
