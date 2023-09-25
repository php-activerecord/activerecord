<?php

namespace test\models;

use ActiveRecord\Model;

class AuthorAttrAccessible extends Model
{
    public static string $pk = 'author_id';
    public static string $table_name = 'authors';
    public static array $has_many = [
        'books' => [
            'class_name' => 'BookAttrProtected',
            'foreign_key' => 'author_id',
            'primary_key' => 'book_id'
        ]
    ];
    public static array $has_one = [
        'parent_author' => [
            'class_name' => 'AuthorAttrAccessible',
            'foreign_key' => 'parent_author_id',
            'primary_key' => 'author_id'
        ]
    ];
    public static array $belongs_to = [];

    // No attributes should be accessible
    public static array $attr_accessible = [null];
}
