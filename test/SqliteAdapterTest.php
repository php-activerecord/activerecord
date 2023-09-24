<?php

use ActiveRecord\Connection;
use ActiveRecord\ConnectionManager;
use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\ConnectionException;

class SqliteAdapterTest extends AdapterTestCase
{
    public function setUp(string $connection_name=null): void
    {
        parent::setUp('sqlite');
    }

    public function tearDown(): void
    {
        @unlink(self::InvalidDb);
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        @unlink(static::$db);
    }

    public function testConnectToInvalidDatabaseShouldNotCreateDbFile()
    {
        try {
            Connection::instance('sqlite://' . self::InvalidDb);
            $this->assertFalse(true);
        } catch (ConnectionException $e) {
            $this->assertFalse(file_exists(__DIR__ . '/' . self::InvalidDb));
        }
    }

    public function testSetEncoding()
    {
        $this->expectException(ActiveRecordException::class);
        ConnectionManager::get_connection()->set_encoding('utf8');
    }

    public function testLimitWith0OffsetDoesNotContainOffset()
    {
        $sql = ConnectionManager::get_connection()->limit('SELECT * FROM authors ORDER BY name ASC', 0, 1);
        iterator_to_array(ConnectionManager::get_connection()->query_and_fetch($sql));

        $this->assertTrue(false !== strpos(ConnectionManager::get_connection()->last_query, 'LIMIT 1'));
    }

    public function testSqliteAdapterAutoincrement()
    {
        // defined in lowercase: id integer not null primary key
        $columns = ConnectionManager::get_connection()->columns('awesome_people');
        $this->assertTrue($columns['id']->auto_increment);

        // defined in uppercase: `amenity_id` INTEGER NOT NULL PRIMARY KEY
        $columns = ConnectionManager::get_connection()->columns('amenities');
        $this->assertTrue($columns['amenity_id']->auto_increment);

        // defined using int: `rm-id` INT NOT NULL
        $columns = ConnectionManager::get_connection()->columns('`rm-bldg`');
        $this->assertFalse($columns['rm-id']->auto_increment);

        // defined using int: id INT NOT NULL PRIMARY KEY
        $columns = ConnectionManager::get_connection()->columns('hosts');
        $this->assertTrue($columns['id']->auto_increment);
    }

    public function testDatetimeToString()
    {
        $datetime = '2009-01-01 01:01:01';
        $this->assertEquals($datetime, ConnectionManager::get_connection()->datetime_string(date_create($datetime)));
    }

    public function testDateToString()
    {
        $datetime = '2009-01-01';
        $this->assertEquals($datetime, ConnectionManager::get_connection()->date_string(date_create($datetime)));
    }

    // not supported
    public function testConnectWithPort()
    {
        $this->expectNotToPerformAssertions();
    }
}
