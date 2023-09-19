<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/ModelStaticMethodReflection.php
 */

use test\models\Book;

$book = Book::find_by_name('Walden');
assert($book instanceof Book);

$book = Book::find_by_name_and_publisher('Walden', 'Random House');
assert(is_null($book));
