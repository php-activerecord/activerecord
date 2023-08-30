<?php

use ActiveRecord\Exception\ValidationsArgumentError;

class BookLength extends ActiveRecord\Model
{
    public static $table = 'books';
    public static array $validates_length_of = [];
}

class BookSize extends ActiveRecord\Model
{
    public static $table = 'books';
    public static $validates_size_of = [];
}

class ValidatesLengthOfTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
        BookLength::$validates_length_of = [
            'name' => [
                'allow_blank' => false,
                'allow_null' => false
            ]
        ];
    }

    public function test_within()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 5];
        $book = new BookLength();
        $book->name = '12345';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_within_error_message()
    {
        BookLength::$validates_length_of['name']['within'] = [2, 5];
        $book = new BookLength();
        $book->name = '1';
        $book->is_valid();
        $this->assertEquals(['Name is too short (minimum is 2 characters)'], $book->errors->full_messages());

        $book->name = '123456';
        $book->is_valid();
        $this->assertEquals(['Name is too long (maximum is 5 characters)'], $book->errors->full_messages());
    }

    public function test_within_custom_error_message()
    {
        BookLength::$validates_length_of['name']['within'] = [2, 5];
        BookLength::$validates_length_of['name']['too_short'] = 'is too short';
        BookLength::$validates_length_of['name']['message'] = 'is not between 2 and 5 characters';
        $book = new BookLength();
        $book->name = '1';
        $book->is_valid();
        $this->assertEquals(['Name is not between 2 and 5 characters'], $book->errors->full_messages());

        $book->name = '123456';
        $book->is_valid();
        $this->assertEquals(['Name is not between 2 and 5 characters'], $book->errors->full_messages());
    }

    public function test_valid_in()
    {
        BookLength::$validates_length_of['name']['in'] = [1, 5];
        $book = new BookLength();
        $book->name = '12345';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_aliased_size_of()
    {
        BookSize::$validates_size_of = BookLength::$validates_length_of;
        BookSize::$validates_size_of['name']['within'] = [1, 5];
        $book = new BookSize();
        $book->name = '12345';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_within_and_in()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];
        $book = new BookLength();
        $book->name = 'four';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));

        $this->setUp();
        BookLength::$validates_length_of['name']['in'] = [1, 3];
        $book = new BookLength();
        $book->name = 'four';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_valid_null()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];
        BookLength::$validates_length_of['name']['allow_null'] = true;

        $book = new BookLength();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_valid_blank()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];
        BookLength::$validates_length_of['name']['allow_blank'] = true;

        $book = new BookLength();
        $book->name = '';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_blank()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];

        $book = new BookLength();
        $book->name = '';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $this->assertEquals('is too short (minimum is 1 characters)', $book->errors->first('name'));
    }

    public function test_invalid_null_within()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];

        $book = new BookLength();
        $book->name = null;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $this->assertEquals('is too short (minimum is 1 characters)', $book->errors->first('name'));
    }

    public function test_invalid_null_minimum()
    {
        BookLength::$validates_length_of['name']['minimum'] = 1;

        $book = new BookLength();
        $book->name = null;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $this->assertEquals('is too short (minimum is 1 characters)', $book->errors->first('name'));
    }

    public function test_valid_null_maximum()
    {
        BookLength::$validates_length_of['name']['maximum'] = 1;

        $book = new BookLength();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_float_as_impossible_range_option()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3.6];
        $book = new BookLength();
        $book->name = '123';
        try {
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('Range must be an array of two ints.', $e->getMessage());
        }
    }

    public function test_signed_integer_as_impossible_within_option()
    {
        BookLength::$validates_length_of['name']['within'] = [-1, 3];

        $book = new BookLength();
        $book->name = '123';
        try {
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('minimum value cannot use a signed integer.', $e->getMessage());

            return;
        }

        $this->fail('An expected exception has not be raised.');
    }

    public function test_signed_integer_as_impossible_is_option()
    {
        BookLength::$validates_length_of['name']['is'] = -8;

        $book = new BookLength();
        $book->name = '123';
        try {
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('is value cannot use a signed integer.', $e->getMessage());

            return;
        }

        $this->fail('An expected exception has not be raised.');
    }

    public function test_lack_of_option()
    {
        try {
            $book = new BookLength();
            $book->name = null;
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('Range unspecified.  Specify the [within], [maximum], or [is] option.', $e->getMessage());

            return;
        }

        $this->fail('An expected exception has not be raised.');
    }

    public function test_too_many_options()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];
        BookLength::$validates_length_of['name']['in'] = [1, 3];

        try {
            $book = new BookLength();
            $book->name = null;
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('Too many range options specified.  Choose only one.', $e->getMessage());

            return;
        }

        $this->fail('An expected exception has not be raised.');
    }

    public function test_too_many_options_with_different_option_types()
    {
        BookLength::$validates_length_of['name']['within'] = [1, 3];
        BookLength::$validates_length_of['name']['is'] = 3;

        try {
            $book = new BookLength();
            $book->name = null;
            $book->save();
        } catch (ValidationsArgumentError $e) {
            $this->assertEquals('Too many range options specified.  Choose only one.', $e->getMessage());

            return;
        }

        $this->fail('An expected exception has not be raised.');
    }

    public function test_with_option_as_non_numeric()
    {
        $this->expectException(ValidationsArgumentError::class);
        BookLength::$validates_length_of = [
            'name' => ['within' => ['test']]
        ];

        $book = new BookLength();
        $book->name = null;
        $book->save();
    }

    public function test_with_option_as_non_numeric_non_array()
    {
        $this->expectException(ValidationsArgumentError::class);

        BookLength::$validates_length_of = [
            'name' => ['with' => 'test']
        ];

        $book = new BookLength();
        $book->name = null;
        $book->save();
    }

    public function test_validates_length_of_maximum()
    {
        BookLength::$validates_length_of = [
            'name' => ['maximum' => 10]
        ];
        $book = new BookLength(['name' => '12345678901']);
        $book->is_valid();
        $this->assertEquals(['Name is too long (maximum is 10 characters)'], $book->errors->full_messages());
    }

    public function test_validates_length_of_minimum()
    {
        BookLength::$validates_length_of['name'] = ['minimum' => 2];
        $book = new BookLength(['name' => '1']);
        $book->is_valid();
        $this->assertEquals(['Name is too short (minimum is 2 characters)'], $book->errors->full_messages());
    }

    public function test_validates_length_of_min_max_custom_message()
    {
        BookLength::$validates_length_of['name'] = ['maximum' => 10, 'message' => 'is far too long'];
        $book = new BookLength([
            'name' => '12345678901'
        ]);
        $book->is_valid();
        $this->assertEquals(['Name is far too long'], $book->errors->full_messages());

        BookLength::$validates_length_of['name'] = [
            'minimum' => 10,
            'message' => 'is far too short'
        ];
        $book = new BookLength(['name' => '123456789']);
        $book->is_valid();
        $this->assertEquals(['Name is far too short'], $book->errors->full_messages());
    }

    public function test_validates_length_of_min_max_custom_message_overridden()
    {
        BookLength::$validates_length_of['name'] = [
            'minimum' => 10,
            'too_short' => 'is too short',
            'message' => 'is custom message'
        ];
        $book = new BookLength(['name' => '123456789']);
        $book->is_valid();
        $this->assertEquals(['Name is custom message'], $book->errors->full_messages());
    }

    public function test_validates_length_of_is()
    {
        BookLength::$validates_length_of['name'] = ['is' => 2];
        $book = new BookLength(['name' => '123']);
        $book->is_valid();
        $this->assertEquals(['Name is the wrong length (should be 2 characters)'], $book->errors->full_messages());
    }
}
