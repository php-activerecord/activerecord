<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/FindDynamicAllMethodReturnTypeReflection.php
 */

use test\models\Book;

$book = Book::find_all_by_name('Foo');
assert(is_array($book));

$book = Book::find_all_by_name_and_publisher('Foo', 'Penguin');
assert(is_array($book));
