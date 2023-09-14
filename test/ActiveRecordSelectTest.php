<?php

namespace test;

use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;
use test\models\Book;

class ActiveRecordSelectTest extends \DatabaseTestCase
{
    public function testSimpleString()
    {
        $book = Book::select('name')
            ->first();
        $this->assertEquals(['name' => 'Ancient Art of Main Tanking'], $book->attributes());
    }

    public function testChainSimpleString()
    {
        $book = Book::select('name')
            ->select('publisher')
            ->first();
        $this->assertEquals([
            'name' => 'Ancient Art of Main Tanking',
            'publisher' => 'Random House'
        ], $book->attributes());
    }

    public function testArrayOfSimpleStrings()
    {
        $book = Book::select(['name', 'publisher'])
            ->first();
        $this->assertEquals([
            'name' => 'Ancient Art of Main Tanking',
            'publisher' => 'Random House'
        ], $book->attributes());
    }

    public function testListOfSimpleStrings()
    {
        $book = Book::select('name', 'publisher')
            ->first();
        $this->assertEquals([
            'name' => 'Ancient Art of Main Tanking',
            'publisher' => 'Random House'
        ], $book->attributes());
    }

    public function testAlias()
    {
        $book = Author::select('name as title, 123 as bubba')
            ->order('title desc')
            ->first();

        $this->assertEquals([
            'title' => 'Uncle Bob',
            'bubba' => 123
        ], $book->attributes());
        $this->assertEquals('Uncle Bob', $book->title);
        $this->assertEquals(123, $book->bubba);
    }

    public function testFindWithSelectNonSelectedFieldsShouldNotHaveAttributes()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::select('name, 123 as bubba')->first();
        $author->id;
        $this->fail('expected ActiveRecord\UndefinedPropertyExecption');
    }
}
