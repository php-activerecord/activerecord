# Quick Start

Begin by installing via composer:
```sh
composer require php-patterns/activerecord
```


Setup is very easy and straight-forward. There are essentially only two configuration points you must concern yourself with:

1. Configuring your database connections.
2. Setting the database connection to use for your environment.

Example:

```php
// config.php
use ActiveRecord\Model;

$cfg = ActiveRecord\Config::instance();
$cfg->set_connections([
    'development' => 'mysql://username:password@localhost/development_database_name',
    'test' => 'mysql://username:password@localhost/test_database_name',
    'production' => 'mysql://username:password@localhost/production_database_name'
]);
$cfg->set_default_connection('development'); // Set to 'development', 'test', or 'production'. 'development' is default
```

Then, begin setting up your model classes.

```php
use ActiveRecord\Model;

class Book extends Model {
    
}

```

Provided you follow naming conventions in your classes and tables, you are off and running.

```php
$book = Book::find(1);
$book->title = "Ubik";
$book->save();
```
