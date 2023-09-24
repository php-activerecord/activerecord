<?php

namespace ActiveRecord;

use ActiveRecord\cache\iCache;
use ActiveRecord\Exception\CacheException;

/**
 * @phpstan-type MemcacheOptions array{
 *      host: string,
 *      port?: int
 *  }
 */
class Memcache implements iCache
{
    public const DEFAULT_PORT = 11211;

    private \Memcache $memcache;

    /**
     * Creates a Memcache instance.
     *
     * @param MemcacheOptions $options
     */
    public function __construct(array $options)
    {
        $this->memcache = new \Memcache();
        $port = $options['port'] ?? self::DEFAULT_PORT;
        if (!@$this->memcache->connect($options['host'], $port)) {
            $error = error_get_last();
            $message = $error['message'] ?? sprintf('Could not connect to %s:%s', $options['host'], $port);
            throw new CacheException($message);
        }
    }

    public function flush(): void
    {
        $this->memcache->flush();
    }

    /**
     * @param list<string>|string $key
     */
    public function read(array|string $key): mixed
    {
        return $this->memcache->get($key);
    }

    public function write(string $key, mixed $value, int $expire = 0): bool
    {
        return $this->memcache->set($key, $value, 0, $expire);
    }

    public function delete(string $key): bool
    {
        return $this->memcache->delete($key);
    }
}
