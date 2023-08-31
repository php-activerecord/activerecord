<?php

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
    die('PHP ActiveRecord requires PHP 5.3 or higher');
}

define('PHP_ACTIVERECORD_VERSION_ID', '1.0');

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_PREPEND')) {
    define('PHP_ACTIVERECORD_AUTOLOAD_PREPEND', true);
}

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_DISABLE')) {
//    spl_autoload_register('activerecord_autoload', true, PHP_ACTIVERECORD_AUTOLOAD_PREPEND);
}

//function activerecord_autoload($class_name)
//{
//    $path = \ActiveRecord\Config::instance()->get_model_directory();
//    $root = realpath(isset($path) ? $path : '.');
//
//    if (($namespaces = ActiveRecord\get_namespaces($class_name))) {
//        $class_name = array_pop($namespaces);
//        $directories = [];
//
//        foreach ($namespaces as $directory) {
//            $directories[] = $directory;
//        }
//
//        $root .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $directories);
//    }
//
//    $file = "$root/$class_name.php";
//
//    if (file_exists($file)) {
//        require_once $file;
//    }
//}
