<?php

class BookExclusion extends ActiveRecord\Model
{
    public static $table = 'books';
    public static array $validates_exclusion_of = [
        'name' => [
            'in' => ['blah', 'alpha', 'bravo']
        ]
    ];
}

class BookInclusion extends ActiveRecord\Model
{
    public static $table = 'books';
    public static array $validates_inclusion_of = [
        'name' => [
            'in' => ['blah', 'tanker', 'shark']
        ]
    ];
}

class ValidatesInclusionAndExclusionOfTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
        BookInclusion::$validates_inclusion_of['name'] = [
            'in' => ['blah', 'tanker', 'shark']
        ];
        BookExclusion::$validates_exclusion_of['name'] = [
            'in' => ['blah', 'alpha', 'bravo']
        ];
    }

    public function testInclusion()
    {
        $book = new BookInclusion();
        $book->name = 'blah';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testExclusion()
    {
        $book = new BookExclusion();
        $book->name = 'blahh';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testInvalidInclusion()
    {
        $book = new BookInclusion();
        $book->name = 'thanker';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
        $book->name = 'alpha ';
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function testInvalidExclusion()
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

    public function testInclusionWithNumeric()
    {
        BookInclusion::$validates_inclusion_of['name']['in'] = [0, 1, 2];
        $book = new BookInclusion();
        $book->name = 2;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testInclusionWithBoolean()
    {
        BookInclusion::$validates_inclusion_of['name']['in'] = [true];
        $book = new BookInclusion();
        $book->name = true;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testInclusionWithNull()
    {
        BookInclusion::$validates_inclusion_of['name']['in']= [null];
        $book = new BookInclusion();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testInvalidInclusionWithNumeric()
    {
        BookInclusion::$validates_inclusion_of['name']['in']= [0, 1, 2];
        $book = new BookInclusion();
        $book->name = 5;
        $book->save();
        $this->assertTrue($book->errors->is_invalid('name'));
    }

    public function tes_inclusion_within_option()
    {
        BookInclusion::$validates_inclusion_of['name'] = [
            'within' => ['okay']
        ];
        $book = new BookInclusion();
        $book->name = 'okay';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testInclusionScalarValue(): void
    {
        BookInclusion::$validates_inclusion_of['name'] = [
            'within' => ['okay']
        ];
        $book = new BookInclusion();
        $book->name = 'okay';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testValidNull()
    {
        BookInclusion::$validates_inclusion_of['name']['allow_null'] = true;
        $book = new BookInclusion();
        $book->name = null;
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testValidBlank()
    {
        BookInclusion::$validates_inclusion_of['name']['allow_blank'] = true;
        $book = new BookInclusion();
        $book->name = '';
        $book->save();
        $this->assertFalse($book->errors->is_invalid('name'));
    }

    public function testCustomMessage()
    {
        $msg = 'is using a custom message.';
        BookInclusion::$validates_inclusion_of['name']['message'] = $msg;
        BookExclusion::$validates_exclusion_of['name']['message'] = $msg;

        $book = new BookInclusion();
        $book->name = 'not included';
        $book->save();
        $this->assertEquals('is using a custom message.', $book->errors->first('name'));
        $book = new BookExclusion();
        $book->name = 'bravo';
        $book->save();
        $this->assertEquals('is using a custom message.', $book->errors->first('name'));
    }
}
