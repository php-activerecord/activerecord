<?php

use ActiveRecord\Column;
use ActiveRecord\Config;
use ActiveRecord\ConnectionManager;
use ActiveRecord\WhereClause;
use test\models\ValueStoreValidations;

class MysqlAdapterTest extends AdapterTestCase
{
    public function setUp($connection_name=null): void
    {
        parent::setUp('mysql');
    }

    public function testSubstituteEscapesQuotes()
    {
        $a = new WhereClause('name = ? or name in( ? )', ["Tito's Guild", [1, "Tito's Guild"]]);
        $this->assertEquals("name = 'Tito\'s Guild' or name in( 1,'Tito\'s Guild' )", $a->to_s(ConnectionManager::get_connection(), substitute: true));
    }

    public function testValidatesUniquenessOfWorksWithMysqlReservedWordAsColumnName()
    {
        ValueStoreValidations::create(['key' => 'GA_KEY', 'value' => 'UA-1234567-1']);
        $valuestore = ValuestoreValidations::create(['key' => 'GA_KEY', 'value' => 'UA-1234567-2']);

        $this->assertEquals(['Key must be unique'], $valuestore->errors->full_messages());
        $this->assertEquals(1, ValuestoreValidations::where('`key`= ?', 'GA_KEY')->count());
    }

    public function testEnum()
    {
        $author_columns = ConnectionManager::get_connection()->columns('authors');
        $this->assertEquals('enum', $author_columns['some_enum']->raw_type);
        $this->assertEquals(Column::STRING, $author_columns['some_enum']->type);
        $this->assertSame(null, $author_columns['some_enum']->length);
    }

    public function testSetCharset()
    {
        $connection_string = Config::instance()->get_default_connection_string();
        $conn = ActiveRecord\Connection::instance($connection_string . '?charset=utf8');
        $this->assertEquals('SET NAMES ?', $conn->last_query);
    }

    public function testLimitWithNullOffsetDoesNotContainOffset()
    {
        iterator_to_array(ConnectionManager::get_connection()->query_and_fetch(
            ConnectionManager::get_connection()->limit('SELECT * FROM authors ORDER BY name ASC', 0, 1)));

        $this->assertTrue(false !== strpos(ConnectionManager::get_connection()->last_query, 'LIMIT 1'));
    }
}
