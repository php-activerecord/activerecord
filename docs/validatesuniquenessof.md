# `validates_uniqueness_of`

The `validates_uniqueness_of` validation in ActiveRecord allows you to validate whether an attribute's value is unique among the records in the database table. It ensures that the attribute's value is not duplicated within the dataset. To use `validates_uniqueness_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for uniqueness. It is typically set as a symbol or string.

    ```php
    static $validates_uniqueness_of = ['email' => []];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_uniqueness_of = [
        'username' => [
            'message' => 'This username is already in use. Please choose a different one.',
        ],
    ];
    ```

- **Scope (Optional):** The `scope` option allows you to specify additional conditions for uniqueness validation. It is used when you want to ensure uniqueness based on certain criteria. You can specify an array of attribute names to scope the uniqueness check.

    ```php
    static $validates_uniqueness_of = [
        'email' => [
            'scope' => ['account_type', 'status'],
            'message' => 'Email address must be unique for the selected account type and status.',
        ],
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_uniqueness_of = [
        'email' => [
            'allow_blank' => true
        ]
    ];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_null` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_uniqueness_of = [
        'username' => [
            'allow_null' => true
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_uniqueness_of = [
        'username' => [
            'on' => 'create'
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_uniqueness_of = ['email', 'username'];
}
```
