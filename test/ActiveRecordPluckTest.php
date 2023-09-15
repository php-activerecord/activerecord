<?php

namespace test;

use ActiveRecord\Exception\ValidationsArgumentError;
use test\models\Author;

class ActiveRecordPluckTest extends \DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->expectException(ValidationsArgumentError::class);
        $author = Author::pluck();
    }

    public function testSingleArgument()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->pluck('name');
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]);
        $this->assertEquals('Uncle Bob', $authors[1]);
    }

    public function testMultipleArguments()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->pluck('name', 'author_id');
        $this->assertMultipleArgumentsResult($authors);
    }

    public function testCommaDelimitedString()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->pluck('name, author_id');
        $this->assertMultipleArgumentsResult($authors);
    }

    public function testArray()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->pluck(['name', 'author_id']);
        $this->assertMultipleArgumentsResult($authors);
    }

    private function assertMultipleArgumentsResult($authors)
    {
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0][0]);
        $this->assertEquals(3, $authors[0][1]);
        $this->assertEquals('Uncle Bob', $authors[1][0]);
        $this->assertEquals(4, $authors[1][1]);
    }

    public function testIds()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->ids();
        $this->assertEquals(2, count($authors));
        $this->assertEquals(3, $authors[0]);
        $this->assertEquals(4, $authors[1]);
    }
}
