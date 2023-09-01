<?php

use ActiveRecord\Cache;
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
    }

    public function test_default_expire()
    {
        $this->assertEquals(30, Author::table()->cache_model_expire);
    }

    public function test_explicit_expire()
    {
        $this->assertEquals(2592000, Publisher::table()->cache_model_expire);
    }

    public function test_cache_key()
    {
        $method = $this->set_method_public(Author::class, 'cache_key');
        $author = Author::first();

        $this->assertEquals('test\models\Author-1', $method->invokeArgs($author, []));
    }

    public function test_model_cache_find_by_pk()
    {
        $publisher = Publisher::find(1);
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $cache_key = $method->invokeArgs($publisher, []);
        $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);

        $this->assertEquals($publisher->name, $publisherDirectlyFromCache->name);
    }

    public function test_model_cache_new()
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

    public function test_model_cache_find()
    {
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $publishers = Publisher::all();

        foreach ($publishers as $publisher) {
            $cache_key = $method->invokeArgs($publisher, []);
            $publisherDirectlyFromCache = Cache::$adapter->read($cache_key);

            $this->assertEquals($publisher->name, $publisherDirectlyFromCache->name);
        }
    }

    public function test_regular_models_not_cached()
    {
        $method = $this->set_method_public(Author::class, 'cache_key');
        $author = Author::first();
        $cache_key = $method->invokeArgs($author, []);
        $this->assertFalse(Cache::$adapter->read($cache_key));
    }

    public function test_model_delete_from_cache()
    {
        $method = $this->set_method_public(Publisher::class, 'cache_key');
        $publisher = Publisher::find(1);
        $cache_key = $method->invokeArgs($publisher, []);

        $publisher->delete();

        // at this point, the cached record should be gone
        $this->assertFalse(Cache::$adapter->read($cache_key));
    }

    public function test_model_update_cache()
    {
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
