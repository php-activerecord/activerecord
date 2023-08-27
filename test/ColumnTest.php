<?php

use ActiveRecord\Column;
use ActiveRecord\DatabaseException;
use ActiveRecord\DateTime;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function setUp($connection_name = null): void
    {
        $this->column = new Column();
        try {
            $this->conn = ActiveRecord\ConnectionManager::get_connection(ActiveRecord\Config::instance()->get_default_connection());
        } catch (DatabaseException $e) {
            $this->mark_test_skipped('failed to connect using default connection. ' . $e->getMessage());
        }
    }

    public function assert_mapped_type($type, $raw_type)
    {
        $this->column->raw_type = $raw_type;
        $this->assertEquals($type, $this->column->map_raw_type());
    }

    public function assert_cast($type, $casted_value, $original_value)
    {
        $this->column->type = $type;
        $value = $this->column->cast($original_value, $this->conn);

        if (null != $original_value && (Column::DATETIME == $type || Column::DATE == $type)) {
            $this->assertTrue($value instanceof DateTime);
        } else {
            $this->assertSame($casted_value, $value);
        }
    }

    public function test_map_raw_type_dates()
    {
        $this->assert_mapped_type(Column::DATETIME, 'datetime');
        $this->assert_mapped_type(Column::DATE, 'date');
    }

    public function test_map_raw_type_integers()
    {
        $this->assert_mapped_type(Column::INTEGER, 'integer');
        $this->assert_mapped_type(Column::INTEGER, 'int');
        $this->assert_mapped_type(Column::INTEGER, 'tinyint');
        $this->assert_mapped_type(Column::INTEGER, 'smallint');
        $this->assert_mapped_type(Column::INTEGER, 'mediumint');
        $this->assert_mapped_type(Column::INTEGER, 'bigint');
    }

    public function test_map_raw_type_decimals()
    {
        $this->assert_mapped_type(Column::DECIMAL, 'float');
        $this->assert_mapped_type(Column::DECIMAL, 'double');
        $this->assert_mapped_type(Column::DECIMAL, 'numeric');
        $this->assert_mapped_type(Column::DECIMAL, 'dec');
    }

    public function test_map_raw_type_strings()
    {
        $this->assert_mapped_type(Column::STRING, 'string');
        $this->assert_mapped_type(Column::STRING, 'varchar');
        $this->assert_mapped_type(Column::STRING, 'text');
    }

    public function test_map_raw_type_default_to_string()
    {
        $this->assert_mapped_type(Column::STRING, 'bajdslfjasklfjlksfd');
    }

    public function test_map_raw_type_changes_integer_to_int()
    {
        $this->column->raw_type = 'integer';
        $this->column->map_raw_type();
        $this->assertEquals('int', $this->column->raw_type);
    }

    public function test_cast()
    {
        $datetime = new DateTime('2001-01-01');
        $this->assert_cast(Column::INTEGER, 1, '1');
        $this->assert_cast(Column::INTEGER, 1, '1.5');
        $this->assert_cast(Column::DECIMAL, 1.5, '1.5');
        $this->assert_cast(Column::DATETIME, $datetime, '2001-01-01');
        $this->assert_cast(Column::DATE, $datetime, '2001-01-01');
        $this->assert_cast(Column::DATE, $datetime, $datetime);
        $this->assert_cast(Column::STRING, 'bubble tea', 'bubble tea');
        $this->assert_cast(Column::INTEGER, 4294967295, '4294967295');
        $this->assert_cast(Column::INTEGER, '18446744073709551615', '18446744073709551615');

        // 32 bit
        if (PHP_INT_SIZE === 4) {
            $this->assert_cast(Column::INTEGER, '2147483648', (((float) PHP_INT_MAX) + 1));
        }
        // 64 bit
        elseif (PHP_INT_SIZE === 8) {
            $this->assert_cast(Column::INTEGER, '9223372036854775808', (((float) PHP_INT_MAX) + 1));
        }
    }

    public function test_cast_leave_null_alone()
    {
        $types = [
            Column::STRING,
            Column::INTEGER,
            Column::DECIMAL,
            Column::DATETIME,
            Column::DATE];

        foreach ($types as $type) {
            $this->assert_cast($type, null, null);
        }
    }

    public function test_empty_and_null_date_strings_should_return_null()
    {
        $column = new Column();
        $column->type = Column::DATE;
        $this->assertEquals(null, $column->cast(null, $this->conn));
        $this->assertEquals(null, $column->cast('', $this->conn));
    }

    public function test_empty_and_null_datetime_strings_should_return_null()
    {
        $column = new Column();
        $column->type = Column::DATETIME;
        $this->assertEquals(null, $column->cast(null, $this->conn));
        $this->assertEquals(null, $column->cast('', $this->conn));
    }

    public function test_native_date_time_attribute_copies_exact_tz()
    {
        $dt = new \DateTime('', new \DateTimeZone('America/New_York'));

        $column = new Column();
        $column->type = Column::DATETIME;

        $dt2 = $column->cast($dt, $this->conn);

        $this->assertEquals($dt->getTimestamp(), $dt2->getTimestamp());
        $this->assertEquals($dt->getTimeZone(), $dt2->getTimeZone());
        $this->assertEquals($dt->getTimeZone()->getName(), $dt2->getTimeZone()->getName());
    }

    public function test_ar_date_time_attribute_copies_exact_tz()
    {
        $dt = new DateTime('', new \DateTimeZone('America/New_York'));

        $column = new Column();
        $column->type = Column::DATETIME;

        $dt2 = $column->cast($dt, $this->conn);

        $this->assertEquals($dt->getTimestamp(), $dt2->getTimestamp());
        $this->assertEquals($dt->getTimeZone(), $dt2->getTimeZone());
        $this->assertEquals($dt->getTimeZone()->getName(), $dt2->getTimeZone()->getName());
    }
}
