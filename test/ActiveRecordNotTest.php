<?php

namespace test;

use test\models\Book;

class ActiveRecordNotTest extends \DatabaseTestCase
{
    public static function conditions(): array
    {
        return [
            'string' => [
                "name = 'Another Book'",
                static fn ($book) => 'AnotherBook' != $book->name
            ],
            'array' => [
                [
                  'name = ?', 'Another Book',
                ],
                static fn ($book) => 'AnotherBook' != $book->name
            ],
            'hash' => [[
                    'name' => 'Another Book',
                ],
                static fn ($book) => 'AnotherBook' != $book->name
            ],
            'in' => [[
                'book_id in (?)', [1, 2],
              ],
              static fn ($book) => 1 != $book->name && 2 != $book->name
           ],
        ];
    }

    /**
     * @dataProvider conditions
     */
    public function testString($condition, $cb)
    {
        foreach (Book::where($condition) as $book) {
            $this->assertTrue($cb($book));
        }
    }

    public function testListOfArguments()
    {
        $book = Book::where('name = ?', 'Another Book')->take();
        $this->assertEquals('Another Book', $book->name);
    }

    public function testIn()
    {
        $books = Book::not('book_id in (?)', [1])->to_a();
        $this->assertEquals(1, count($books));
        $this->assertEquals('Another Book', $books[0]->name);
    }
}
