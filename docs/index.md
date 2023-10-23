[![CI](https://github.com/php-activerecord/activerecord/actions/workflows/test.yml/badge.svg?branch=master)](https://github.com/php-activerecord/activerecord/actions/workflows/test.yml)
[![codecov](https://codecov.io/github/php-activerecord/activerecord/graph/badge.svg?token=IJBKNRHVOC)](https://codecov.io/github/php-activerecord/activerecord)
[![Latest Stable Version](https://poser.pugx.org/php-patterns/activerecord/version)](https://packagist.org/packages/php-patterns/activerecord)

![logo_large](https://github.com/php-activerecord/activerecord/assets/773172/01732546-a438-4a27-bdff-d6653af7d7a2)

# Introduction

Active Record is a software design pattern that abstracts database interactions, enabling developers to treat database records as objects within the host programming language (in our case, PHP). Each object instance represents a row in a database table, and its attributes correspond to the table's columns. This pattern allows for the creation, retrieval, updating, and deletion of records without the need for explicit SQL queries, streamlining database operations and enhancing code maintainability.

```php
$person = Person::find(3); // find author with id of 3
echo $person->first_name; // "Bruce"
echo $person->age; // 63
 
echo gettype($person->first_name); // "string"
echo gettype($person->age); // "integer"
 
$person->first_name = "Sam";
$person->save();
echo $person->first_name; // "Sam"
 
$person->delete();
 
Person::find(3); // RecordNotFound exception
```
