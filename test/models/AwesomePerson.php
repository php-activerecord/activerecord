<?php

namespace test\models;

use ActiveRecord\Model;

class AwesomePerson extends Model
{
    public static array $belongs_to = [
        'author' => true
    ];
}
