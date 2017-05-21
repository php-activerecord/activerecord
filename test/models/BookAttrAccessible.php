<?php

class BookAttrAccessible extends ActiveRecord\Model
{
    public static $pk = 'book_id';
    public static $table_name = 'books';

    public static $attr_accessible = ['author_id'];
    public static $attr_protected = ['book_id'];
}
