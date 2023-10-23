Finders in PHP ActiveRecord
Introduction
Finders in PHP ActiveRecord are methods that allow you to retrieve records from a database table based on specific criteria. Finders simplify the process of querying the database by providing a more intuitive and object-oriented approach to building queries.

## Basic Finder Methods
PHP ActiveRecord provides a variety of basic finder methods to retrieve records from the database. These methods can be chained together for more complex queries.

1. `find()` The find() method retrieves one or more records by its primary key(s).

    ```php
    // Retrieve a user by ID
    $user = User::find(1);
    
    if ($user) {
    echo $user->first_name;
    } else {
    echo "User not found";
    }
    
    // Retrieve using an array of IDs
    $users = User::find(1, 2, 3);
    ```

2. `all()` The all() method retrieves all records from the table.

    ```php
    // Retrieve all users
    $users = User::all();
    
    foreach ($users as $user) {
      echo $user->first_name . '<br>';
    }
    ```

3. `where()` The `where()` method is used to filter records based on specific conditions.

    ```php
    // Find users with a specific email domain
    $users = User::where('email', 'LIKE', '%example.com')->to_a();
    ```

    ```php
    // Find the first user with a specific condition
    $user = User::where('first_name', 'John')->first();
    ```

4. `order_by()` The order_by() method allows you to specify the sorting order of the retrieved records.

    ```php
    // Retrieve users sorted by last name in ascending order
    $users = User::order_by('last_name')->to_a();
    
    // Retrieve users sorted by last name in descending order
    $users = User::order_by_desc('last_name')->to_a();
    ```

## Chaining Finder Methods
You can chain multiple finder methods together to create more complex queries. For example:

```php
// Retrieve all active users with a specific role, sorted by registration date
$activeUsers = User::where('active', true)
  ->where('role', 'admin')
  ->order_by('created_at')
  ->to_a();
```
