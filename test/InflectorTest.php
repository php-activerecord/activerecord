<?php

use ActiveRecord\Inflector;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/Inflector.php';

class InflectorTest extends TestCase
{

    public function test_underscorify()
    {
        $this->assertEquals('rm__name__bob', Inflector::variablize('rm--name  bob'));
        $this->assertEquals('One_Two_Three', Inflector::underscorify('OneTwoThree'));
    }

    public function test_tableize()
    {
        $this->assertEquals('angry_people', Inflector::tableize('AngryPerson'));
        $this->assertEquals('my_sqls', Inflector::tableize('MySQL'));
    }

    public function test_keyify()
    {
        $this->assertEquals('building_type_id', Inflector::keyify('BuildingType'));
    }
}
