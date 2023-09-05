<?php

namespace test\models;

use ActiveRecord\Model;

class Event extends Model
{
    public static array $belongs_to = [
        'host' => true,
        'venue' => true
    ];

    public static array $delegate = [
        [
            'delegate'=>[
                'state',
                'address'
            ],
            'to' => 'venue'
        ],
        [
            'delegate'=>[
                'name'
            ],
            'to' => 'host',
            'prefix' => 'woot'
        ]
    ];
}
