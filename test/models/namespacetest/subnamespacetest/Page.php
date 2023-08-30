<?php

namespace test\models\namespacetest\subnamespacetest;

use ActiveRecord\Model;

class Page extends Model
{
    public static $belong_to = [
        ['book', 'class_name' => '\NamespaceTest\Book'],
    ];
}
