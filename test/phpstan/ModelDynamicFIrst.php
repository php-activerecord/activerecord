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
$book = Book::first();
assert($book instanceof Book);

$book = Book::all()->first();
assert($book instanceof Book);

/**
 * Static checking for single model
 */
$books = Book::first(1);
assert(is_array($books));
