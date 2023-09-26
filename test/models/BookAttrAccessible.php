<?php

namespace test\models;

use ActiveRecord\Model;

class BookAttrAccessible extends Model
{
    public static string $pk = 'book_id';
    public static string $table_name = 'books';

    public static array $attr_accessible = ['author_id'];
}
