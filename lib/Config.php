<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ConfigException;
use Closure;
use Psr\Log\LoggerInterface;

/**
 * Manages configuration options for ActiveRecord.
 *
 * ```
 * ActiveRecord::initialize(function($cfg) {
 *   $cfg->set_model_home('models');
 *   $cfg->set_connections([
 *     'development' => 'mysql://user:pass@development.com/awesome_development',
 *     'production' => 'mysql://user:pass@production.com/awesome_production'
 *   ]);
 * });
 * ```
 *
 * @phpstan-import-type CacheOptions from Cache
 */
class Config extends Singleton
{
    /**
     * Name of the connection to use by default.
     *
     * ```
     * ActiveRecord\Config::initialize(function($cfg) {
     *   $cfg->set_connections([
     *     'development' => 'mysql://user:pass@development.com/awesome_development',
     *     'production' => 'mysql://user:pass@production.com/awesome_production'
     *   ]);
     * });
     * ```
     *
     * This is a singleton class, so you can retrieve the {@link Singleton} instance by doing:
     *
     * ```
     * $config = ActiveRecord\Config::instance();
     * ```
     */
    private string $default_connection = 'development';

    /**
     * Contains the list of database connection strings.
     *
     * @var array<string,string>
     */
    private array $connections = [];

    /**
     * Switch for logging.
     */
    private bool $logging = false;

    /**
     * Contains a Logger object that must implement a log() method.
     */
    private LoggerInterface $logger;

    /**
     * Contains the class name for the Date class to use. Must have a public format() method and a
     * public static createFromFormat($format, $time) method
     *
     * @var class-string
     */
    private string $date_class = 'ActiveRecord\\DateTime';

    /**
     * Allows config initialization using a closure.
     *
     * This method is just syntatic sugar.
     *
     * ```
     * ActiveRecord\Config::initialize(function($cfg) {
     *   $cfg->set_connections([
     *     'development' => 'mysql://username:password@127.0.0.1/database_name'
     *   ]);
     * });
     * ```
     *
     * You can also initialize by grabbing the singleton object:
     *
     * ```
     * $cfg = ActiveRecord\Config::instance();
     * $cfg->set_connections([
     *   'development' => 'mysql://username:password@localhost/database_name'
     * ]);
     * ```
     *
     * @param \Closure $initializer A closure
     */
    public static function initialize(\Closure $initializer): void
    {
        $initializer(parent::instance());
    }

    /**
     * Sets the list of database connection strings.
     *
     * ```
     * $config->set_connections([
     *     'development' => 'mysql://username:password@127.0.0.1/database_name'
     * ]);
     * ```
     *
     * @param array<string,string> $connections        Array of connections
     * @param string               $default_connection Optionally specify the default_connection
     *
     * @throws ConfigException
     */
    public function set_connections(array $connections, string $default_connection = ''): void
    {
        if ($default_connection) {
            $this->set_default_connection($default_connection);
        }

        $this->connections = $connections;
    }

    /**
     * Returns the connection strings array.
     *
     * @return array<string,string>
     */
    public function get_connections(): array
    {
        return $this->connections;
    }

    /**
     * Returns a connection string if found otherwise null.
     *
     * @param string $name Name of connection to retrieve
     *
     * @return ?string connection info for specified connection name
     */
    public function get_connection(string $name): ?string
    {
        if (array_key_exists($name, $this->connections)) {
            return $this->connections[$name];
        }

        return null;
    }

    /**
     * Returns the default connection string.
     */
    public function get_default_connection_string(): string
    {
        return $this->connections[$this->default_connection] ?? '';
    }

    /**
     * Returns the name of the default connection.
     */
    public function get_default_connection(): string
    {
        return $this->default_connection;
    }

    /**
     * Set the name of the default connection.
     *
     * @param string $name Name of a connection in the connections array
     */
    public function set_default_connection(string $name): void
    {
        $this->default_connection = $name;
    }

    /**
     * Turn on/off logging
     */
    public function set_logging(bool $bool): void
    {
        $this->logging = (bool) $bool;
    }

    /**
     * Sets the logger object for future SQL logging
     */
    public function set_logger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Return whether logging is on
     */
    public function get_logging(): bool
    {
        return $this->logging;
    }

    /**
     * Returns the logger
     */
    public function get_logger(): LoggerInterface|null
    {
        return $this->logger ?? null;
    }

    /**
     * @param class-string $date_class
     *
     * @throws ConfigException
     * @throws Exception\ActiveRecordException
     */
    public function set_date_class(string $date_class): void
    {
        try {
            $klass = Reflections::instance()->add($date_class)->get($date_class);
        } catch (\ReflectionException $e) {
            throw new ConfigException('Cannot find date class');
        }

        if (!$klass->hasMethod('format') || !$klass->getMethod('format')->isPublic()) {
            throw new ConfigException('Given date class must have a "public format($format = null)" method');
        }
        if (!$klass->hasMethod('createFromFormat') || !$klass->getMethod('createFromFormat')->isPublic()) {
            throw new ConfigException('Given date class must have a "public static createFromFormat($format, $time)" method');
        }
        $this->date_class = $date_class;
    }

    /**
     * @return class-string
     */
    public function get_date_class(): string
    {
        return $this->date_class;
    }

    /**
     * Sets the url for the cache server to enable query caching.
     *
     * Only table schema queries are cached at the moment. A general query cache
     * will follow.
     *
     * Example:
     *
     * ```
     * $config->set_cache("memcached://localhost");
     * $config->set_cache("memcached://localhost",["expire" => 60]);
     * ```
     *
     * @param string       $url     url to your cache server
     * @param CacheOptions $options Array of options
     */
    public function set_cache(string $url, array $options = []): void
    {
        Cache::initialize($url, $options);
    }
}
