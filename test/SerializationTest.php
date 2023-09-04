<?php

use ActiveRecord\DateTime;
use ActiveRecord\Serialize\CsvSerializer;
use ActiveRecord\Serialize\JsonSerializer;
use test\models\Author;
use test\models\Book;
use test\models\Host;

class SerializationTest extends DatabaseTestCase
{
    public function _a($options=[], $model=null)
    {
        if (!$model) {
            $model = Book::find(1);
        }

        $s = new JsonSerializer($model, $options);

        return $s->to_a();
    }

    public function testOnly()
    {
        $this->assertArrayHasKey('special', $this->_a(['only' => ['name', 'special']]));
        $this->assertArrayHasKey('name', $this->_a(['only' => ['name', 'special']]));
    }

    public function testOnlyNotArray()
    {
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name']));
    }

    public function testOnlyShouldOnlyApplyToAttributes()
    {
        Book::$belongs_to = [
            'author' => true
        ];

        $this->assertArrayHasKey('author', $this->_a(['only' => 'name', 'include' => 'author']));
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name', 'include' => 'author']));
        $this->assertArrayHasKey('book_id', $this->_a(['only' => 'book_id', 'methods' => 'upper_name']));
        $this->assertArrayHasKey('upper_name', $this->_a(['only' => 'book_id', 'methods' => 'upper_name']));
    }

    public function testOnlyOverridesExcept()
    {
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name', 'except' => 'name']));
    }

    public function testExcept()
    {
        $this->assertArrayNotHasKey('name', $this->_a(['except' => ['name', 'special']]));
        $this->assertArrayNotHasKey('special', $this->_a(['except' => ['name', 'special']]));
    }

    public function testExceptTakesAString()
    {
        $this->assertArrayNotHasKey('name', $this->_a(['except' => 'name']));
    }

    public function testMethods()
    {
        $a = $this->_a(['methods' => ['upper_name']]);
        $this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upper_name']);
    }

    public function testMethodsTakesAString()
    {
        $a = $this->_a(['methods' => 'upper_name']);
        $this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upper_name']);
    }

    // methods added last should have value of the method in our json
    // rather than the regular attribute value
    public function testMethodsMethodSameAsAttribute()
    {
        $a = $this->_a(['methods' => 'name']);
        $this->assertEquals('ancient art of main tanking', $a['name']);
    }

    public function testInclude()
    {
        $a = $this->_a(['include' => ['author']]);
        $this->assertArrayHasKey('parent_author_id', $a['author']);
    }

    public function testIncludeNestedWithNestedOptions()
    {
        $a = $this->_a(
            ['include' => ['events' => ['except' => 'title', 'include' => ['host' => ['only' => 'id']]]]],
            Host::find(4));

        $this->assertEquals(3, count($a['events']));
        $this->assertArrayNotHasKey('title', $a['events'][0]);
        $this->assertEquals(['id' => 4], $a['events'][0]['host']);
    }

    public function testDatetimeValuesGetConvertedToStrings()
    {
        $now = new DateTime();
        $a = $this->_a(['only' => 'created_at'], new Author(['created_at' => $now]));
        $this->assertEquals($now->format(\ActiveRecord\Serialize\Serialization::$DATETIME_FORMAT), $a['created_at']);
    }

    public function testToJson()
    {
        $book = Book::find(1);
        $json = $book->to_json();
        $this->assertEquals($book->attributes(), (array) json_decode($json));
    }

    public function testToJsonIncludeRoot()
    {
        $this->assertNotNull(json_decode(Book::find(1)->to_json([
            'include_root' => true
        ]))->{'test\models\book'});
    }

    public function testToXmlInclude()
    {
        $xml = Host::find(4)->to_xml(['include' => 'events']);
        $decoded = get_object_vars(new SimpleXMLElement($xml));

        $this->assertEquals(3, count($decoded['events']->event));
    }

    public function testToXml()
    {
        $book = Book::find(1);
        $this->assertEquals($book->attributes(), get_object_vars(new SimpleXMLElement($book->to_xml())));
    }

    public function testToArray()
    {
        $book = Book::find(1);
        $array = $book->to_array();
        $this->assertEquals($book->attributes(), $array);
    }

    public function testToArrayIncludeRoot()
    {
        $book = Book::find(1);
        $array = $book->to_array(['include_root'=>true]);
        $book_attributes = ['test\models\book' => $book->attributes()];
        $this->assertEquals($book_attributes, $array);
    }

    public function testToArrayExcept()
    {
        $book = Book::find(1);
        $array = $book->to_array(['except' => ['special']]);
        $book_attributes = $book->attributes();
        unset($book_attributes['special']);
        $this->assertEquals($book_attributes, $array);
    }

    public function testWorksWithDatetime()
    {
        Author::find(1)->update_attribute('created_at', new DateTime());
        $this->assertMatchesRegularExpression('/<updated_at>[0-9]{4}-[0-9]{2}-[0-9]{2}/', Author::find(1)->to_xml());
        $this->assertMatchesRegularExpression('/"updated_at":"[0-9]{4}-[0-9]{2}-[0-9]{2}/', Author::find(1)->to_json());
    }

    public function testToXmlSkipInstruct()
    {
        $this->assertSame(false, strpos(Book::find(1)->to_xml(['skip_instruct' => true]), '<?xml version'));
        $this->assertSame(0, strpos(Book::find(1)->to_xml(['skip_instruct' => false]), '<?xml version'));
    }

    public function testOnlyMethod()
    {
        $this->assertStringContainsString('<sharks>lasers</sharks>', Author::first()->to_xml(['only_method' => 'return_something']));
    }

    public function testToCsv()
    {
        $book = Book::find(1);
        $this->assertEquals('1,1,2,"Ancient Art of Main Tanking","Random House",0,0', $book->to_csv());
    }

    public function testToCsvOnlyHeader()
    {
        $book = Book::find(1);
        $this->assertEquals('book_id,author_id,secondary_author_id,name,publisher,numeric_test,special',
            $book->to_csv(['only_header'=>true])
        );
    }

    public function testToCsvOnlyMethod()
    {
        $book = Book::find(1);
        $this->assertEquals('2,"Ancient Art of Main Tanking"',
            $book->to_csv(['only'=>['name', 'secondary_author_id']])
        );
    }

    public function testToCsvOnlyMethodOnHeader()
    {
        $book = Book::find(1);
        $this->assertEquals('secondary_author_id,name',
            $book->to_csv(['only'=>['secondary_author_id', 'name'],
                                'only_header'=>true])
        );
    }

    public function testToCsvWithCustomDelimiter()
    {
        $book = Book::find(1);
        CsvSerializer::$delimiter=';';
        $this->assertEquals('1;1;2;"Ancient Art of Main Tanking";"Random House";0;0', $book->to_csv());
    }

    public function testToCsvWithCustomEnclosure()
    {
        $book = Book::find(1);
        CsvSerializer::$delimiter=',';
        CsvSerializer::$enclosure="'";
        $this->assertEquals("1,1,2,'Ancient Art of Main Tanking','Random House',0,0", $book->to_csv());
    }
}
