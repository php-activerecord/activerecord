<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;

class ActiveRecordFirstTest extends \DatabaseTestCase
{
    public function testFindNothingWithSqlInString()
    {
        $this->expectException(TypeError::class);
        Author::first('name = 123123123');
    }

    public function testFirstNoArguments()
    {
        $author = Author::first();
        $this->assertEquals(1, $author->author_id);
        $this->assertEquals('Tito', $author->name);
    }

    public function testFirstSingleArgument()
    {
        $authors = Author::first(1);
        $this->assertIsArray($authors);
        $this->assertEquals(1, $authors[0]->author_id);
        $this->assertEquals('Tito', $authors[0]->name);
    }

    public function testFirstChainedFromWhere()
    {
        $author = Author::where(['author_id IN(?)', [2, 3]])->first();
        $this->assertEquals(2, $author->author_id);
        $this->assertEquals('George W. Bush', $author->name);
    }

    public function testFirstSortsByPkByDefault()
    {
        Author::where(['author_id IN(?)', [1, 2, 3]])->first();
        $this->assert_sql_has('ORDER BY author_id ASC', Author::table()->last_sql);
    }

    public function testFirstSortsBySuppliedOrder()
    {
        Author::order('name')->where(['author_id IN(?)', [1, 2, 3]])->first();
        $this->assert_sql_has('ORDER BY name', Author::table()->last_sql);
        $this->assert_sql_doesnt_has('ORDER BY author_id ASC', Author::table()->last_sql);
    }

    public function testFirstNoResults()
    {
        $this->assertNull(Author::where('author_id=1111111')->first());
    }

    public function testFindFirstWithConditionsAsString()
    {
        $author = Author::where('author_id=3')->first();
        $this->assertEquals(3, $author->author_id);
    }

    public function testFirstWithConditions()
    {
        $author = Author::where(['author_id=? and name=?', 1, 'Tito'])->first();
        $this->assertEquals(1, $author->author_id);
    }

    public function testFirstWithSelectNonSelectedFieldsShouldNotHaveAttributes()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::select('name, 123 as bubba')->first();
        $author->id;
        $this->fail('expected ActiveRecord\UndefinedPropertyExecption');
    }
}
