<?php

namespace test\models;

use ActiveRecord\Model;

class Student extends Model
{
    public static string $pk = 'student_id';
    public static array $has_and_belongs_to_many = [
        'courses'=>[]
    ];
}
