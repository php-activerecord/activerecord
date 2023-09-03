<?php

use test\models\Author;

class DateFormatTest extends DatabaseTestCase
{
    public function testDatefieldGetsConvertedToArDatetime()
    {
        // make sure first author has a date
        $author = Author::first();
        $author->some_date = new DateTime();
        $author->save();

        $author = Author::first();
        $this->assertInstanceOf('ActiveRecord\\DateTime', $author->some_date);
    }
}
