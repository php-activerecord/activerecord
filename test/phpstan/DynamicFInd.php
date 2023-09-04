<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/FindDynamicMethodReturnTypeReflection.php
 */

use test\models\Book;

/**
 * Static checking for single model
 */
$book = Book::find(1);
assert($book instanceof Book);

$book = Book::find(['name'=>'Ubik']);
assert($book instanceof Book);

/**
 * Static checking for nullable single model
 */
$book = Book::find('first', ['name'=>'Waldo']);
assert(is_null($book));

$book = Book::find('last', ['name'=>'Waldo']);
assert(is_null($book));

/**
 * Static checking for array of models
 */
$books = Book::find(
    'all',
    ['name' => 'William']
);
assert(0==count($books));

$books = Book::find(1, 3, 8);
assert(0==count($books));

$books = Book::find([1, 3, 8]);
assert(0==count($books));
