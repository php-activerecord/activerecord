# PHP ActiveRecord
[![CI](https://github.com/php-activerecord/activerecord/actions/workflows/test.yml/badge.svg?branch=master)](https://github.com/php-activerecord/activerecord/actions/workflows/test.yml)
[![codecov](https://codecov.io/github/php-activerecord/activerecord/graph/badge.svg?token=IJBKNRHVOC)](https://codecov.io/github/php-activerecord/activerecord)
[![Latest Stable Version](https://poser.pugx.org/php-patterns/activerecord/version)](https://packagist.org/packages/php-patterns/activerecord)

![logo_large](https://github.com/php-activerecord/activerecord/assets/773172/01732546-a438-4a27-bdff-d6653af7d7a2)


**We encourage pull requests, and issues will be dealt with thoroughly and in a timely manner.**

 
http://php-activerecord.github.io/activerecord/


## Installation

Via composer:

```sh
composer require php-patterns/activerecord
```

## Introduction ##
A brief summarization of what ActiveRecord is:

> Active record is an approach to access data in a database. A database table or view is wrapped into a class,
> thus an object instance is tied to a single row in the table. After creation of an object, a new row is added to
> the table upon save. Any object loaded gets its information from the database; when an object is updated, the
> corresponding row in the table is also updated. The wrapper class implements accessor methods or properties for
> each column in the table or view.

More details can be found [here](http://en.wikipedia.org/wiki/Active_record_pattern).

This implementation is inspired and thus borrows heavily from Ruby on Rails' ActiveRecord.
We have tried to maintain their conventions while deviating mainly because of convenience or necessity.
Of course, there are some differences which will be obvious to the user if they are familiar with rails.

## Minimum Requirements ##

- PHP 8.1+
- [PDO driver for your respective database](https://www.php.net/manual/en/pdo.installation.php)

## Supported Databases ##

- MySQL
- PostgreSQL
- SQLite

## Features ##

- Callbacks
- Model Caching
- Database adapter plugins
- Finder methods, static and dynamic
- Relationships
- Serializations (json/xml)
- Transactions
- Validations
- Writer methods
- Miscellaneous options such as: aliased/protected/accessible attributes

## Installation ##

Setup is very easy and straight-forward. There are essentially only two configuration points you must concern yourself with:

1. Configuring your database connections.
2. Setting the database connection to use for your environment.

Example:

```php
$cfg = ActiveRecord\Config::instance();
$cfg->set_connections([
    'development' => 'mysql://username:password@localhost/development_database_name',
    'test' => 'mysql://username:password@localhost/test_database_name',
    'production' => 'mysql://username:password@localhost/production_database_name'
]);
$cfg->set_default_connection('development'); // Set to 'development', 'test', or 'production'. 'development' is default
```

Once you have configured these settings you are done. ActiveRecord takes care of the rest for you.
It does not require that you map your table schema to yaml/xml files. It will query the database for this information and
cache it so that it does not make multiple calls to the database for a single schema.

## Basic CRUD ##

### Retrieve ###
These are your basic methods to find and retrieve records from your database.
See the *Finders* section for more details.

```php
$post = Post::find(1);
echo $post->title; # 'My first blog post!!'
echo $post->author_id; # 5

# also the same since it is the first record in the db
$post = Post::first();

# finding using dynamic finders
$post = Post::find_by_name('The Decider');
$post = Post::find_by_name_and_id('The Bridge Builder',100);
$post = Post::find_by_name_or_id('The Bridge Builder',100);

# finding using a conditions array
$posts = Post::find('all', ['conditions' => ['name=? or id > ?','The Bridge Builder',100]]);
```

### Create ###
Here we create a new post by instantiating a new object and then invoking the save() method.

```php
$post = new Post();
$post->title = 'My first blog post!!';
$post->author_id = 5;
$post->save();
# INSERT INTO `posts` (title,author_id) VALUES('My first blog post!!', 5)
```

### Update ###
To update you would just need to find a record first and then change one of its attributes.
It keeps an array of attributes that are "dirty" (that have been modified) and so our
sql will only update the fields modified.

```php
$post = Post::find(1);
echo $post->title; # 'My first blog post!!'
$post->title = 'Some real title';
$post->save();
# UPDATE `posts` SET title='Some real title' WHERE id=1

$post->title = 'New real title';
$post->author_id = 1;
$post->save();
# UPDATE `posts` SET title='New real title', author_id=1 WHERE id=1
```

### Delete ###
Deleting a record will not *destroy* the object. This means that it will call sql to delete
the record in your database but you can still use the object if you need to.

```php
$post = Post::find(1);
$post->delete();
# DELETE FROM `posts` WHERE id=1
echo $post->title; # 'New real title'
```

## Contributing ##

Please refer to [CONTRIBUTING.md](https://github.com/php-activerecord/activerecord/blob/master/CONTRIBUTING.md) for information on how to contribute to PHP ActiveRecord.
