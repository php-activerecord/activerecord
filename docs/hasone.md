# `$has_one` (one-to-one)

The `$has_one` relationship in ActiveRecord represents a one-to-one association between two database tables. It allows you to specify that a record in one table is associated with exactly one record in another table. To define a `$has_one` relationship, you need to configure the following properties:

- **Name of the Relationship:** This property specifies the name of the relationship, which is used to access the associated record. It is typically set as a string representing the name of the associated model. 

    ```php
    static $has_one = ['profile' => []];
    ```

- **Class Name (Optional):** This property allows you to explicitly specify the class name of the associated model if it differs from the relationship name. This can be useful if your relationship and model names do not follow the default naming conventions.

    ```php
    static $has_one = [
        'customProfile' => [
            'class_name' => 'Profile'
        ]
    ];
    ```

- **Foreign Key (Optional):** By default, ActiveRecord assumes that the foreign key in the associated table is derived from the name of the current model and suffixed with "_id" (e.g., "user_id" for a `User` model). You can specify a different foreign key if needed. This property is optional.

    ```php
    static $has_one = [
        'customProfile' => [
            'class_name' => 'Profile', 
            'foreign_key' => 'custom_user_id'
        ]
    ];
    ```

- **Primary Key (Optional):** This property allows you to specify the primary key of the associated table if it is different from the default "id" column. Use this when your associated table uses a different primary key column. This property is optional.

    ```php
    static $has_one = [
        'profile' => [
            'primary_key' => 'profile_id'
        ]
    ];
    ```

- **Order (Optional):** You can specify the order in which the associated records should be retrieved using the `order` property. This property is optional.

    ```php
    static $has_one = [
        'profile' => [
            'order' => 'created_at DESC'
        ]
    ];
    ```

- **Conditions (Optional):** You can specify conditions for retrieving the associated record using the `conditions` property. This property allows you to filter the associated record based on specific criteria. This property is optional.

    ```php
    static $has_one = [
        'profile' => [
            'conditions' => [
                'active' => true
            ]
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $has_one = [
        'profile' => []
    ];
}

class Profile extends ActiveRecord\Model {
    static $belongs_to = [
        'user' => []
    ];
}
```

In this example, a `User` model has a `$has_one` relationship with the `Profile` model. It allows you to access a user's profile information through the `profile` property.

The `$has_one` relationship simplifies working with one-to-one associations in your database, making it easy to retrieve and manipulate associated records.
