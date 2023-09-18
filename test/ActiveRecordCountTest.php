<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;

class ActiveRecordCountTest extends \DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->assertEquals(5, Author::count());
    }

    public function testColumnNameAsArgument()
    {
        // tbd
        $this->expectNotToPerformAssertions();
    }

    public function testWithStringConditions()
    {
        $this->assertEquals(1, Author::where('author_id=1')->count());
    }

    public function testSelectIsNotClobberedByCount()
    {
        $authors = Author::select('name')->where('author_id=1');
        $this->assertEquals(1, $authors->count());

        $authors = $authors->to_a();

        $this->expectException(UndefinedPropertyException::class);
        $authors[0]->author_id;
    }

    public function testWithHashCondition()
    {
        $this->assertEquals(1, Author::where(['author_id' =>1])->count());
    }

    public function testWithArrayCondition()
    {
        $this->assertEquals(1, Author::where(['author_id = ?', 1])->count());
    }
}
