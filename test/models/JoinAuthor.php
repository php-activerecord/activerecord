<?php

namespace test\models;

use ActiveRecord\Model;

class JoinAuthor extends Model
{
    public static string $table_name = 'authors';
    public static string $pk = 'author_id';
}
