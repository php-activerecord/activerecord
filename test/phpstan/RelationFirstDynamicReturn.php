<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/RelationDynamicMethodReturnTypeReflection.php
 */

use ActiveRecord\Relation;
use test\models\Book;

class RelationFirstDynamicReturn
{
    /**
     * @var Book|null
     */
    protected $book;

    /**
     * @var array<Book>
     */
    protected $books;

    public function __construct()
    {
        /**
         * @var Relation<Book> $rel
         */
        $rel = new Relation(Book::class, [], []);

        $this->book = $rel->first();
        $this->books = $rel->first(1);
    }
}
