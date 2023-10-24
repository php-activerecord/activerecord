# Static Checking

The PHP Active Record code base is statically checked for correctness using [PHPStan](https://phpstan.org/). If you are using phpstan in your own code you can leverage the safety it provides by importing this project's neon file in your own:

```neon
// your phpstan.neon file
parameters:
...
includes:
  - vendor/php-patterns/activerecord/extension.neon
```

There are several methods (namely `Model::find`, `Model::first`, `Model::last` and their counterparts on `Relation`) in this library that have dynamic return types, and by tying into  Active Record's bespoke extension.neon file you can eliminate much of the uncertainty around using them and avoid bugs before they happen. For example:

```php
class Sample {
  protected Book $book;
  
  /**
   * @var array<Book> 
   */
  protected array $books;
  
  function __construct() {
    $this->book = Book::find(1); // ok
    $this->book = Book::find([1,2,3]);  // PHPStan error!
                                        // Can't assign array to single item
  }
}

```
