<?php

require_once __DIR__ . '/../../ActiveRecord.php';

class Book extends ActiveRecord\Model
{
    // explicit table name since our table is not "books"
    public static string $table_name = 'simple_book';

    // explicit pk since our pk is not "id"
    public static string $primary_key = 'book_id';

    // explicit connection name since we always want production with this model
    public static string $connection = 'production';

    // explicit database name will generate sql like so => db.table_name
    public static string $db = 'test';
}

$connections = [
    'development' => 'mysql://invalid',
    'production' => 'mysql://test:test@127.0.0.1/test'
];

// initialize ActiveRecord
ActiveRecord\Config::initialize(function ($cfg) use ($connections) {
    $cfg->set_connections($connections);
});

print_r(Book::first()->attributes());
