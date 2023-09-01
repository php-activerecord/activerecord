<?php

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80100) {
    exit('PHP ActiveRecord requires PHP 8.1 or higher');
}

define('PHP_ACTIVERECORD_VERSION_ID', '2.0');

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_PREPEND')) {
    define('PHP_ACTIVERECORD_AUTOLOAD_PREPEND', true);
}

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_DISABLE')) {
    //    spl_autoload_register('activerecord_autoload', true, PHP_ACTIVERECORD_AUTOLOAD_PREPEND);
}
