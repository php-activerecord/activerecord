<?php

namespace test\models;

use ActiveRecord\Model;

class Course extends Model
{
    public static string $pk = 'course_id';
    public static array $has_and_belongs_to_many = [
        'students' => []
    ];
}
