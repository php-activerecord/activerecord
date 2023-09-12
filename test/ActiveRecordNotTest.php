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
            ]
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
}
