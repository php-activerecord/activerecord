<?php

use ActiveRecord\ConnectionManager;
use ActiveRecord\Table;
use test\models\Author;

class PgsqlAdapterTest extends AdapterTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp('pgsql');
    }

    public function testInsertId()
    {
        static::resetTableData();
        ConnectionManager::get_connection()->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),'name')");
        $this->assertTrue(ConnectionManager::get_connection()->insert_id('authors_author_id_seq') > 0);
    }

    public function testSequenceWasSet()
    {
        $table = Table::load(Author::class);
        $this->assertEquals(
            ConnectionManager::get_connection()->init_sequence_name($table),
            $table->sequence
        );
    }

    public function testToSql(): void
    {
        $this->assert_sql_includes(
            'SELECT * FROM authors WHERE "mixedCaseField" = ? ORDER BY name',
            \test\models\Author::where('mixedCaseField = ?', 'The Art of Main Tanking')
                ->order('name')->to_sql()
        );
    }

    public function testInsertIdWithParams()
    {
        $x = ['name'];
        ConnectionManager::get_connection()->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),?)", $x);
        $this->assertTrue(ConnectionManager::get_connection()->insert_id('authors_author_id_seq') > 0);
    }

    public function testInsertIdShouldReturnExplicitlyInsertedId()
    {
        ConnectionManager::get_connection()->query('INSERT INTO authors(author_id,name) VALUES(99,\'name\')');
        $this->assertTrue(ConnectionManager::get_connection()->insert_id('authors_author_id_seq') > 0);
    }

    public function testSetCharset()
    {
        $connection_string = ActiveRecord\Config::instance()->get_default_connection_string();
        $conn = ActiveRecord\Connection::instance($connection_string . '?charset=utf8');
        $this->assertEquals("SET NAMES 'utf8'", $conn->last_query);
    }

    public function testGh96ColumnsNotDuplicatedByIndex()
    {
        $this->assertEquals(3, ConnectionManager::get_connection()->query_column_info('user_newsletters')->rowCount());
    }
}
