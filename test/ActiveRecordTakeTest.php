<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;

class ActiveRecordTakeTest extends \DatabaseTestCase
{
    public function testNoArguments()
    {
        $author = Author::take();
        $this->assertEquals(1, $author->author_id);
        $this->assertEquals('Tito', $author->name);
    }

    public function testSingleArgument()
    {
        $authors = Author::take(1);
        $this->assertIsArray($authors);
        $this->assertEquals(1, $authors[0]->author_id);
        $this->assertEquals('Tito', $authors[0]->name);
    }

    public function testChainedFromWhere()
    {
        $author = Author::where(['author_id IN(?)', [2, 3]])->take();
        $this->assertEquals(2, $author->author_id);
        $this->assertEquals('George W. Bush', $author->name);
    }

    public function testNoImplicitOrder()
    {
        Author::where(['author_id IN(?)', [1, 2, 3]])->take();
        $this->assert_sql_doesnt_has('ORDER BY', Author::table()->last_sql);
    }

    public function testSortsBySuppliedOrder()
    {
        Author::order('name')->where(['author_id IN(?)', [1, 2, 3]])->take();
        $this->assert_sql_has('ORDER BY name', Author::table()->last_sql);
        $this->assert_sql_doesnt_has('ORDER BY author_id ASC', Author::table()->last_sql);
    }

    public function testNoResults()
    {
        $this->assertNull(Author::where('author_id=1111111')->take());
    }

    public function testWithConditionsAsString()
    {
        $author = Author::where('author_id=3')->take();
        $this->assertEquals(3, $author->author_id);
    }

    public function testWithConditions()
    {
        $author = Author::where(['author_id=? and name=?', 1, 'Tito'])->take();
        $this->assertEquals(1, $author->author_id);
    }

    public function testWithSelectNonSelectedFieldsShouldNotHaveAttributes()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::select('name, 123 as bubba')->take();
        $author->id;
    }
}
