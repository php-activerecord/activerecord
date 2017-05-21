<?php

class BookPresence extends ActiveRecord\Model
{
    public static $table_name = 'books';

    public static $validates_presence_of = [
        ['name']
    ];
}

class AuthorPresence extends ActiveRecord\Model
{
    public static $table_name = 'authors';

    public static $validates_presence_of = [
        ['some_date']
    ];
}

class ValidatesPresenceOfTest extends DatabaseTest
{
    public function test_presence()
    {
        $book = new BookPresence(['name' => 'blah']);
        $this->assert_false($book->is_invalid());
    }

    public function test_presence_on_date_field_is_valid()
    {
        $author = new AuthorPresence(['some_date' => '2010-01-01']);
        $this->assert_true($author->is_valid());
    }

    public function test_presence_on_date_field_is_not_valid()
    {
        $author = new AuthorPresence();
        $this->assert_false($author->is_valid());
    }

    public function test_invalid_null()
    {
        $book = new BookPresence(['name' => null]);
        $this->assert_true($book->is_invalid());
    }

    public function test_invalid_blank()
    {
        $book = new BookPresence(['name' => '']);
        $this->assert_true($book->is_invalid());
    }

    public function test_valid_white_space()
    {
        $book = new BookPresence(['name' => ' ']);
        $this->assert_false($book->is_invalid());
    }

    public function test_custom_message()
    {
        BookPresence::$validates_presence_of[0]['message'] = 'is using a custom message.';

        $book = new BookPresence(['name' => null]);
        $book->is_valid();
        $this->assert_equals('is using a custom message.', $book->errors->on('name'));
    }

    public function test_valid_zero()
    {
        $book = new BookPresence(['name' => 0]);
        $this->assert_true($book->is_valid());
    }
}
