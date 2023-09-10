<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/RelationMethodReflection.php
 */

use ActiveRecord\Relation;
use test\models\Book;
use function PHPStan\dumpType;

/**
 * @var Relation<Book> $rel
 */
$rel = new Relation(Book::class, [], []);

$book = $rel->first();
assert($book instanceof Book);

$books = $rel->first(1);
assert(is_array($books));
