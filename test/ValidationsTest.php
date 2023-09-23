<?php

use test\models\Book;
use test\models\ValueStoreValidations;

class BookValidations extends ActiveRecord\Model
{
    public static string $table_name = 'books';
    public static array $alias_attribute = ['name_alias' => 'name', 'x' => 'secondary_author_id'];
    public static array $validates_presence_of = [];
    public static array $validates_uniqueness_of = [];
    public static string $custom_validator_error_msg = 'failed custom validation';

    // fired for every validation - but only used for custom validation test
    public function validate()
    {
        if ('test_custom_validation' == $this->name) {
            $this->errors->add('name', self::$custom_validator_error_msg);
        }
    }
}

class ValidationsTest extends DatabaseTestCase
{
    public function setUp(string $connection_name=null): void
    {
        parent::setUp($connection_name);

        BookValidations::$validates_presence_of = ['name' => true];
        BookValidations::$validates_uniqueness_of =  ['name' => true];
        ValueStoreValidations::$validates_uniqueness_of = ['key' => true];
    }

    public function testIsValidInvokesValidations()
    {
        $book = new Book();
        $this->assertTrue(empty($book->errors));
        $book->is_valid();
        $this->assertFalse(empty($book->errors));
    }

    public function testIsValidReturnsTrueIfNoValidationsExist()
    {
        $book = new Book();
        $this->assertTrue($book->is_valid());
    }

    public function testIsValidReturnsFalseIfFailedValidations()
    {
        $book = new BookValidations();
        $this->assertFalse($book->is_valid());
    }

    public function testIsInvalid()
    {
        $book = new Book();
        $this->assertFalse($book->is_invalid());
    }

    public function testIsInvalidIsTrue()
    {
        $book = new BookValidations();
        $this->assertTrue($book->is_invalid());
    }

    public function testIsIterable()
    {
        $book = new BookValidations();
        $book->is_valid();

        foreach ($book->errors as $name => $message) {
            $this->assertEquals("Name can't be blank", $message);
        }
    }

    public function testFullMessages()
    {
        $book = new BookValidations();
        $book->is_valid();

        $this->assertEquals(["Name can't be blank"], array_values($book->errors->full_messages(['hash' => true])));
    }

    public function testToArray()
    {
        $book = new BookValidations();
        $book->is_valid();

        $this->assertEquals(['name' => ["Name can't be blank"]], $book->errors->to_array());
    }

    public function testToString()
    {
        $book = new BookValidations();
        $book->is_valid();
        $book->errors->add('secondary_author_id', 'is invalid');

        $this->assertEquals("Name can't be blank\nSecondary author id is invalid", (string) $book->errors);
    }

    public function testValidatesUniquenessOf()
    {
        BookValidations::create(['name' => 'bob']);
        $book = BookValidations::create(['name' => 'bob']);

        $this->assertEquals(['Name must be unique'], $book->errors->full_messages());
        $this->assertEquals(1, BookValidations::where("name='bob'")->count());
    }

    public function testValidatesUniquenessOfExcludesSelf()
    {
        $book = BookValidations::first();
        $this->assertEquals(true, $book->is_valid());
    }

    public function testValidatesUniquenessOfWithMultipleFields()
    {
        BookValidations::$validates_uniqueness_of  = [
            'name' => [
                'scope' => ['special']
            ]
        ];
        $book1 = BookValidations::first();
        $book2 = new BookValidations(['name' => $book1->name, 'special' => $book1->special+1]);
        $this->assertTrue($book2->is_valid());
    }

    public function testValidatesUniquenessOfWithMultipleFieldsIsNotUnique()
    {
        BookValidations::$validates_uniqueness_of  = [
            'name' => [
                'scope' => ['special']
            ]
        ];
        $book1 = BookValidations::first();
        $book2 = new BookValidations(['name' => $book1->name, 'special' => $book1->special]);
        $this->assertFalse($book2->is_valid());
        $this->assertEquals(['Name and special must be unique'], $book2->errors->full_messages());
    }

    public function testValidatesUniquenessOfWorksWithAliasAttribute()
    {
        BookValidations::$validates_uniqueness_of = ['name_alias'=> ['scope'=>['x']]];
        $book = BookValidations::create(['name_alias' => 'Another Book', 'x' => 2]);
        $this->assertFalse($book->is_valid());
        $this->assertEquals(['Name alias and x must be unique'], $book->errors->full_messages());
    }

    public function testGetValidationRules()
    {
        $validators = BookValidations::first()->get_validation_rules();
        $this->assertTrue(in_array(['validator' => 'validates_presence_of'], $validators['name']));
    }

    public function testModelIsNulledOutToPreventMemoryLeak()
    {
        $book = new BookValidations();
        $book->is_valid();
        $this->assertTrue(false !== strpos(serialize($book->errors), 'model";N;'));
    }

    public function testValidationsTakesStrings()
    {
        BookValidations::$validates_presence_of = [
            'numeric_test' => true,
            'special' => true,
            'name' => true
        ];
        $book = new BookValidations(['numeric_test' => 1, 'special' => 1]);
        $this->assertFalse($book->is_valid());
    }

    public function testGh131CustomValidation()
    {
        $book = new BookValidations(['name' => 'test_custom_validation']);
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $this->assertEquals(
            BookValidations::$custom_validator_error_msg,
            $book->errors->first('name')
        );
    }
}
