<?php

namespace test\models;

use ActiveRecord\Model;

class Task extends Model
{
    public static array $has_and_belongs_to_many = [
        'workers' => []
    ];
}
