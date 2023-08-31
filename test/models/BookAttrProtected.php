<?php
namespace test\models;

use ActiveRecord\Model;
class BookAttrProtected extends Model
{
    public static $pk = 'book_id';
    public static $table_name = 'books';
    public static $belongs_to = [
        ['author', 'class_name' => 'AuthorAttrAccessible', 'primary_key' => 'author_id']
    ];

    // No attributes should be accessible
    public static $attr_accessible = [null];
}
