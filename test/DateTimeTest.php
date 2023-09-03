<?php

use ActiveRecord\DatabaseException;
use ActiveRecord\DateTime;
use PHPUnit\Framework\TestCase;
use test\models\Author;

class DateTimeTest extends TestCase
{
    private $date;
    private $original_format;

    public function setUp(): void
    {
        $this->date = new DateTime();
        $this->original_format = DateTime::$DEFAULT_FORMAT;
    }

    public function tearDown(): void
    {
        DateTime::$DEFAULT_FORMAT = $this->original_format;
    }

    private function get_model()
    {
        try {
            $model = new Author();
        } catch (DatabaseException $e) {
            $this->markTestSkipped('failed to connect. ' . $e->getMessage());
        }

        return $model;
    }

    private function assert_dirtifies($method /* , method params, ... */)
    {
        $model = $this->get_model();
        $datetime = new DateTime();
        $datetime->attribute_of($model, 'some_date');

        $args = func_get_args();
        array_shift($args);

        call_user_func_array([$datetime, $method], $args);
        $this->assertArrayHasKey('some_date', $model->dirty_attributes());
    }

    public function testShouldFlagTheAttributeDirty()
    {
        $interval = new DateInterval('PT1S');
        $timezone = new DateTimeZone('America/New_York');
        $this->assert_dirtifies('setDate', 2001, 1, 1);
        $this->assert_dirtifies('setISODate', 2001, 1);
        $this->assert_dirtifies('setTime', 1, 1);
        $this->assert_dirtifies('setTimestamp', 1);
        $this->assert_dirtifies('setTimezone', $timezone);
        $this->assert_dirtifies('modify', '+1 day');
        $this->assert_dirtifies('add', $interval);
        $this->assert_dirtifies('sub', $interval);
    }

    public function testSetIsoDate()
    {
        $a = new \DateTime();
        $a->setISODate(2001, 1);

        $b = new DateTime();
        $b->setISODate(2001, 1);

        $this->assertEquals($a->format(DateTime::ATOM), $b->format(DateTime::ATOM));
    }

    public function testSetTime()
    {
        $a = new \DateTime();
        $a->setTime(1, 1);

        $b = new DateTime();
        $b->setTime(1, 1);

        $this->assertEquals($a->format(DateTime::ATOM), $b->format(DateTime::ATOM));
    }

    public function testGetFormatWithFriendly()
    {
        $this->assertEquals('Y-m-d H:i:s', DateTime::get_format('db'));
    }

    public function testGetFormatWithFormat()
    {
        $this->assertEquals('Y-m-d', DateTime::get_format('Y-m-d'));
    }

    public function testGetFormatWithNull()
    {
        $this->assertEquals(\DateTime::RFC2822, DateTime::get_format());
    }

    public function testFormat()
    {
        $this->assertTrue(is_string($this->date->format()));
        $this->assertTrue(is_string($this->date->format('Y-m-d')));
    }

    public function testFormatByFriendlyName()
    {
        $d = date(DateTime::get_format('db'));
        $this->assertEquals($d, $this->date->format('db'));
    }

    public function testFormatByCustomFormat()
    {
        $format = 'Y/m/d';
        $this->assertEquals(date($format), $this->date->format($format));
    }

    public function testFormatUsesDefault()
    {
        $d = date(DateTime::$FORMATS[DateTime::$DEFAULT_FORMAT]);
        $this->assertEquals($d, $this->date->format());
    }

    public function testAllFormats()
    {
        foreach (DateTime::$FORMATS as $name => $format) {
            $this->assertEquals(date($format), $this->date->format($name));
        }
    }

    public function testChangeDefaultFormatToFormatString()
    {
        DateTime::$DEFAULT_FORMAT = 'H:i:s';
        $this->assertEquals(date(DateTime::$DEFAULT_FORMAT), $this->date->format());
    }

    public function testChangeDefaultFormatToFriently()
    {
        DateTime::$DEFAULT_FORMAT = 'short';
        $this->assertEquals(date(DateTime::$FORMATS['short']), $this->date->format());
    }

    public function testToString()
    {
        $this->assertEquals(date(DateTime::get_format()), '' . $this->date);
    }

    public function testCreateFromFormatErrorHandling()
    {
        $this->expectException(AssertionError::class);
        DateTime::createFromFormat('H:i:s Y-d-m', '!!!');
    }

    public function testCreateFromFormatWithoutTz()
    {
        $d = DateTime::createFromFormat('H:i:s Y-d-m', '03:04:05 2000-02-01');
        $this->assertEquals(new DateTime('2000-01-02 03:04:05'), $d);
    }

    public function testCreateFromFormatWithTz()
    {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', '2000-02-01 03:04:05', new \DateTimeZone('Etc/GMT-10'));
        $d2 = new DateTime('2000-01-31 17:04:05');

        $this->assertEquals($d2->getTimestamp(), $d->getTimestamp());
    }

    public function testNativeDateTimeAttributeCopiesExactTz()
    {
        $dt = new \DateTime('', new \DateTimeZone('America/New_York'));
        $model = $this->get_model();

        // Test that the data transforms without modification
        $model->assign_attribute('updated_at', $dt);
        $dt2 = $model->read_attribute('updated_at');

        $this->assertEquals($dt->getTimestamp(), $dt2->getTimestamp());
        $this->assertEquals($dt->getTimeZone(), $dt2->getTimeZone());
        $this->assertEquals($dt->getTimeZone()->getName(), $dt2->getTimeZone()->getName());
    }

    public function testArDateTimeAttributeCopiesExactTz()
    {
        $dt = new DateTime('', new \DateTimeZone('America/New_York'));
        $model = $this->get_model();

        // Test that the data transforms without modification
        $model->assign_attribute('updated_at', $dt);
        $dt2 = $model->read_attribute('updated_at');

        $this->assertEquals($dt->getTimestamp(), $dt2->getTimestamp());
        $this->assertEquals($dt->getTimeZone(), $dt2->getTimeZone());
        $this->assertEquals($dt->getTimeZone()->getName(), $dt2->getTimeZone()->getName());
    }

    public function testClone()
    {
        $model = $this->get_model();
        $model_attribute = 'some_date';

        $datetime = new DateTime();
        $datetime->attribute_of($model, $model_attribute);

        $cloned_datetime = clone $datetime;

        // Assert initial state
        $this->assertFalse($model->attribute_is_dirty($model_attribute));

        $cloned_datetime->add(new DateInterval('PT1S'));

        // Assert that modifying the cloned object didn't flag the model
        $this->assertFalse($model->attribute_is_dirty($model_attribute));

        $datetime->add(new DateInterval('PT1S'));

        // Assert that modifying the model-attached object did flag the model
        $this->assertTrue($model->attribute_is_dirty($model_attribute));

        // Assert that the dates are equal but not the same instance
        $this->assertEquals($datetime, $cloned_datetime);
        $this->assertNotSame($datetime, $cloned_datetime);
    }
}
