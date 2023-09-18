<?php

use ActiveRecord\Column;
use ActiveRecord\WhereClause;

class MysqlAdapterTest extends AdapterTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp('mysql');
    }

    public function testSubstituteEscapesQuotes()
    {
        $a = new WhereClause('name = ? or name in( ? )', ["Tito's Guild", [1, "Tito's Guild"]]);
        $this->assertEquals("name = 'Tito\'s Guild' or name in( 1,'Tito\'s Guild' )", $a->to_s($this->connection, substitute: true));
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
        iterator_to_array($this->connection->query_and_fetch(
            $this->connection->limit('SELECT * FROM authors ORDER BY name ASC', 0, 1)));

        $this->assertTrue(false !== strpos($this->connection->last_query, 'LIMIT 1'));
    }
}
