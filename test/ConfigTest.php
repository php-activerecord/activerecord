<?php

use ActiveRecord\Config;
use ActiveRecord\Connection;
use ActiveRecord\Exception\ConfigException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TestLogger implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param mixed[] $context
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param mixed[] $context
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param mixed[] $context
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param mixed[] $context
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Normal but significant events.
     *
     * @param mixed[] $context
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param mixed[] $context
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Detailed debug information.
     *
     * @param mixed[] $context
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed[] $context
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
    }
}

class TestDateTimeWithoutCreateFromFormat
{
    public function format($format=null)
    {
    }
}

class TestDateTime
{
    public function format($format=null)
    {
    }

    public static function createFromFormat($format, $time)
    {
    }
}

class ConfigTest extends TestCase
{
    private $config;
    private $connections;

    public function setUp(): void
    {
        $this->config = new Config();
        $this->connections = ['development' => 'mysql://blah/development', 'test' => 'mysql://blah/test'];
        $this->config->set_connections($this->connections);
    }

    public function testGetConnections()
    {
        $this->assertEquals($this->connections, $this->config->get_connections());
    }

    public function testGetConnection()
    {
        $this->assertEquals($this->connections['development'], $this->config->get_connection('development'));
    }

    public function testGetInvalidConnection()
    {
        $this->assertNull($this->config->get_connection('whiskey tango foxtrot'));
    }

    public function testGetDefaultConnectionAndConnection()
    {
        $this->config->set_default_connection('development');
        $this->assertEquals('development', $this->config->get_default_connection());
        $this->assertEquals($this->connections['development'], $this->config->get_default_connection_string());
    }

    public function testGetDefaultConnectionAndConnectionStringDefaultsToDevelopment()
    {
        $this->assertEquals('development', $this->config->get_default_connection());
        $this->assertEquals($this->connections['development'], $this->config->get_default_connection_string());
    }

    public function testGetDefaultConnectionStringWhenConnectionNameIsNotValid()
    {
        $this->config->set_default_connection('little mac');
        $this->assertEmpty($this->config->get_default_connection_string());
    }

    public function testDefaultConnectionIsSetWhenOnlyOneConnectionIsPresent()
    {
        $this->config->set_connections(['development' => $this->connections['development']]);
        $this->assertEquals('development', $this->config->get_default_connection());
    }

    public function testSetConnectionsWithDefault()
    {
        $this->config->set_connections($this->connections, 'test');
        $this->assertEquals('test', $this->config->get_default_connection());
    }

    public function testGetDateClassWithDefault()
    {
        $this->assertEquals('ActiveRecord\\DateTime', $this->config->get_date_class());
    }

    public function testSetDateClassWhenClassDoesntExist()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('doesntexist');
    }

    public function testSetDateClassWhenClassDoesntHaveFormatOrCreatefromformat()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('TestLogger');
    }

    public function testSetLogging()
    {
        $oldLogger = Config::instance()->get_logger();
        $oldLogging = Config::instance()->get_logging();
        Config::instance()->set_logging(true);

        $loggerMock = $this->createMock(TestLogger::class);
        $loggerMock
            ->expects($this->atLeast(1))
            ->method('info');

        Config::instance()->set_logger($loggerMock);
        Config::instance()->set_logging(true);

        Connection::instance()->query('select * from books');

        $loggerMock = $this->createMock(TestLogger::class);
        $loggerMock
            ->expects($this->exactly(0))
            ->method('info');
        Config::instance()->set_logger($loggerMock);
        Config::instance()->set_logging(false);

        Connection::instance()->query('select * from books');

        Config::instance()->set_logging($oldLogging);
        Config::instance()->set_logger($oldLogger);
    }

    public function testSetDateClassWhenClassDoesntHaveCreatefromformat()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('TestDateTimeWithoutCreateFromFormat');
    }

    public function testSetDateClassWithValidClass()
    {
        $this->config->set_date_class('TestDateTime');
        $this->assertEquals('TestDateTime', $this->config->get_date_class());
    }

    public function testInitializeClosure()
    {
        $test = $this;

        Config::initialize(function ($cfg) use ($test) {
            $test->assertNotNull($cfg);
            $test->assertEquals('ActiveRecord\Config', get_class($cfg));
        });
    }
}
