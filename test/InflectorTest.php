<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/Inflector.php';

class InflectorTest extends TestCase
{
    private $inflector;

    public function setUp(): void
    {
        $this->inflector = ActiveRecord\Inflector::instance();
    }

    public function testUnderscorify()
    {
        $this->assertEquals('rm__name__bob', $this->inflector->variablize('rm--name  bob'));
        $this->assertEquals('One_Two_Three', $this->inflector->underscorify('OneTwoThree'));
    }

    public function testTableize()
    {
        $this->assertEquals('angry_people', $this->inflector->tableize('AngryPerson'));
        $this->assertEquals('my_sqls', $this->inflector->tableize('MySQL'));
    }

    public function testKeyify()
    {
        $this->assertEquals('building_type_id', $this->inflector->keyify('BuildingType'));
    }
}
