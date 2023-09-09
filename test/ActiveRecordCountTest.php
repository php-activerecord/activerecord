<?php

namespace test;

use ActiveRecord;
use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Exception\ValidationsArgumentError;
use ActiveRecord\Model;
use DatabaseTestCase;
use test\models\Author;
use test\models\HonestLawyer;
use test\models\JoinBook;
use test\models\Venue;

class ActiveRecordCountTest extends DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->assertEquals(4, Author::count());
    }

    public function testColumnNameAsArgument() {
        // tbd
    }

    public function testWithStringConditions() {
        $this->assertEquals(1, Author::where('author_id=1')->count());
    }

    public function testWithHashCondition() {
        $this->assertEquals(1, Author::where(['author_id' =>1])->count());
    }

    public function testWithArrayCondition() {
        $this->assertEquals(1, Author::where(['author_id = ?', 1])->count());
    }
}
