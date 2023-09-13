<?php

use ActiveRecord\Connection;
use ActiveRecord\Exception\ConnectionException;
use test\models\Author;

class SqliteAdapterTest extends AdapterTestCase
{
    public function setUp(string $connection_name=null): void
    {
        parent::setUp('sqlite');
    }

    public function tearDown(): void
    {
        @unlink(self::InvalidDb);
    }

    public function testCanIterate()
    {
        $authors = Author::all();

        foreach ($authors as $author) {
            $this->assertInstanceOf(Author::class, $author);
        }

        foreach ($authors as $author) {
            $this->assertInstanceOf(Author::class, $author);
        }
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

    public function testLimitWith0OffsetDoesNotContainOffset()
    {
        $ret = [];
        $sql = 'SELECT * FROM authors ORDER BY name ASC';
        $this->connection->query_and_fetch($this->connection->limit($sql, 0, 1), function ($row) use (&$ret) { $ret[] = $row; });

        $this->assertTrue(false !== strpos($this->connection->last_query, 'LIMIT 1'));
    }

    public function testGh183SqliteadapterAutoincrement()
    {
        // defined in lowercase: id integer not null primary key
        $columns = $this->connection->columns('awesome_people');
        $this->assertTrue($columns['id']->auto_increment);

        // defined in uppercase: `amenity_id` INTEGER NOT NULL PRIMARY KEY
        $columns = $this->connection->columns('amenities');
        $this->assertTrue($columns['amenity_id']->auto_increment);

        // defined using int: `rm-id` INT NOT NULL
        $columns = $this->connection->columns('`rm-bldg`');
        $this->assertFalse($columns['rm-id']->auto_increment);

        // defined using int: id INT NOT NULL PRIMARY KEY
        $columns = $this->connection->columns('hosts');
        $this->assertTrue($columns['id']->auto_increment);
    }

    public function testDatetimeToString()
    {
        $datetime = '2009-01-01 01:01:01';
        $this->assertEquals($datetime, $this->connection->datetime_string(date_create($datetime)));
    }

    public function testDateToString()
    {
        $datetime = '2009-01-01';
        $this->assertEquals($datetime, $this->connection->date_string(date_create($datetime)));
    }

    // not supported
    public function testConnectWithPort()
    {
        $this->expectNotToPerformAssertions();
    }
}
