<?php

class PgsqlAdapterTest extends AdapterTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp('pgsql');
    }

    public function testInsertId()
    {
        $this->connection->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),'name')");
        $this->assertTrue($this->connection->insert_id('authors_author_id_seq') > 0);
    }

    public function testInsertIdWithParams()
    {
        $x = ['name'];
        $this->connection->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),?)", $x);
        $this->assertTrue($this->connection->insert_id('authors_author_id_seq') > 0);
    }

    public function testInsertIdShouldReturnExplicitlyInsertedId()
    {
        $this->connection->query('INSERT INTO authors(author_id,name) VALUES(99,\'name\')');
        $this->assertTrue($this->connection->insert_id('authors_author_id_seq') > 0);
    }

    public function testSetCharset()
    {
        $connection_string = ActiveRecord\Config::instance()->get_connection($this->connection_name);
        $conn = ActiveRecord\Connection::instance($connection_string . '?charset=utf8');
        $this->assertEquals("SET NAMES 'utf8'", $conn->last_query);
    }

    public function testGh96ColumnsNotDuplicatedByIndex()
    {
        $this->assertEquals(3, $this->connection->query_column_info('user_newsletters')->rowCount());
    }
}
