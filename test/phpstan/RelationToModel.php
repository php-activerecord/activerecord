<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to confirm that Relation methods are set up with generics
 * and working properly.
 */

use test\models\Book;

class RelationToModel
{
    protected Book $book;

    public function bookFromFirst(): Book|null
    {
        return Book::all()->first();
    }

    public function bookFromLast(): Book|null
    {
        return Book::all()->last();
    }

    /**
     * @return array<Book>
     */
    public function booksFromFirst(): array
    {
        return Book::all()->first(1);
    }

    /**
     * @return array<Book>
     */
    public function booksFromLast(): array
    {
        return Book::all()->last(1);
    }

    /**
     * @return array<Book>
     */
    public function booksFromToA(): array
    {
        return Book::all()->to_a();
    }

    public function bookFromTake(): Book|null
    {
        return Book::all()->take();
    }

    /**
     * @return array<Book>
     */
    public function booksFromTake(): array
    {
        return Book::all()->take(1);
    }

    public function booksIterator(): void
    {
        $books = Book::all();
        foreach ($books as $book) {
            assert($book instanceof Book);
        }
    }
}
