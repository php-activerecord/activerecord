<?php

namespace test;

use test\models\Author;

class ActiveRecordCountTest extends \DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->assertEquals(4, Author::count());
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

    public function testWithHashCondition()
    {
        $this->assertEquals(1, Author::where(['author_id' =>1])->count());
    }

    public function testWithArrayCondition()
    {
        $this->assertEquals(1, Author::where(['author_id = ?', 1])->count());
    }
}
