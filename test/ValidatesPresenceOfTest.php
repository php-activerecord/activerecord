<?php

class BookPresence extends ActiveRecord\Model
{
    public static string $table_name = 'books';

    public static array $validates_presence_of = [
        'name' => true
    ];
}

class AuthorPresence extends ActiveRecord\Model
{
    public static string $table_name = 'authors';

    public static array $validates_presence_of = [
        'some_date' => true
    ];
}

class ValidatesPresenceOfTest extends DatabaseTestCase
{
    public function testPresence()
    {
        $book = new BookPresence(['name' => 'blah']);
        $this->assertFalse($book->is_invalid());
    }

    public function testPresenceOnDateFieldIsValid()
    {
        $author = new AuthorPresence(['some_date' => '2010-01-01']);
        $this->assertTrue($author->is_valid());
    }

    public function testPresenceOnDateFieldIsNotValid()
    {
        $author = new AuthorPresence();
        $this->assertFalse($author->is_valid());
    }

    public function testInvalidNull()
    {
        $book = new BookPresence(['name' => null]);
        $this->assertTrue($book->is_invalid());
    }

    public function testInvalidBlank()
    {
        $book = new BookPresence(['name' => '']);
        $this->assertTrue($book->is_invalid());
    }

    public function testValidWhiteSpace()
    {
        $book = new BookPresence(['name' => ' ']);
        $this->assertFalse($book->is_invalid());
    }

    public function testCustomMessage()
    {
        BookPresence::$validates_presence_of = [
            'name' => ['message' => 'is using a custom message.']
        ];

        $book = new BookPresence(['name' => null]);
        $book->is_valid();
        $this->assertEquals('is using a custom message.', $book->errors->first('name'));
    }

    public function testValidZero()
    {
        $book = new BookPresence(['name' => 0]);
        $this->assertTrue($book->is_valid());
    }
}
