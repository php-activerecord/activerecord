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
        /**
         * @var non-empty-array<int>
         */
        $ids = [];

        $this->book = Book::All()->find(1);
        $this->books = Book::All()->find([1, 2]);
        $this->books = Book::All()->find($ids);

        $books = Book::find($ids);
        $this->books = $books;

        $mixed = 1;
        $books = Book::find($mixed);
        $this->book = $books;
    }
}
