<?php

namespace test\models;

use ActiveRecord\Model;

class Worker extends Model
{
    public static array $has_and_belongs_to_many = [
        'tasks' => []
    ];
}
