<?php

use ActiveRecord as AR;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    private $object_array;
    private $array_hash;

    public function setUp(): void
    {
        $this->object_array = [null, null];
        $this->object_array[0] = new stdClass();
        $this->object_array[0]->a = '0a';
        $this->object_array[0]->b = '0b';
        $this->object_array[1] = new stdClass();
        $this->object_array[1]->a = '1a';
        $this->object_array[1]->b = '1b';

        $this->array_hash = [
            ['a' => '0a', 'b' => '0b'],
            ['a' => '1a', 'b' => '1b']];
    }

    public function testCollectWithArrayOfObjectsUsingClosure()
    {
        $this->assertEquals(['0a', '1a'], AR\collect($this->object_array, function ($obj) { return $obj->a; }));
    }

    public function testCollectWithArrayOfObjectsUsingString()
    {
        $this->assertEquals(['0a', '1a'], AR\collect($this->object_array, 'a'));
    }

    public function testCollectWithArrayHashUsingClosure()
    {
        $this->assertEquals(['0a', '1a'], AR\collect($this->array_hash, function ($item) { return $item['a']; }));
    }

    public function testUncountable()
    {
        $this->assertEquals('sheep', AR\Utils::pluralize('sheep'));
        $this->assertEquals('sheep', AR\Utils::singularize('sheep'));
    }

    public function testCollectWithArrayHashUsingString()
    {
        $this->assertEquals(['0a', '1a'], AR\collect($this->array_hash, 'a'));
    }

    public function testArrayFlatten()
    {
        $this->assertEquals([], AR\array_flatten([]));
        $this->assertEquals([1], AR\array_flatten([1]));
        $this->assertEquals([1], AR\array_flatten([[1]]));
        $this->assertEquals([1, 2], AR\array_flatten([[1, 2]]));
        $this->assertEquals([1, 2], AR\array_flatten([[1], 2]));
        $this->assertEquals([1, 2], AR\array_flatten([1, [2]]));
        $this->assertEquals([1, 2, 3], AR\array_flatten([1, [2], 3]));
        $this->assertEquals([1, 2, 3, 4], AR\array_flatten([1, [2, 3], 4]));
        $this->assertEquals([1, 2, 3, 4, 5, 6], AR\array_flatten([1, [2, 3], 4, [5, 6]]));
    }

    public function testAll()
    {
        $this->assertTrue(AR\all(null, [null, null]));
        $this->assertTrue(AR\all(1, [1, 1]));
        $this->assertFalse(AR\all(1, [1, '1']));
        $this->assertFalse(AR\all(null, ['', null]));
    }

    public function testClassify()
    {
        $bad_class_names = ['ubuntu_rox', 'stop_the_Snake_Case', 'CamelCased', 'camelCased'];
        $good_class_names = ['UbuntuRox', 'StopTheSnakeCase', 'CamelCased', 'CamelCased'];

        $class_names = [];
        foreach ($bad_class_names as $s) {
            $class_names[] = AR\classify($s);
        }

        $this->assertEquals($class_names, $good_class_names);
    }

    public function testClassifySingularize()
    {
        $bad_class_names = ['events', 'stop_the_Snake_Cases', 'angry_boxes', 'Mad_Sheep_herders', 'happy_People'];
        $good_class_names = ['Event', 'StopTheSnakeCase', 'AngryBox', 'MadSheepHerder', 'HappyPerson'];

        $class_names = [];
        foreach ($bad_class_names as $s) {
            $class_names[] = AR\classify($s, true);
        }

        $this->assertEquals($class_names, $good_class_names);
    }

    public function testSingularize()
    {
        $this->assertEquals('order_status', AR\Utils::singularize('order_status'));
        $this->assertEquals('order_status', AR\Utils::singularize('order_statuses'));
        $this->assertEquals('os_type', AR\Utils::singularize('os_type'));
        $this->assertEquals('os_type', AR\Utils::singularize('os_types'));
        $this->assertEquals('photo', AR\Utils::singularize('photos'));
        $this->assertEquals('pass', AR\Utils::singularize('pass'));
        $this->assertEquals('pass', AR\Utils::singularize('passes'));
    }

    public function testWrapStringsInArrays()
    {
        $x = ['1', ['2']];
        $this->assertEquals([['1'], ['2']], ActiveRecord\wrap_values_in_arrays($x));

        $x = '1';
        $this->assertEquals([['1']], ActiveRecord\wrap_values_in_arrays($x));
    }
}
