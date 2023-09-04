<?php

namespace test\models;

use ActiveRecord\Model;

class Host extends Model
{
    public static array $has_many = [
        'events' => true,
        'venues' => [
            'through' => 'events'
        ]
    ];
}
