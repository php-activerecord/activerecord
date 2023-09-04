<?php

namespace foo\bar\biz;

use ActiveRecord\Model;

class User extends Model
{
    public static array $has_many = [
        'user_newsletters' => true,
        'newsletters' => [
            'through' => 'user_newsletters'
        ]
    ];
}

class Newsletter extends Model
{
    public static array $has_many = [
        'user_newsletters'=>true,
        'users' =>[
            'through' => 'user_newsletters'
        ],
    ];
}

class UserNewsletter extends Model
{
    public static $belong_to = [
        ['user'],
        ['newsletter'],
    ];
}

// vim: ts=4 noet nobinary
