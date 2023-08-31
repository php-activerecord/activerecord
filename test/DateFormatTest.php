<?php

use test\models\Author;

class DateFormatTest extends DatabaseTestCase
{
    public function test_datefield_gets_converted_to_ar_datetime()
    {
        //make sure first author has a date
        $author = Author::first();
        $author->some_date = new DateTime();
        $author->save();

        $author = Author::first();
        $this->assertInstanceOf('ActiveRecord\\DateTime', $author->some_date);
    }
}
