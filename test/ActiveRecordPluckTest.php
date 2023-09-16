<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
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

    public function testSelectIsNotClobberedByPluck()
    {
        $relation = Author::select('name')->where(['mixedCaseField' => 'Bill']);
        $this->assertEquals([3, 4], $relation->pluck(['author_id']));

        $this->expectException(UndefinedPropertyException::class);
        $this->assertEquals('Bill Clinton', $relation->to_a()[0]->author_id);
    }

    public function testIds()
    {
        $authors = Author::where(['mixedCaseField' => 'Bill'])->ids();
        $this->assertEquals(2, count($authors));
        $this->assertEquals(3, $authors[0]);
        $this->assertEquals(4, $authors[1]);
    }

    public function testIdsAll()
    {
        $authors = Author::ids();
        $this->assertEquals(4, count($authors));
    }
}
