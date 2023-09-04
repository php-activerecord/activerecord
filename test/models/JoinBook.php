<?php

namespace test\models;

use ActiveRecord\Model;

class JoinBook extends Model
{
    public static string $table_name = 'books';

    public static array $belongs_to = [];
}
