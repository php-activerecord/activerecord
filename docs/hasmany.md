# `$has_many` (one-to-many)

The `$has_many` relationship in ActiveRecord represents a one-to-many association between two database tables. It allows you to specify that a record in one table can be associated with multiple records in another table. To define a `$has_many` relationship, you need to configure the following properties:

- **Name of the Relationship:** This property specifies the name of the relationship, which is used to access the associated records. It is typically set as a string representing the name of the associated model in plural form.

    ```php
    static $has_many = ['posts' => []];
    ```

- **Class Name:** This property allows you to explicitly specify the class name of the associated model if it differs from the relationship name. This can be useful if your relationship and model names do not follow the default naming conventions.

    ```php
    static $has_many = [
        'customPosts' => [
            'class_name' => 'Post'
        ]];
    ```

- **Foreign Key:** By default, ActiveRecord assumes that the foreign key in the associated table is derived from the name of the current model and suffixed with "_id" (e.g., "user_id" for a `User` model). However, you can specify a different foreign key if needed.

    ```php
    static $has_many = [
        'posts' => [
            'foreign_key' => 'custom_user_id'
        ]];
    ```

- **Primary Key:** This property allows you to specify the primary key of the associated table if it is different from the default "id" column. Use this when your associated table uses a different primary key column.

    ```php
    static $has_many = [
        'pots' => [
            'primary_key' => 'post_id'
        ]
    ];
    ```
  - **Order (Optional):** You can specify the order in which the associated records should be retrieved using the `order` property. This property is optional.

    ```php
    static $has_many = ['posts' => ['order' => 'created_at DESC']];
    ```

- **Limit (Optional):** To limit the number of associated records retrieved, you can use the `limit` property. This is useful for scenarios where you want to retrieve a specific number of related records. This property is optional.

    ```php
    static $has_many = ['posts' => ['limit' => 5]];
    ```

- **Offset (Optional):** The `offset` property allows you to skip a specified number of records before retrieving associated records. It can be used in combination with `limit` for pagination purposes. This property is optional.

    ```php
    static $has_many = ['posts' => ['limit' => 5, 'offset' => 10]];
    ```

- **Conditions (Optional):** You can specify conditions for retrieving associated records using the `conditions` property. This property allows you to filter the associated records based on specific criteria. This property is optional.

    ```php
    static $has_many = ['posts' => ['conditions' => ['status' => 'published']]];
    ```


Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $has_many = ['posts'=>[]];
}

class Post extends ActiveRecord\Model {
    static $belongs_to = ['user'=>[]];
}

// Finding a user and accessing their posts
$user = User::find(1);

// Accessing the user's posts
$posts = $user->posts;

// Iterating through and displaying the user's posts
foreach ($posts as $post) {
    echo "Post Title: " . $post->title . "<br>";
    echo "Content: " . $post->content . "<br><br>";
}
```
