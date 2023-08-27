<?php

class BookExclusion extends ActiveRecord\Model
{
    public static $table = 'books';
    public static $validates_exclusion_of = [
        ['name', 'in' => ['blah', 'alpha', 'bravo']]
    ];
}

class BookInclusion extends ActiveRecord\Model
{
    public static $table = 'books';
    public static $validates_inclusion_of = [
        ['name', 'in' => ['blah', 'tanker', 'shark']]
    ];
}

class ValidatesInclusionAndExclusionOfTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
        BookInclusion::$validates_inclusion_of[0] = ['name', 'in' => ['blah', 'tanker', 'shark']];
        BookExclusion::$validates_exclusion_of[0] = ['name', 'in' => ['blah', 'alpha', 'bravo']];
    }

    public function test_inclusion()
    {
        $book = new BookInclusion();
        $book->name = 'blah';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_exclusion()
    {
        $book = new BookExclusion();
        $book->name = 'blahh';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_inclusion()
    {
        $book = new BookInclusion();
        $book->name = 'thanker';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $book->name = 'alpha ';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_invalid_exclusion()
    {
        $book = new BookExclusion();
        $book->name = 'alpha';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));

        $book = new BookExclusion();
        $book->name = 'bravo';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function test_inclusion_with_numeric()
    {
        BookInclusion::$validates_inclusion_of[0]['in']= [0, 1, 2];
        $book = new BookInclusion();
        $book->name = 2;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_inclusion_with_boolean()
    {
        BookInclusion::$validates_inclusion_of[0]['in']= [true];
        $book = new BookInclusion();
        $book->name = true;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_inclusion_with_null()
    {
        BookInclusion::$validates_inclusion_of[0]['in']= [null];
        $book = new BookInclusion();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_invalid_inclusion_with_numeric()
    {
        BookInclusion::$validates_inclusion_of[0]['in']= [0, 1, 2];
        $book = new BookInclusion();
        $book->name = 5;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function tes_inclusion_within_option()
    {
        BookInclusion::$validates_inclusion_of[0] = ['name', 'within' => ['okay']];
        $book = new BookInclusion();
        $book->name = 'okay';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function tes_inclusion_scalar_value()
    {
        BookInclusion::$validates_inclusion_of[0] = ['name', 'within' => 'okay'];
        $book = new BookInclusion();
        $book->name = 'okay';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_valid_null()
    {
        BookInclusion::$validates_inclusion_of[0]['allow_null'] = true;
        $book = new BookInclusion();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_valid_blank()
    {
        BookInclusion::$validates_inclusion_of[0]['allow_blank'] = true;
        $book = new BookInclusion();
        $book->name = '';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function test_custom_message()
    {
        $msg = 'is using a custom message.';
        BookInclusion::$validates_inclusion_of[0]['message'] = $msg;
        BookExclusion::$validates_exclusion_of[0]['message'] = $msg;

        $book = new BookInclusion();
        $book->name = 'not included';
        $book->save();
        $this->assertEquals('is using a custom message.', $book->errors->on('name'));
        $book = new BookExclusion();
        $book->name = 'bravo';
        $book->save();
        $this->assertEquals('is using a custom message.', $book->errors->on('name'));
    }
}
