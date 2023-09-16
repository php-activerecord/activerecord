<?php

namespace test;

use ActiveRecord\Exception\RecordNotFound;
use test\models\Author;

class ActiveRecordNoneTest extends \DatabaseTestCase
{
    public function testCount()
    {
        $this->assertEquals(0, Author::none()->count());
    }

    public function testFind()
    {
        $this->expectException(RecordNotFound::class);
        Author::none()->find(1);
    }

    public function testToA()
    {
        $this->assertEquals([], Author::none()->to_a());
    }
}
