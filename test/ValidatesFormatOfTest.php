<?php

use ActiveRecord\Exception\ValidationsArgumentError;

class BookFormat extends ActiveRecord\Model
{
    public static $table = 'books';
    public static array $validates_format_of = [
        'name' => []
    ];
}

class ValidatesFormatOfTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
    }

    public function test_format()
    {
        BookFormat::$validates_format_of = ['name' => ['with' => '/^[a-z\W]*$/']];
        $book = new BookFormat(['author_id' => 1, 'name' => 'testing reg']);
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));

        BookFormat::$validates_format_of = ['name' => ['with' => '/[0-9]/']];
        $book = new BookFormat(['author_id' => 1, 'name' => 12]);
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_null(): void
    {
        BookFormat::$validates_format_of = ['name' => ['with' => '/[0-9]/']];
        $book = new BookFormat();
        $book->name = null;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_invalid_blank(): void
    {
        BookFormat::$validates_format_of = ['name' => ['with' => '/[^0-9]/']];
        $book = new BookFormat();
        $book->name = '';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_valid_blank_and_allow_blank()
    {
        BookFormat::$validates_format_of = [
            'name' => [
                'with' => '/[^0-9]/',
                'allow_blank' => true
            ]
        ];
        $book = new BookFormat(['author_id' => 1, 'name' => '']);
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_valid_null_and_allow_null()
    {
        BookFormat::$validates_format_of = [
            'name' => [
                'with' => '/[^0-9]/',
                'allow_null' => true
            ]
        ];
        $book = new BookFormat();
        $book->author_id = 1;
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_with_expression_as_non_regexp()
    {
        BookFormat::$validates_format_of = [
            'name' => [
                'with' => 'blah'
            ]
        ];
        $book = new BookFormat();
        $book->name = 'blah';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_custom_message()
    {
        BookFormat::$validates_format_of = [
            'name' => [
                'message' => 'is using a custom message.',
                'with' => '/[^0-9]/'
            ]
        ];

        $book = new BookFormat();
        $book->name = null;
        $book->save();
        $this->assertEquals('is using a custom message.', $book->errors->first('name'));
    }
}
