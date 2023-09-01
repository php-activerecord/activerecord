<?php

use ActiveRecord\Exception\ConnectionException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Exception\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/DatabaseLoader.php';

abstract class DatabaseTestCase extends TestCase
{
    protected \ActiveRecord\Connection $connection;
    protected string $connection_name;
    protected $original_date_class;
    public static $log = false;
    public static $db;

    public function setUp(string $connection_name=null): void
    {
        ActiveRecord\Table::clear_cache();

        $config = ActiveRecord\Config::instance();
        $connection_name ??= $config->get_default_connection();

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
}
