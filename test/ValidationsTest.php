<?php

use ActiveRecord as AR;

class BookValidations extends ActiveRecord\Model
{
    public static $table_name = 'books';
    public static $alias_attribute = ['name_alias' => 'name', 'x' => 'secondary_author_id'];
    public static $validates_presence_of = [];
    public static $validates_uniqueness_of = [];
    public static $custom_validator_error_msg = 'failed custom validation';

    // fired for every validation - but only used for custom validation test
    public function validate()
    {
        if ('test_custom_validation' == $this->name) {
            $this->errors->add('name', self::$custom_validator_error_msg);
        }
    }
}

class ValuestoreValidations extends ActiveRecord\Model
{
    public static $table_name = 'valuestore';
    public static $validates_uniqueness_of = [];
}

class ValidationsTest extends DatabaseTest
{
    public function set_up($connection_name=null)
    {
        parent::set_up($connection_name);

        BookValidations::$validates_presence_of[0] = 'name';
        BookValidations::$validates_uniqueness_of[0] = 'name';

        ValuestoreValidations::$validates_uniqueness_of[0] = 'key';
    }

    public function test_is_valid_invokes_validations()
    {
        $book = new Book();
        $this->assert_true(empty($book->errors));
        $book->is_valid();
        $this->assert_false(empty($book->errors));
    }

    public function test_is_valid_returns_true_if_no_validations_exist()
    {
        $book = new Book();
        $this->assert_true($book->is_valid());
    }

    public function test_is_valid_returns_false_if_failed_validations()
    {
        $book = new BookValidations();
        $this->assert_false($book->is_valid());
    }

    public function test_is_invalid()
    {
        $book = new Book();
        $this->assert_false($book->is_invalid());
    }

    public function test_is_invalid_is_true()
    {
        $book = new BookValidations();
        $this->assert_true($book->is_invalid());
    }

    public function test_is_iterable()
    {
        $book = new BookValidations();
        $book->is_valid();

        foreach ($book->errors as $name => $message) {
            $this->assert_equals("Name can't be blank", $message);
        }
    }

    public function test_full_messages()
    {
        $book = new BookValidations();
        $book->is_valid();

        $this->assert_equals(["Name can't be blank"], array_values($book->errors->full_messages(['hash' => true])));
    }

    public function test_to_array()
    {
        $book = new BookValidations();
        $book->is_valid();

        $this->assert_equals(['name' => ["Name can't be blank"]], $book->errors->to_array());
    }

    public function test_toString()
    {
        $book = new BookValidations();
        $book->is_valid();
        $book->errors->add('secondary_author_id', 'is invalid');

        $this->assert_equals("Name can't be blank\nSecondary author id is invalid", (string) $book->errors);
    }

    public function test_validates_uniqueness_of()
    {
        BookValidations::create(['name' => 'bob']);
        $book = BookValidations::create(['name' => 'bob']);

        $this->assert_equals(['Name must be unique'], $book->errors->full_messages());
        $this->assert_equals(1, BookValidations::count(['conditions' => "name='bob'"]));
    }

    public function test_validates_uniqueness_of_excludes_self()
    {
        $book = BookValidations::first();
        $this->assert_equals(true, $book->is_valid());
    }

    public function test_validates_uniqueness_of_with_multiple_fields()
    {
        BookValidations::$validates_uniqueness_of[0] = [['name', 'special']];
        $book1 = BookValidations::first();
        $book2 = new BookValidations(['name' => $book1->name, 'special' => $book1->special+1]);
        $this->assert_true($book2->is_valid());
    }

    public function test_validates_uniqueness_of_with_multiple_fields_is_not_unique()
    {
        BookValidations::$validates_uniqueness_of[0] = [['name', 'special']];
        $book1 = BookValidations::first();
        $book2 = new BookValidations(['name' => $book1->name, 'special' => $book1->special]);
        $this->assert_false($book2->is_valid());
        $this->assert_equals(['Name and special must be unique'], $book2->errors->full_messages());
    }

    public function test_validates_uniqueness_of_works_with_alias_attribute()
    {
        BookValidations::$validates_uniqueness_of[0] = [['name_alias', 'x']];
        $book = BookValidations::create(['name_alias' => 'Another Book', 'x' => 2]);
        $this->assert_false($book->is_valid());
        $this->assert_equals(['Name alias and x must be unique'], $book->errors->full_messages());
    }

    public function test_validates_uniqueness_of_works_with_mysql_reserved_word_as_column_name()
    {
        ValuestoreValidations::create(['key' => 'GA_KEY', 'value' => 'UA-1234567-1']);
        $valuestore = ValuestoreValidations::create(['key' => 'GA_KEY', 'value' => 'UA-1234567-2']);

        $this->assert_equals(['Key must be unique'], $valuestore->errors->full_messages());
        $this->assert_equals(1, ValuestoreValidations::count(['conditions' => "`key`='GA_KEY'"]));
    }

    public function test_get_validation_rules()
    {
        $validators = BookValidations::first()->get_validation_rules();
        $this->assert_true(in_array(['validator' => 'validates_presence_of'], $validators['name']));
    }

    public function test_model_is_nulled_out_to_prevent_memory_leak()
    {
        $book = new BookValidations();
        $book->is_valid();
        $this->assert_true(false !== strpos(serialize($book->errors), 'model";N;'));
    }

    public function test_validations_takes_strings()
    {
        BookValidations::$validates_presence_of = ['numeric_test', ['special'], 'name'];
        $book = new BookValidations(['numeric_test' => 1, 'special' => 1]);
        $this->assert_false($book->is_valid());
    }

    public function test_gh131_custom_validation()
    {
        $book = new BookValidations(['name' => 'test_custom_validation']);
        $book->save();
        $this->assert_true($book->errors->is_invalid('name'));
        $this->assert_equals(BookValidations::$custom_validator_error_msg, $book->errors->on('name'));
    }
}
