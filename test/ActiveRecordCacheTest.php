<?php

use activerecord\Cache;
use activerecord\Config;
use test\models\Author;

class ActiveRecordCacheTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('The memcache extension is not available');

            return;
        }

        parent::setUp($connection_name);
        Config::instance()->set_cache('memcache://localhost');
    }

    public function tearDown(): void
    {
        Cache::flush();
        Cache::initialize();
    }

    public function testDefaultExpire()
    {
        $this->assertEquals(30, Cache::$options['expire']);
    }

    public function testExplicitDefaultExpire()
    {
        Config::instance()->set_cache('memcache://localhost', ['expire' => 1]);
        $this->assertEquals(1, Cache::$options['expire']);
    }

    public function testCachesColumnMetaData()
    {
        Author::first();

        $table_name = Author::table()->get_fully_qualified_table_name(!($this->connection instanceof ActiveRecord\PgsqlAdapter));
        $value = Cache::$adapter->read("get_meta_data-$table_name");
        $this->assertTrue(is_array($value));
    }
}
