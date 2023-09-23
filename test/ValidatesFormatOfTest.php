<?php

class BookFormat extends ActiveRecord\Model
{
    public static $table = 'books';
    public static array $validates_format_of = [
        'name' => []
    ];
}

class ValidatesFormatOfTest extends DatabaseTestCase
{
    public function testFormat()
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

    public function testInvalidNull(): void
    {
        BookFormat::$validates_format_of = ['name' => ['with' => '/[0-9]/']];
        $book = new BookFormat();
        $book->name = null;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function testInvalidBlank(): void
    {
        BookFormat::$validates_format_of = ['name' => ['with' => '/[^0-9]/']];
        $book = new BookFormat();
        $book->name = '';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function testValidBlankAndAllowBlank()
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

    public function testValidNullAndAllowNull()
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

    public function testInvalidWithExpressionAsNonRegexp()
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

    public function testCustomMessage()
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
