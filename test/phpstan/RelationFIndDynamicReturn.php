<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/RelationDynamicMethodReturnTypeReflection.php
 */

use test\models\Book;

class RelationFindDynamicReturn
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
        $this->book = Book::All()->find(1);
        $this->books = Book::All()->find([1, 2]);
    }
}
