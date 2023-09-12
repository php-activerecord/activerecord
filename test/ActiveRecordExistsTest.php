<?php

namespace test;

use test\models\Author;

class ActiveRecordExistsTest extends \DatabaseTestCase
{
    public function testNoArguments()
    {
        $this->assertTrue(Author::exists());
    }

    public function testWithSingleId()
    {
        $this->assertTrue(Author::exists(1));
        $this->assertTrue(Author::exists('1'));
        $this->assertFalse(Author::exists(134234));
    }

    public function testWithArrayConditions()
    {
        $this->assertTrue(Author::exists(['author_id=? and name=?', 1, 'Tito']));
        $this->assertFalse(Author::exists(['author_id=? and name=?', 1, 'Sam']));
    }

    public function testWithHashConditions()
    {
        $this->assertTrue(Author::exists(['name' => 'Tito']));
    }

    public function testWithFalse()
    {
        $this->assertFalse(Author::exists(false));
    }
}
