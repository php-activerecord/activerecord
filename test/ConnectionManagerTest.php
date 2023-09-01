<?php

use ActiveRecord\Config;
use ActiveRecord\ConnectionManager;

class ConnectionManagerTest extends DatabaseTestCase
{
    public function test_get_connection_with_null_connection()
    {
        $this->assertNotNull(ConnectionManager::get_connection(null));
        $this->assertNotNull(ConnectionManager::get_connection());
    }

    public function test_get_connection()
    {
        $this->assertNotNull(ConnectionManager::get_connection('mysql'));
    }

    public function test_get_connection_uses_existing_object()
    {
        $a = ConnectionManager::get_connection('mysql');
        $this->assertSame($a === ConnectionManager::get_connection('mysql'), true);
    }

    public function test_gh_91_get_connection_with_null_connection_is_always_default()
    {
        $conn_one = ConnectionManager::get_connection('mysql');
        $conn_two = ConnectionManager::get_connection();
        $conn_three = ConnectionManager::get_connection('mysql');
        $conn_four = ConnectionManager::get_connection();

        $this->assertSame($conn_one, $conn_three);
        $this->assertSame($conn_two, $conn_three);
        $this->assertSame($conn_four, $conn_three);
    }
}
