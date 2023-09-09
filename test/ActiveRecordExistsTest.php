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

class ActiveRecordExistsTest extends DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->assertTrue(Author::exists());
    }

    public function testWithSingleId() {
        $this->assertTrue(Author::exists(1));
        $this->assertTrue(Author::exists('1'));
        $this->assertFalse(Author::exists(134234));
    }

    public function testWithArrayConditions() {
        $this->assertTrue(Author::exists(['author_id=? and name=?', 1, 'Tito']));
        $this->assertFalse(Author::exists(['author_id=? and name=?', 1, 'Sam']));
    }

    public function testWithHashConditions() {
        $this->assertTrue(Author::exists(['name' => 'Tito']));
    }
}
