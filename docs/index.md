# Introduction


Active Record is a software design pattern that abstracts database interactions, enabling developers to treat database records as objects within the host programming language (in our case, PHP). Each object instance represents a row in a database table, and its attributes correspond to the table's columns. This pattern allows for the creation, retrieval, updating, and deletion of records without the need for explicit SQL queries, streamlining database operations and enhancing code maintainability.

```php
$book = Book::find(1); // find a book by its primary key
$title = $book->title; // get the title
$book->title = strtoupper($title); // write the title
$book->save(); // save your changes

$book->delete();
```
