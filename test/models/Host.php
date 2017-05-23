<?php

class Host extends ActiveRecord\Model
{
    public static $has_many = [
        'events',
        ['venues', 'through' => 'events']
    ];
}
