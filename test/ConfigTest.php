<?php

use ActiveRecord\Config;
use ActiveRecord\Exception\ConfigException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TestLogger implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void {

    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void {

    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void {

    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void {

    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {

    }

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []):void {

    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []):void {

    }

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []):void {

    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []):void {

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

    public function test_get_connections()
    {
        $this->assertEquals($this->connections, $this->config->get_connections());
    }

    public function test_get_connection()
    {
        $this->assertEquals($this->connections['development'], $this->config->get_connection('development'));
    }

    public function test_get_invalid_connection()
    {
        $this->assertNull($this->config->get_connection('whiskey tango foxtrot'));
    }

    public function test_get_default_connection_and_connection()
    {
        $this->config->set_default_connection('development');
        $this->assertEquals('development', $this->config->get_default_connection());
        $this->assertEquals($this->connections['development'], $this->config->get_default_connection_string());
    }

    public function test_get_default_connection_and_connection_string_defaults_to_development()
    {
        $this->assertEquals('development', $this->config->get_default_connection());
        $this->assertEquals($this->connections['development'], $this->config->get_default_connection_string());
    }

    public function test_get_default_connection_string_when_connection_name_is_not_valid()
    {
        $this->config->set_default_connection('little mac');
        $this->assertNull($this->config->get_default_connection_string());
    }

    public function test_default_connection_is_set_when_only_one_connection_is_present()
    {
        $this->config->set_connections(['development' => $this->connections['development']]);
        $this->assertEquals('development', $this->config->get_default_connection());
    }

    public function test_set_connections_with_default()
    {
        $this->config->set_connections($this->connections, 'test');
        $this->assertEquals('test', $this->config->get_default_connection());
    }

    public function test_get_date_class_with_default()
    {
        $this->assertEquals('ActiveRecord\\DateTime', $this->config->get_date_class());
    }

    public function test_set_date_class_when_class_doesnt_exist()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('doesntexist');
    }

    public function test_set_date_class_when_class_doesnt_have_format_or_createfromformat()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('TestLogger');
    }

    public function test_set_date_class_when_class_doesnt_have_createfromformat()
    {
        $this->expectException(ConfigException::class);
        $this->config->set_date_class('TestDateTimeWithoutCreateFromFormat');
    }

    public function test_set_date_class_with_valid_class()
    {
        $this->config->set_date_class('TestDateTime');
        $this->assertEquals('TestDateTime', $this->config->get_date_class());
    }

    public function test_initialize_closure()
    {
        $test = $this;

        Config::initialize(function ($cfg) use ($test) {
            $test->assertNotNull($cfg);
            $test->assertEquals('ActiveRecord\Config', get_class($cfg));
        });
    }
}
