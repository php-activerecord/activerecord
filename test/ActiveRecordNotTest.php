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
                "WHERE name = 'Another Book'",
                "WHERE !(name = 'Another Book')"
            ],
            'array' => [[
                  'name = ?', 'Another Book',
                ],
                'WHERE name = ?',
                'WHERE !(name = ?)'
            ],
            'hash' => [[
                    'name' => 'Another Book',
                ],
                'WHERE `name` = ?',
                'WHERE !(`name` = ?)'
            ],
            'in' => [[
                'book_id in (?)', [1, 2],
              ],
              'WHERE book_id in (?,?)',
              'WHERE !(book_id in (?,?))',
           ],
        ];
    }

    /**
     * @dataProvider conditions
     */
    public function testString($condition, $expectedWhere, $expectedNotWhere)
    {
        Book::where($condition)->to_a();
        $this->assertStringContainsString($expectedWhere, Book::table()->last_sql);

        Book::not($condition)->to_a();
        $this->assertStringContainsString($expectedNotWhere, Book::table()->last_sql);
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

    public function testRelation()
    {
        $books = Book::not(Book::where('book_id in (?)', [1]))->to_a();
        $this->assertEquals(1, count($books));
        $this->assertEquals('Another Book', $books[0]->name);
    }

    public function testRelationAndNot()
    {
        $books = Book::not(Book::where('book_id in (?)', [1])->where('name = ?', 'Ancient Art of Main Tanking'))->to_a();
        $this->assertEquals(1, count($books));
        $this->assertEquals('Another Book', $books[0]->name);
    }
}
