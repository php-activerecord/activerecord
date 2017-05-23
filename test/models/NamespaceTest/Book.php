<?php

namespace NamespaceTest;

class Book extends \ActiveRecord\Model
{
    public static $belongs_to = [
        ['parent_book', 'class_name' => '\NamespaceTest\Book'],
        ['parent_book_2', 'class_name' => 'Book'],
        ['parent_book_3', 'class_name' => '\Book'],
    ];

    public static $has_many = [
        ['pages', 'class_name' => '\NamespaceTest\SubNamespaceTest\Page'],
        ['pages_2', 'class_name' => 'SubNamespaceTest\Page'],
    ];
}
