<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Table;
use test\models\Author;

class ActiveRecordFirstLastTest extends \DatabaseTestCase
{
    public function testFirstNoArguments()
    {
        $author = Author::first();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals(1, $author->author_id);
        $this->assertEquals('Tito', $author->name);
    }

    public function testFirstSingleArgument()
    {
        $authors = Author::first(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
        $this->assertEquals(1, $authors[0]->author_id);
        $this->assertEquals('Tito', $authors[0]->name);
    }

    public function testFirstWithCountOverridesLimit()
    {
        $authors = Author::limit(1)->first(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
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
        $this->assert_sql_includes('ORDER BY author_id ASC', Table::load(Author::class)->last_sql);
    }

    public function testFirstSortsBySuppliedOrder()
    {
        Author::order('name')->where(['author_id IN(?)', [1, 2, 3]])->first();
        $this->assert_sql_includes('ORDER BY name', Table::load(Author::class)->last_sql);
        $this->assert_sql_doesnt_has('ORDER BY author_id ASC', Table::load(Author::class)->last_sql);
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
    }

    public function testLast()
    {
        static::resetTableData();
        $author = Author::last();
        $this->assertInstanceOf(Author::class, $author);

        $author2 = Author::last(1);
        $this->assertIsArray($author2);
        $this->assertEquals($author2[0], $author);

        $authors = Author::all()->to_a();
        $this->assertEquals($author, $authors[count($authors)-1]);
    }

    public function testLastWithCount()
    {
        $allAuthors = Author::all()->to_a();
        $authors = Author::last(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
        $this->assertEquals($allAuthors[count($allAuthors)-1], $authors[0]);
        $this->assertEquals($allAuthors[count($allAuthors)-2], $authors[1]);
    }

    public function testLastWithCountOverridesLimit()
    {
        $authors = Author::limit(1)->last(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
    }

    public function testDoesNotClobberOrderOrLimit()
    {
        $rel = Author::limit(2)->order('author_id DESC');
        $this->assertEquals(Author::last(), $rel->first());
        $this->assertEquals(Author::first(), $rel->last());

        $authors = $rel->to_a();
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Tito', $authors[0]->name);
        $this->assertEquals('Uncle Bob', $authors[1]->name);
    }

    public function testLastNull()
    {
        $query = Author::where(['mixedCaseField' => 'Does not exist'])->last();
        $this->assertEquals(null, $query);
    }
}
