<?php

use ActiveRecord\Adapter\SqliteAdapter;
use ActiveRecord\Column;
use ActiveRecord\Exception\ConnectionException;
use ActiveRecord\Exception\DatabaseException;

abstract class AdapterTestCase extends DatabaseTestCase
{
    public const InvalidDb = '__1337__invalid_db__';

    public function setUp(string $connection_name=null): void
    {
        if (($connection_name && !in_array($connection_name, PDO::getAvailableDrivers()))
            || 'skip' == ActiveRecord\Config::instance()->get_connection($connection_name)) {
            $this->markTestSkipped($connection_name . ' drivers are not present');
        }

        parent::setUp($connection_name);
    }

    public function testIHasADefaultPortUnlessImSqlite()
    {
        if ($this->connection instanceof SqliteAdapter) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $c = $this->connection;
        $this->assertTrue($c::$DEFAULT_PORT > 0);
    }

    public function testShouldSetAdapterVariables()
    {
        $this->assertNotNull($this->connection->protocol);
    }

    public function testNullConnectionStringUsesDefaultConnection()
    {
        $this->assertNotNull(ActiveRecord\Connection::instance(null));
        $this->assertNotNull(ActiveRecord\Connection::instance(''));
        $this->assertNotNull(ActiveRecord\Connection::instance());
    }

    public function testInvalidConnectionProtocol()
    {
        $this->expectException(DatabaseException::class);
        ActiveRecord\Connection::instance('terribledb://user:pass@host/db');
    }

    public function testNoHostConnection()
    {
        $this->expectException(DatabaseException::class);
        if (!$GLOBALS['slow_tests']) {
            throw new DatabaseException('');
        }
        ActiveRecord\Connection::instance("{$this->connection->protocol}://user:pass");
    }

    public function testConnectionFailedInvalidHost()
    {
        $this->expectException(DatabaseException::class);
        if (!$GLOBALS['slow_tests']) {
            throw new DatabaseException('');
        }
        ActiveRecord\Connection::instance("{$this->connection->protocol}://user:pass/1.1.1.1/db");
    }

    public function testConnectionFailed()
    {
        $this->expectException(ConnectionException::class);
        ActiveRecord\Connection::instance("{$this->connection->protocol}://baduser:badpass@127.0.0.1/db");
    }

    public function testConnectFailed()
    {
        $this->expectException(ConnectionException::class);
        ActiveRecord\Connection::instance("{$this->connection->protocol}://zzz:zzz@127.0.0.1/test");
    }

    public function testConnectWithPort()
    {
        $this->expectNotToPerformAssertions();
        $config = ActiveRecord\Config::instance();
        $name = $this->connection_name;
        $url = parse_url($config->get_connection($name));
        $conn = $this->connection;
        $port = $conn::$DEFAULT_PORT;

        $connection_string = "{$url['scheme']}://" . $url['user'] ?? '';
        if (isset($url['pass'])) {
            $connection_string =  "{$connection_string}:{$url['pass']}";
        }

        $connection_string = "{$connection_string}@{$url['host']}:" . $port . $url['path'] ?? '';

        if ('sqlite' != $this->connection->protocol) {
            ActiveRecord\Connection::instance($connection_string);
        }
    }

    public function testConnectToInvalidDatabase()
    {
        $this->expectException(ConnectionException::class);
        ActiveRecord\Connection::instance("{$this->connection->protocol}://test:test@127.0.0.1/" . self::InvalidDb);
    }

    public function testDateTimeType()
    {
        $columns = $this->connection->columns('authors');
        $this->assertEquals('datetime', $columns['created_at']->raw_type);
        $this->assertEquals(Column::DATETIME, $columns['created_at']->type);
        $this->assertTrue($columns['created_at']->length > 0);
    }

    public function testDate()
    {
        $columns = $this->connection->columns('authors');
        $this->assertEquals('date', $columns['some_Date']->raw_type);
        $this->assertEquals(Column::DATE, $columns['some_Date']->type);
        $this->assertTrue($columns['some_Date']->length >= 7);
    }

