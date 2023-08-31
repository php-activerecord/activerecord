<?php

namespace test\models\namespacetest;

class Book extends \ActiveRecord\Model
{
    public static array $belongs_to = [
        ['parent_book', 'class_name' => 'Book'],
        ['parent_book_2', 'class_name' => '\test\models\namespacetest\Book'],
        ['parent_book_3', 'class_name' => '\test\models\Book'],
    ];

    public static $has_many = [
        ['pages', 'class_name' => '\test\models\namespacetest\subnamespacetest\Page'],
        ['pages_2', 'class_name' => 'subnamespacetest\Page'],
    ];
}
