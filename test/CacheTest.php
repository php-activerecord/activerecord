<?php

use ActiveRecord\Cache;
use ActiveRecord\Exception\CacheException;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function setUp($connection_name=null): void
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('The memcache extension is not available');

            return;
        }

        Cache::initialize('memcache://localhost');
    }

    public function tearDown(): void
    {
        Cache::flush();
    }

    private function cache_get()
    {
        return Cache::get('1337', function () { return 'abcd'; });
    }

    public function testInitialize()
    {
        $this->assertNotNull(Cache::$adapter);
    }

    public function testInitializeWithNull()
    {
        Cache::initialize();
        $this->assertNull(Cache::$adapter);
    }

    public function testGetReturnsTheValue()
    {
        $this->assertEquals('abcd', $this->cache_get());
    }

    public function testGetWritesToTheCache()
    {
        $this->cache_get();
        $this->assertEquals('abcd', Cache::$adapter->read('1337'));
    }

    public function testGetDoesNotExecuteClosureOnCacheHit()
    {
        $this->expectNotToPerformAssertions();
        $this->cache_get();
        Cache::get('1337', function () { throw new Exception('I better not execute!'); });
    }

    public function testCacheAdapterReturnsFalseOnCacheMiss()
    {
        $this->assertSame(false, Cache::$adapter->read('some-key'));
    }

    public function testGetWorksWithoutCachingEnabled()
    {
        Cache::$adapter = null;
        $this->assertEquals('abcd', $this->cache_get());
    }

    public function testCacheExpire()
    {
        Cache::$options['expire'] = 1;
        $this->cache_get();
        sleep(2);

        $this->assertSame(false, Cache::$adapter->read('1337'));
    }

    public function testNamespaceIsSetProperly()
    {
        Cache::$options['namespace'] = 'myapp';
        $this->cache_get();
        $this->assertSame('abcd', Cache::$adapter->read('myapp::1337'));
    }

    public function testExceptionWhenConnectFails()
    {
        $this->expectException(CacheException::class);
        Cache::initialize('memcache://127.0.0.1:1234');
    }
}
