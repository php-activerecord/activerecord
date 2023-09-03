<?php

use ActiveRecord\Column;

class MysqlAdapterTest extends AdapterTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp('mysql');
    }

    public function testEnum()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertEquals('enum', $author_columns['some_enum']->raw_type);
        $this->assertEquals(Column::STRING, $author_columns['some_enum']->type);
        $this->assertSame(null, $author_columns['some_enum']->length);
    }

    public function testSetCharset()
    {
        $connection_string = ActiveRecord\Config::instance()->get_connection($this->connection_name);
        $conn = ActiveRecord\Connection::instance($connection_string . '?charset=utf8');
        $this->assertEquals('SET NAMES ?', $conn->last_query);
    }

    public function testLimitWithNullOffsetDoesNotContainOffset()
    {
        $ret = [];
        $sql = 'SELECT * FROM authors ORDER BY name ASC';
        $this->connection->query_and_fetch($this->connection->limit($sql, 0, 1), function ($row) use (&$ret) { $ret[] = $row; });

        $this->assertTrue(false !== strpos($this->connection->last_query, 'LIMIT 1'));
    }
}
