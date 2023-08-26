<?php

use PhpUnit\Framework\TestCase;

class SnakeCase_PHPUnit_Framework_TestCase extends TestCase
{
    public function setUp(): void
    {
        if (method_exists($this, 'set_up')) {
            call_user_func_array([$this, 'set_up'], func_get_args());
        }
    }

    public function tearDown(): void
    {
        if (method_exists($this, 'tear_down')) {
            call_user_func_array([$this, 'tear_down'], func_get_args());
        }
    }

    private function setup_assert_keys($args)
    {
        $last = count($args)-1;
        $keys = array_slice($args, 0, $last);
        $array = $args[$last];

        return [$keys, $array];
    }

    public function assert_has_keys(/* $keys..., $array */)
    {
        list($keys, $array) = $this->setup_assert_keys(func_get_args());

        $this->assertNotNull($array, 'Array was null');

        foreach ($keys as $name) {
            $this->assertArrayHasKey($name, $array);
        }
    }

    public function assert_doesnt_has_keys(/* $keys..., $array */)
    {
        list($keys, $array) = $this->setup_assert_keys(func_get_args());

        foreach ($keys as $name) {
            $this->assertArrayNotHasKey($name, $array);
        }
    }

    public function assert_is_a($expected_class, $object)
    {
        $this->assertEquals($expected_class, get_class($object));
    }

    public function assert_datetime_equals($expected, $actual)
    {
        $this->assertEquals($expected->format(DateTime::ISO8601), $actual->format(DateTime::ISO8601));
    }
}