    public function testColumnsNoInflectionOnHashKey()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertTrue(array_key_exists('author_id', $author_columns));
    }

    public function testColumnsNullable()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertFalse($author_columns['author_id']->nullable);
        $this->assertTrue($author_columns['parent_author_id']->nullable);
    }

    public function testColumnsPk()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertTrue($author_columns['author_id']->pk);
        $this->assertFalse($author_columns['parent_author_id']->pk);
    }

    public function testColumnsSequence()
    {
        if ($this->connection->supports_sequences()) {
            $author_columns = $this->connection->columns('authors');
            $this->assertEquals('authors_author_id_seq', $author_columns['author_id']->sequence);
        } else {
            $this->expectNotToPerformAssertions();
        }
    }

    public function testColumnsDefault()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertEquals('default_name', $author_columns['name']->default);
    }

    public function testColumnsType()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertEquals('varchar', substr($author_columns['name']->raw_type, 0, 7));
        $this->assertEquals(Column::STRING, $author_columns['name']->type);
        $this->assertEquals(25, $author_columns['name']->length);
    }

    public function testColumnsText()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertEquals('text', $author_columns['some_text']->raw_type);
        $this->assertEquals(null, $author_columns['some_text']->length);
    }

    public function testColumnsTime()
    {
        $author_columns = $this->connection->columns('authors');
        $this->assertEquals('time', $author_columns['some_time']->raw_type);
        $this->assertEquals(Column::TIME, $author_columns['some_time']->type);
    }

    public function testQuery()
    {
        $sth = $this->connection->query('SELECT * FROM authors');

        while ($row = $sth->fetch()) {
            $this->assertNotNull($row);
        }

        $sth = $this->connection->query('SELECT * FROM authors WHERE author_id=1');
        $row = $sth->fetch();
        $this->assertEquals('Tito', $row['name']);
    }

    public function testInvalidQuery()
    {
        $this->expectException(DatabaseException::class);
        $this->connection->query('alsdkjfsdf');
    }

    public function testFetch()
    {
        $sth = $this->connection->query('SELECT * FROM authors WHERE author_id IN(1,2,3)');
        $i = 0;
        $ids = [];

        while ($row = $sth->fetch()) {
            ++$i;
            $ids[] = $row['author_id'];
        }

        $this->assertEquals(3, $i);
        $this->assertEquals([1, 2, 3], $ids);
    }

    public function testQueryWithParams()
    {
        $x=['Bill Clinton', 'Tito'];
        $sth = $this->connection->query('SELECT * FROM authors WHERE name IN(?,?) ORDER BY name DESC', $x);
        $row = $sth->fetch();
        $this->assertEquals('Tito', $row['name']);

        $row = $sth->fetch();
        $this->assertEquals('Bill Clinton', $row['name']);

        $row = $sth->fetch();
        $this->assertEquals(null, $row);
    }

    public function testInsertIdShouldReturnExplicitlyInsertedId()
    {
        $this->connection->query('INSERT INTO authors(author_id,name) VALUES(99,\'name\')');
        $this->assertTrue($this->connection->insert_id() > 0);
    }

    public function testInsertId()
    {
        $this->connection->query("INSERT INTO authors(name) VALUES('name')");
        $this->assertTrue($this->connection->insert_id() > 0);
    }

    public function testInsertIdWithParams()
    {
        $x = ['name'];
        $this->connection->query('INSERT INTO authors(name) VALUES(?)', $x);
        $this->assertTrue($this->connection->insert_id() > 0);
    }

    public function testInflection()
    {
        $columns = $this->connection->columns('authors');
        $this->assertEquals('parent_author_id', $columns['parent_author_id']->inflected_name);
    }

    public function testEscape()
    {
        $s = "Bob's";
        $this->assertNotEquals($s, $this->connection->escape($s));
    }

    public function testColumnsx()
    {
        $columns = $this->connection->columns('authors');
        $names = ['author_id', 'parent_author_id', 'name', 'updated_at', 'created_at', 'some_Date', 'some_time', 'some_text', 'encrypted_password', 'mixedCaseField'];

        foreach ($names as $field) {
            $this->assertTrue(array_key_exists($field, $columns));
        }

        $this->assertEquals(true, $columns['author_id']->pk);
        $this->assertEquals('int', $columns['author_id']->raw_type);
        $this->assertEquals(Column::INTEGER, $columns['author_id']->type);
        $c = $columns['author_id'];
        $this->assertTrue($columns['name']->length > 1);
        $this->assertFalse($columns['author_id']->nullable);

        $this->assertEquals(false, $columns['parent_author_id']->pk);
        $this->assertTrue($columns['parent_author_id']->nullable);

        $this->assertEquals('varchar', substr($columns['name']->raw_type, 0, 7));
        $this->assertEquals(Column::STRING, $columns['name']->type);
        $this->assertEquals(25, $columns['name']->length);
    }

    public function testColumnsDecimal()
    {
        $columns = $this->connection->columns('books');
        $this->assertEquals(Column::DECIMAL, $columns['special']->type);
        $this->assertTrue($columns['special']->length >= 10);
    }

    private function limit(int $offset = 0, int $limit = 0)
    {
        $sql = 'SELECT * FROM authors ORDER BY name ASC';
        $ret = iterator_to_array($this->connection->query_and_fetch($this->connection->limit($sql, $offset, $limit)));

        return ActiveRecord\collect($ret, 'author_id');
    }

    public function testLimit()
    {
        $this->assertEquals([2, 1], $this->limit(1, 2));
    }

    public function testLimitToFirstRecord()
    {
        $this->assertEquals([3], $this->limit(0, 1));
    }

    public function testLimitToLastRecord()
    {
        $this->assertEquals([1], $this->limit(2, 1));
    }

    public function testLimitWithNullOffset()
    {
        $this->assertEquals([3], $this->limit(0, 1));
    }

    public function testLimitWithDefaults()
    {
        $this->assertEquals([], $this->limit());
    }

    public function testFetchNoResults()
    {
        $sth = $this->connection->query('SELECT * FROM authors WHERE author_id=65534');
        $this->assertEquals(null, $sth->fetch());
    }

    public function testTables()
    {
        $this->assertTrue(count($this->connection->tables()) > 0);
    }

    public function testQueryColumnInfo()
    {
        $this->assertGreaterThan(0, count((array) $this->connection->query_column_info('authors')));
    }

    public function testQueryTableInfo()
    {
        $this->assertGreaterThan(0, count((array) $this->connection->query_for_tables()));
    }

    public function testQueryTableInfoMustReturnOneField()
    {
        $sth = $this->connection->query_for_tables();
        $this->assertEquals(1, count((array) $sth->fetch()));
    }

    public function testTransactionCommit()
    {
        $original = $this->connection->query_and_fetch_one('select count(*) from authors');

        $this->connection->transaction();
        $this->connection->query("insert into authors(author_id,name) values(9999,'blahhhhhhhh')");
        $this->connection->commit();

        $this->assertEquals($original+1, $this->connection->query_and_fetch_one('select count(*) from authors'));
    }

    public function testTransactionRollback()
    {
        $original = $this->connection->query_and_fetch_one('select count(*) from authors');

        $this->connection->transaction();
        $this->connection->query("insert into authors(author_id,name) values(9999,'blahhhhhhhh')");
        $this->connection->rollback();

        $this->assertEquals($original, $this->connection->query_and_fetch_one('select count(*) from authors'));
    }

    public function testShowMeAUsefulPdoExceptionMessage()
    {
        try {
            $this->connection->query('select * from an_invalid_column');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(1, preg_match('/(an_invalid_column)|(exist)/', $e->getMessage()));
        }
    }

    public function testQuoteNameDoesNotOverQuote()
    {
        $c = $this->connection;
        $q = $c::$QUOTE_CHARACTER;
        $qn = function ($s) use ($c) { return $c->quote_name($s); };

        $this->assertEquals("{$q}string", $qn("{$q}string"));
        $this->assertEquals("string{$q}", $qn("string{$q}"));
        $this->assertEquals("{$q}string{$q}", $qn("{$q}string{$q}"));
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
}
