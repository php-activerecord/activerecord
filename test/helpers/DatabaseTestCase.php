<?php

use ActiveRecord\Connection;
use ActiveRecord\Exception\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/DatabaseLoader.php';

abstract class DatabaseTestCase extends TestCase
{
    protected Connection $connection;
    protected string $connection_name;

    protected string $original_default_connection;
    protected $original_date_class;
    public static $log = false;
    public static $db;

    public function setUp(string $connection_name=null): void
    {
        ActiveRecord\Table::clear_cache();

        $config = ActiveRecord\Config::instance();
        $this->original_default_connection = $config->get_default_connection();
        $connection_name ??= $this->original_default_connection;

        $config->set_default_connection($connection_name);

        $this->original_date_class = $config->get_date_class();

        if ('sqlite' == $connection_name || 'sqlite' == $config->get_default_connection()) {
            // need to create the db. the adapter specifically does not create it for us.
            static::$db = substr(ActiveRecord\Config::instance()->get_connection('sqlite'), 9);
            new SQLite3(static::$db);
        }

        try {
            $this->connection = ActiveRecord\ConnectionManager::get_connection($connection_name);
            $this->connection_name = $connection_name;
        } catch (Exception $e) {
            $this->markTestSkipped($connection_name . ' failed to connect. ' . $e->getMessage());
        }

        $GLOBALS['ACTIVERECORD_LOG'] = false;

        $loader = new DatabaseLoader($this->connection);
        $loader->reset_table_data();

        if (self::$log) {
            $GLOBALS['ACTIVERECORD_LOG'] = true;
        }
    }

    public function tearDown(): void
    {
        ActiveRecord\Config::instance()->set_date_class($this->original_date_class);
        ActiveRecord\Config::instance()->set_default_connection($this->original_default_connection);
    }

    public function assert_exception_message_contains($contains, $closure)
    {
        $message = '';

        try {
            $closure();
        } catch (UndefinedPropertyException $e) {
            $message = $e->getMessage();
        }

        $this->assertStringContainsString($contains, $message);
    }

    /**
     * Returns true if $regex matches $actual.
     *
     * Takes database specific quotes into account by removing them. So, this won't
     * work if you have actual quotes in your strings.
     */
    public function assert_sql_has($needle, $haystack)
    {
        $needle = str_replace(['"', '`'], '', $needle);
        $haystack = str_replace(['"', '`'], '', $haystack);

        return $this->assertStringContainsString($needle, $haystack);
    }

    public function assert_sql_doesnt_has($needle, $haystack)
    {
        $needle = str_replace(['"', '`'], '', $needle);
        $haystack = str_replace(['"', '`'], '', $haystack);

        return $this->assertStringNotContainsString($needle, $haystack);
    }

    /**
     * Exposes non-public variables for inspection
     *
     * @param $obj  The instantianted object who has the private variable you want access to
     * @param $name The name of the private or protected variable you want access to
     *
     * @return The private variable of $obj
     */
    protected function getPrivateVariable($obj, $name): mixed
    {
        $reflectionClass = new ReflectionClass($obj);
        $reflectionProperty = $reflectionClass->getProperty($name);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($obj);
    }
}
