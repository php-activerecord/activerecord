<?php

namespace NamespaceTest\SubNamespaceTest;

class Page extends \ActiveRecord\Model
{
    public static $belong_to = [
        ['book', 'class_name' => '\NamespaceTest\Book'],
    ];
}
