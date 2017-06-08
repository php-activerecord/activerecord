# Introduction

php-activerecord is an implementation of Active Record, a design pattern with the goal of seamlessly mapping database table rows to objects. Aside from some initial one-time setup, you don't have to write any getters or setters or teach your object class about the columns in the table; you simply define a bare-bones wrapper class that extends a Model base class, and all of the getters and setters and knowledge of columns are extrapolated automatically from queries to the database (which are cached). Changes made to an instance of the class are persisted to your database.
 
 You can read more about the idea [here](https://en.wikipedia.org/wiki/Active_record_pattern)
 
 Provided you follow some conventions, your wrapper class may be as simple as:
  ```php
 class Author extends ActiveRecord\Model {
 }
 ```
 
 You are then ready to access rows from your database's authors table and make edits:
 ```
 $person = Person::find(3); // find author with id of 3
 echo $person->first_name; // "Bruce"
 echo $person->age; // 63
 
 echo gettype($person->first_name); // "string"
 echo gettype($person->age); // "integer"
 
 $person->first_name = "Caitlyn";
 $person->save();
 echo $person->first_name; // "Caitlyn
 ```
 
 See the other pages in this documentation for details.