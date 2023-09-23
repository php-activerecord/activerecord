<?php

use ActiveRecord\Connection;
use ActiveRecord\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

// Only use this to test static methods in Connection that are not specific
// to any database adapter.

class ConnectionTest extends TestCase
{
    public function testConnectionInfoFromShouldThrowExceptionWhenNoHost()
    {
        $this->expectException(DatabaseException::class);
        ActiveRecord\Connection::parse_connection_url('mysql://user:pass@');
    }

    public function testConnectionInfo()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://user:pass@127.0.0.1:3306/dbname');
        $this->assertEquals('mysql', $info['protocol']);
        $this->assertEquals('user', $info['user']);
        $this->assertEquals('pass', $info['pass']);
        $this->assertEquals('127.0.0.1', $info['host']);
        $this->assertEquals(3306, $info['port']);
        $this->assertEquals('dbname', $info['db']);
    }

    public function testGh103SqliteConnectionStringRelative()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://../some/path/to/file.db');
        $this->assertEquals('../some/path/to/file.db', $info['host']);
    }

    public function testGh103SqliteConnectionStringAbsolute()
    {
        $this->expectException(DatabaseException::class);
        ActiveRecord\Connection::parse_connection_url('sqlite:///some/path/to/file.db');
    }

    public function testGh103SqliteConnectionStringUnix()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)');
        $this->assertEquals('/some/path/to/file.db', $info['host']);

        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)/');
        $this->assertEquals('/some/path/to/file.db', $info['host']);

        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)/dummy');
        $this->assertEquals('/some/path/to/file.db', $info['host']);
    }

    public function testGh103SqliteConnectionStringWindows()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://windows(c%3A/some/path/to/file.db)');
        $this->assertEquals('c:/some/path/to/file.db', $info['host']);
    }

    public function testParseConnectionUrlWithUnixSockets()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://user:password@unix(/tmp/mysql.sock)/database');
        $this->assertEquals('/tmp/mysql.sock', $info['host']);

        $dsn = Connection::data_source_name($info);
        $this->assertStringContainsString('unix_socket=/tmp/mysql.sock', $dsn);
    }

    public function testParseConnectionUrlWithDecodeOption()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://h%20az:h%40i@127.0.0.1/test?decode=true');
        $this->assertEquals('h az', $info['user']);
        $this->assertEquals('h@i', $info['pass']);
    }

    public function testEncoding()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://test:test@127.0.0.1/test?charset=utf8');
        $this->assertEquals('utf8', $info['charset']);
    }

    public function testReestablishConnection()
    {
        $connection = \test\models\Book::reestablish_connection(true);
        $this->assertNotNull($connection);
    }
}
