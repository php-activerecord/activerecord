<?php

namespace test;

use ActiveRecord\Exception\DatabaseException;
use test\models\Author;
use test\models\Venue;

class ActiveRecordFromTest extends \DatabaseTestCase
{
    public function testFrom()
    {
        $author = Author::from('books')
            ->order('author_id asc')
            ->first();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertNotNull($author->book_id);

        $author = Author::from('authors')
            ->order('author_id asc')
            ->first();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals(1, $author->id);
    }

    public function testFromWithInvalidTable()
    {
        $this->expectException(DatabaseException::class);
        Author::from('wrong_authors_table')->first();
    }

    public function testSimpleTableName(): void
    {
        $venues = Venue::from('events');
        $this->assert_sql_includes('FROM events', $venues->to_sql());

        $venue = $venues->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->title);
    }

    public function testAlias(): void
    {
        $venues = Venue::from('events as old_events');
        $this->assert_sql_includes('FROM events as old_events', $venues->to_sql());

        $venue = $venues->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->title);
    }
}
