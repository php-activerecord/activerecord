<?php

namespace test\models\namespacetest;

use test\models\namespacetest\subnamespacetest\Page;

class Book extends \ActiveRecord\Model
{
    public static array $belongs_to = [
        'parent_book' => [
            'class_name' => 'Book'
        ],
        'parent_book_2' => [
            'class_name' => Book::class
        ],
        'parent_book_3' => [
            'class_name' => \test\models\Book::class
        ],
    ];

    public static array $has_many = [
        'pages' => [
            'class_name' => Page::class
        ],
        'pages_2' => [
            'class_name' => 'subnamespacetest\Page'
        ],
    ];
}
