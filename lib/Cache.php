<?php

namespace ActiveRecord;

use ActiveRecord\cache\iCache;
use Closure;

/**
 * Cache::get('the-cache-key', function() {
 *	 # this gets executed when cache is stale
 *	 return "your cacheable datas";
 * });
 *
 * @phpstan-type CacheOptions array{
 *  expire?: int,
 *  namespace?: string
 * }
 */
class Cache
{
    public static ?iCache $adapter = null;

    /**
     * @var CacheOptions
     */
    public static $options = [];

    /**
     * Initializes the cache.
     *
     * With the $options array it's possible to define:
     * - expiration of the key, (time in seconds)
     * - a namespace for the key
     *
     * this last one is useful in the case two applications use
     * a shared key/store (for instance a shared Memcached db)
     *
     * Ex:
     * $cfg_ar = ActiveRecord\Config::instance();
     * $cfg_ar->set_cache('memcache://localhost:11211',
     *  [
     *      'namespace' => 'my_cool_app',
     *      'expire' => 120
     *  ]);
     *
     * In the example above all the keys expire after 120 seconds, and the
     * all get a postfix 'my_cool_app'.
     *
     * (Note: expiring needs to be implemented in your cache store.)
     *
     * @param string       $url     URL to your cache server
     * @param CacheOptions $options Specify additional options
     */
    public static function initialize(string $url = '', array $options = []): void
    {
        if ($url) {
            $url = parse_url($url);
            assert(is_array($url));
            $file = ucwords(Inflector::camelize($url['scheme'] ?? ''));
            $class = "ActiveRecord\\$file";
            require_once __DIR__ . "/cache/$file.php";

            $cache = new $class($url);

            assert($cache instanceof Memcache);

            static::$adapter  = $cache;
        } else {
            static::$adapter = null;
        }

        static::$options = array_merge(['expire' => 30, 'namespace' => ''], $options);
    }

    public static function flush(): void
    {
        static::$adapter?->flush();
    }

    /**
     * Attempt to retrieve a value from cache using a key. If the value is not found, then the closure method
     * will be invoked, and the result will be stored in cache using that key.
     *
     * @param int $expire in seconds
     */
    public static function get(string $key, \Closure $closure, int $expire = null): mixed
    {
        if (!static::$adapter) {
            return $closure();
        }

        $key = static::get_namespace() . $key;

        if (!($value = static::$adapter->read($key))) {
            static::$adapter->write($key, $value = $closure(), $expire ?? static::$options['expire'] ?? 0);
        }

        return $value;
    }

    public static function set(string $key, mixed $var, int $expire = null): void
    {
        assert(isset(static::$adapter), 'Adapter required to set');

        $key = static::get_namespace() . $key;

        static::$adapter->write($key, $var, $expire ?? static::$options['expire'] ?? 0);
    }

    public static function delete(string $key): void
    {
        assert(isset(static::$adapter), 'Adapter required to delete');

        $key = static::get_namespace() . $key;
        static::$adapter->delete($key);
    }

    protected static function get_namespace(): string
    {
        return (isset(static::$options['namespace']) && strlen(static::$options['namespace']) > 0) ? (static::$options['namespace'] . '::') : '';
    }
}
