<?php

use ActiveRecord\ConnectionManager;

class ConnectionManagerTest extends DatabaseTestCase
{
    public function testGetConnectionWithNullConnection()
    {
        $this->assertNotNull(ConnectionManager::get_connection(null));
        $this->assertNotNull(ConnectionManager::get_connection());
    }

    public function testGetConnection()
    {
        $this->assertNotNull(ConnectionManager::get_connection('mysql'));
    }

    public function testGetConnectionUsesExistingObject()
    {
        $a = ConnectionManager::get_connection('mysql');
        $this->assertSame($a === ConnectionManager::get_connection('mysql'), true);
    }

    public function testGh91GetConnectionWithNullConnectionIsAlwaysDefault()
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
