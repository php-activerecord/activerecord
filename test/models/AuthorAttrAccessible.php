<?php

class AuthorAttrAccessible extends ActiveRecord\Model
{
    public static $pk = 'author_id';
    public static $table_name = 'authors';
    public static $has_many = [
        ['books', 'class_name' => 'BookAttrProtected', 'foreign_key' => 'author_id', 'primary_key' => 'book_id']
    ];
    public static $has_one = [
        ['parent_author', 'class_name' => 'AuthorAttrAccessible', 'foreign_key' => 'parent_author_id', 'primary_key' => 'author_id']
    ];
    public static $belongs_to = [];

    // No attributes should be accessible
    public static $attr_accessible = [null];
}
