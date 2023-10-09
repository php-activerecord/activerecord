<?php

use ActiveRecord\Cache;
use ActiveRecord\Table;
use test\models\Author;
use test\models\Publisher;

class CacheModelTest extends DatabaseTestCase
{
    public function setUp($connection_name=null): void
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('The memcache extension is not available');

            return;
        }
        parent::setUp($connection_name);
        ActiveRecord\Config::instance()->set_cache('memcache://localhost');
        Cache::flush();
    }

    protected static function set_method_public($className, $methodName)
    {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }

    public function tearDown(): void
    {
        Cache::flush();
        Cache::initialize();

        parent::tearDown();
    }

    public function testDefaultExpire()
    {
        $this->assertEquals(30, Table::load(Author::class)->cache_model_expire);
    }

    public function testExplicitExpire()
    {
        $this->assertEquals(2592000, Table::load(Publisher::class)->cache_model_expire);
    }

    public function testCacheKey()
    {
        $method = $this->set_method_public(Author::class, 'cache_key');
        $author = Author::first();

        $this->assertEquals('test\models\Author-1', $method->invokeArgs($author, []));
    }

    public function testModelCacheFindByPk()
    {
        $publisher = Publisher::find(1);
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $cache_key = $method->invokeArgs($publisher, []);
        $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);

        $this->assertEquals($publisher->name, $publisherDirectlyFromCache->name);
    }

    public function testModelCacheNew()
    {
        $publisher = new Publisher([
           'name'=>'HarperCollins'
        ]);
        $publisher->save();

        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $cache_key = $method->invokeArgs($publisher, []);

        $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);

        $this->assertTrue(is_object($publisherDirectlyFromCache));
        $this->assertEquals($publisher->name, $publisherDirectlyFromCache->name);
    }

    public function testModelCacheFind()
    {
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $publishers = Publisher::all()->to_a();

        foreach ($publishers as $publisher) {
            $cache_key = $method->invokeArgs($publisher, []);
            $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);

            $this->assertEquals($publisher->name, $publisherDirectlyFromCache->name);
        }
    }

    public function testRegularModelsNotCached()
    {
        $method = $this->set_method_public(Author::class, 'cache_key');
        $author = Author::first();
        $cache_key = $method->invokeArgs($author, []);
        $this->assertFalse(Cache::$adapter->read($cache_key));
    }

    public function testModelDeleteFromCache()
    {
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $publisher = Publisher::find(1);
        $cache_key = $method->invokeArgs($publisher, []);

        $publisher->delete();

        // at this point, the cached record should be gone
        $this->assertFalse(Cache::$adapter->read($cache_key));
    }

    public function testModelUpdateCache()
    {
        static::setUpBeforeClass();

        $method = $this->set_method_public(Publisher::class, 'cache_key');

        $publisher = Publisher::find(1);
        $cache_key = $method->invokeArgs($publisher, []);
        $this->assertEquals('Random House', $publisher->name);

        $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);
        $this->assertEquals('Random House', $publisherDirectlyFromCache->name);

        // make sure that updates make it to cache
        $publisher->name = 'Puppy Publishing';
        $publisher->save();

        $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);
        $this->assertEquals('Puppy Publishing', $publisherDirectlyFromCache->name);
    }
}
