<?php

use activerecord\Cache;
use activerecord\Config;
use ActiveRecord\Table;
use test\models\Author;

class ActiveRecordCacheTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('The memcache extension is not available');

            return;
        }

        Config::instance()->set_cache('memcache://localhost');
        parent::setUp($connection_name);
        static::setUpBeforeClass();
    }

    public function tearDown(): void
    {
        Cache::flush();
        Cache::initialize();
        parent::tearDown();
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
        static::resetTableData();
        Author::first();

        $table_name = Table::load(Author::class)->table;
        $value = Cache::$adapter->read("get_meta_data-$table_name");
        $this->assertTrue(is_array($value));
    }
}
