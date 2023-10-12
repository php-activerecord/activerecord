# Introduction


Active Record is a software design pattern that abstracts database interactions, enabling developers to treat database records as objects within the host programming language (in our case, PHP). Each object instance represents a row in a database table, and its attributes correspond to the table's columns. This pattern allows for the creation, retrieval, updating, and deletion of records without the need for explicit SQL queries, streamlining database operations and enhancing code maintainability.

```php-inline
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
