<?php

namespace test\models;

use ActiveRecord\Model;

class BookAttrProtected extends Model
{
    //    public static string $pk = 'book_id';
    public static string $table_name = 'books';
    public static array $belongs_to = [
        'author' => [
            'class_name' => 'AuthorAttrAccessible',
            'primary_key' => 'author_id'
        ]
    ];

    // No attributes should be accessible
    public static array $attr_protected = ['book_id'];
}
