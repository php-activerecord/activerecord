<?php

/**
 * In order to run these unit tests, you need to install the required packages using Composer:
 *
 *    $ composer install
 *
 * After that you can run the tests by invoking the local PHPUnit
 *
 * To run all test simply use:
 *
 *    $ vendor/bin/phpunit
 *
 * Or run a single test file by specifying its path:
 *
 *    $ vendor/bin/phpunit test/InflectorTest.php
 **/
require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../vendor/phpunit/phpunit/src/Framework/TestCase.php';
require_once 'DatabaseTestCase.php';
require_once 'AdapterTestCase.php';

require_once __DIR__ . '/../../ActiveRecord.php';

// whether to run the slow non-crucial tests
$GLOBALS['slow_tests'] = false;

// whether to show warnings when Log or Memcache is missing
$GLOBALS['show_warnings'] = true;

if ('false' !== getenv('LOG')) {
    DatabaseTestCase::$log = true;
}

ActiveRecord\Config::initialize(function ($cfg) {
    $cfg->set_connections([
        'mysql'  => getenv('PHPAR_MYSQL') ?: 'mysql://test:test@127.0.0.1:3306/test',
        'pgsql'  => getenv('PHPAR_PGSQL') ?: 'pgsql://test:test@127.0.0.1:5432/test',
        'sqlite' => getenv('PHPAR_SQLITE') ?: 'sqlite://test.db']);

    $cfg->set_default_connection('mysql');

    for ($i=0; $i<count($GLOBALS['argv']); ++$i) {
        if ('--adapter' == $GLOBALS['argv'][$i]) {
            $cfg->set_default_connection($GLOBALS['argv'][$i+1]);
        } elseif ('--slow-tests' == $GLOBALS['argv'][$i]) {
            $GLOBALS['slow_tests'] = true;
        }
    }

    if (class_exists('Monolog\Logger')) { // Monolog installed
        $log = new Monolog\Logger('arlog');
        $log->pushHandler(new \Monolog\Handler\StreamHandler(
            dirname(__FILE__) . '/../log/query.log',
            \Monolog\Level::Warning)
        );

        $cfg->set_logging(true);
        $cfg->set_logger($log);
    } else {
        if ($GLOBALS['show_warnings'] && !isset($GLOBALS['show_warnings_done'])) {
            echo "(Logging SQL queries disabled, PEAR::Log not found.)\n";
        }

        DatabaseTestCase::$log = false;
    }

    if ($GLOBALS['show_warnings']  && !isset($GLOBALS['show_warnings_done'])) {
        if (!extension_loaded('memcache')) {
            echo "(Cache Tests will be skipped, Memcache not found.)\n";
        }
    }

    date_default_timezone_set('UTC');

    $GLOBALS['show_warnings_done'] = true;
});

error_reporting(E_ALL | E_STRICT);
