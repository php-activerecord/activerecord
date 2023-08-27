<?php

use ActiveRecord\Connection;
use ActiveRecord\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

// Only use this to test static methods in Connection that are not specific
// to any database adapter.

class ConnectionTest extends TestCase
{
    public function test_connection_info_from_should_throw_exception_when_no_host()
    {
        $this->expectException(DatabaseException::class);
        ActiveRecord\Connection::parse_connection_url('mysql://user:pass@');
    }

    public function test_connection_info()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://user:pass@127.0.0.1:3306/dbname');
        $this->assertEquals('mysql', $info->protocol);
        $this->assertEquals('user', $info->user);
        $this->assertEquals('pass', $info->pass);
        $this->assertEquals('127.0.0.1', $info->host);
        $this->assertEquals(3306, $info->port);
        $this->assertEquals('dbname', $info->db);
    }

    public function test_gh_103_sqlite_connection_string_relative()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://../some/path/to/file.db');
        $this->assertEquals('../some/path/to/file.db', $info->host);
    }

    public function test_gh_103_sqlite_connection_string_absolute()
    {
        $this->expectException(DatabaseException::class);
        ActiveRecord\Connection::parse_connection_url('sqlite:///some/path/to/file.db');
    }

    public function test_gh_103_sqlite_connection_string_unix()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)');
        $this->assertEquals('/some/path/to/file.db', $info->host);

        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)/');
        $this->assertEquals('/some/path/to/file.db', $info->host);

        $info = ActiveRecord\Connection::parse_connection_url('sqlite://unix(/some/path/to/file.db)/dummy');
        $this->assertEquals('/some/path/to/file.db', $info->host);
    }

    public function test_gh_103_sqlite_connection_string_windows()
    {
        $info = ActiveRecord\Connection::parse_connection_url('sqlite://windows(c%3A/some/path/to/file.db)');
        $this->assertEquals('c:/some/path/to/file.db', $info->host);
    }

    public function test_parse_connection_url_with_unix_sockets()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://user:password@unix(/tmp/mysql.sock)/database');
        $this->assertEquals('/tmp/mysql.sock', $info->host);
    }

    public function test_parse_connection_url_with_decode_option()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://h%20az:h%40i@127.0.0.1/test?decode=true');
        $this->assertEquals('h az', $info->user);
        $this->assertEquals('h@i', $info->pass);
    }

    public function test_encoding()
    {
        $info = ActiveRecord\Connection::parse_connection_url('mysql://test:test@127.0.0.1/test?charset=utf8');
        $this->assertEquals('utf8', $info->charset);
    }
}
