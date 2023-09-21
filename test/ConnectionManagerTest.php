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
}
