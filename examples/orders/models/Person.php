<?php

class Person extends ActiveRecord\Model
{
    // a person can have many orders and payments
    public static array $has_many = [
        'orders' => true,
        'payments'=> true
    ];

    // must have a name and a state
    public static array $validates_presence_of = [
        'name' => true,
        'state' => true
    ];
}
