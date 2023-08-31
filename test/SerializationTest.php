<?php

use ActiveRecord\DateTime;
use ActiveRecord\Serialize\ArraySerializer;
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

    public function test_only()
    {
        $this->assertArrayHasKey('special', $this->_a(['only' => ['name', 'special']]));
        $this->assertArrayHasKey('name', $this->_a(['only' => ['name', 'special']]));
    }

    public function test_only_not_array()
    {
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name']));
    }

    public function test_only_should_only_apply_to_attributes()
    {
        $this->assertArrayHasKey('author', $this->_a(['only' => 'name', 'include' => 'author']));
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name', 'include' => 'author']));
    }

    public function test_only_overrides_except()
    {
        $this->assertArrayHasKey('name', $this->_a(['only' => 'name', 'except' => 'name']));
    }

    public function test_except()
    {
        $this->assertArrayNotHasKey('name', $this->_a(['except' => ['name', 'special']]));
        $this->assertArrayNotHasKey('special', $this->_a(['except' => ['name', 'special']]));
    }

    public function test_except_takes_a_string()
    {
        $this->assertArrayNotHasKey('name', $this->_a(['except' => 'name']));
    }

    public function test_methods()
    {
        $a = $this->_a(['methods' => ['upper_name']]);
        $this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upper_name']);
    }

    public function test_methods_takes_a_string()
    {
        $a = $this->_a(['methods' => 'upper_name']);
        $this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upper_name']);
    }

    // methods added last should we shuld have value of the method in our json
    // rather than the regular attribute value
    public function test_methods_method_same_as_attribute()
    {
        $a = $this->_a(['methods' => 'name']);
        $this->assertEquals('Ancient Art of Main Tanking', $a['name']);
    }

    public function test_include()
    {
        $a = $this->_a(['include' => ['author']]);
        $this->assertArrayHasKey('parent_author_id', $a['author']);
    }

    public function test_include_nested_with_nested_options()
    {
        $a = $this->_a(
            ['include' => ['events' => ['except' => 'title', 'include' => ['host' => ['only' => 'id']]]]],
            Host::find(4));

        $this->assertEquals(3, count($a['events']));
        $this->assertArrayNotHasKey('title', $a['events'][0]);
        $this->assertEquals(['id' => 4], $a['events'][0]['host']);
    }

    public function test_datetime_values_get_converted_to_strings()
    {
        $now = new DateTime();
        $a = $this->_a(['only' => 'created_at'], new Author(['created_at' => $now]));
        $this->assertEquals($now->format(\ActiveRecord\Serialize\Serialization::$DATETIME_FORMAT), $a['created_at']);
    }

    public function test_to_json()
    {
        $book = Book::find(1);
        $json = $book->to_json();
        $this->assertEquals($book->attributes(), (array) json_decode($json));
    }

    public function test_to_json_include_root()
    {
        $this->assertNotNull(json_decode(Book::find(1)->to_json([
            'include_root' => true
        ]))->{'test\models\book'});
    }

    public function test_to_xml_include()
    {
        $xml = Host::find(4)->to_xml(['include' => 'events']);
        $decoded = get_object_vars(new SimpleXMLElement($xml));

        $this->assertEquals(3, count($decoded['events']->event));
    }

    public function test_to_xml()
    {
        $book = Book::find(1);
        $this->assertEquals($book->attributes(), get_object_vars(new SimpleXMLElement($book->to_xml())));
    }

    public function test_to_array()
    {
        $book = Book::find(1);
        $array = $book->to_array();
        $this->assertEquals($book->attributes(), $array);
    }

    public function test_to_array_include_root()
    {
        $book = Book::find(1);
        $array = $book->to_array(['include_root'=>true]);
        $book_attributes = ['test\models\book' => $book->attributes()];
        $this->assertEquals($book_attributes, $array);
    }

    public function test_to_array_except()
    {
        $book = Book::find(1);
        $array = $book->to_array(['except' => ['special']]);
        $book_attributes = $book->attributes();
        unset($book_attributes['special']);
        $this->assertEquals($book_attributes, $array);
    }

    public function test_works_with_datetime()
    {
        Author::find(1)->update_attribute('created_at', new DateTime());
        $this->assertMatchesRegularExpression('/<updated_at>[0-9]{4}-[0-9]{2}-[0-9]{2}/', Author::find(1)->to_xml());
        $this->assertMatchesRegularExpression('/"updated_at":"[0-9]{4}-[0-9]{2}-[0-9]{2}/', Author::find(1)->to_json());
    }

    public function test_to_xml_skip_instruct()
    {
        $this->assertSame(false, strpos(Book::find(1)->to_xml(['skip_instruct' => true]), '<?xml version'));
        $this->assertSame(0, strpos(Book::find(1)->to_xml(['skip_instruct' => false]), '<?xml version'));
    }

    public function test_only_method()
    {
        $this->assertStringContainsString('<sharks>lasers</sharks>', Author::first()->to_xml(['only_method' => 'return_something']));
    }

    public function test_to_csv()
    {
        $book = Book::find(1);
        $this->assertEquals('1,1,2,"Ancient Art of Main Tanking",0,0', $book->to_csv());
    }

    public function test_to_csv_only_header()
    {
        $book = Book::find(1);
        $this->assertEquals('book_id,author_id,secondary_author_id,name,numeric_test,special',
                         $book->to_csv(['only_header'=>true])
                         );
    }

    public function test_to_csv_only_method()
    {
        $book = Book::find(1);
        $this->assertEquals('2,"Ancient Art of Main Tanking"',
                         $book->to_csv(['only'=>['name', 'secondary_author_id']])
                         );
    }

    public function test_to_csv_only_method_on_header()
    {
        $book = Book::find(1);
        $this->assertEquals('secondary_author_id,name',
                         $book->to_csv(['only'=>['secondary_author_id', 'name'],
                                             'only_header'=>true])
                         );
    }

    public function test_to_csv_with_custom_delimiter()
    {
        $book = Book::find(1);
        CsvSerializer::$delimiter=';';
        $this->assertEquals('1;1;2;"Ancient Art of Main Tanking";0;0', $book->to_csv());
    }

    public function test_to_csv_with_custom_enclosure()
    {
        $book = Book::find(1);
        CsvSerializer::$delimiter=',';
        CsvSerializer::$enclosure="'";
        $this->assertEquals("1,1,2,'Ancient Art of Main Tanking',0,0", $book->to_csv());
    }
}
