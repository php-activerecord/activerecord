# `validates_exclusion_of`

The `validates_exclusion_of` validation in ActiveRecord allows you to validate whether an attribute's value is excluded from a specified set of values. It is commonly used to ensure that an attribute does not have a value that falls within a predefined list or range. To use `validates_exclusion_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for exclusion. It is typically set as a symbol or string.

    ```php
    static $validates_exclusion_of = [
        'username' => []
    ];
    ```

- **In (Required):** The `in` option defines the set of values that the attribute's value must be excluded from. It is required and should be set as an array of values to be excluded.

    ```php
    static $validates_exclusion_of = [
        'username' => [
            'in' => ['admin', 'root', 'superuser']
        ]
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_exclusion_of = [
        'status' => [
            'in' => ['blocked', 'banned'],
            'message' => 'Invalid status. Please choose a different status.',
        ],
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_exclusion_of = [
        'user_type' => [
            'in' => ['admin', 'moderator'], 
            'allow_blank' => true
        ]
    ];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_nil` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_exclusion_of = [
        'account_type' => [
            'in' => ['suspended', 'disabled'], 
            'allow_null' => true
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_exclusion_of = [
        'user_role' => [
            'in' => ['admin', 'superuser'], 
            'on' => 'update'
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_exclusion_of = [
        'role' => [
            'in' => ['admin', 'superuser'],
            'message' => 'Invalid role. Please choose a different role.',
            'allow_blank' => true,
        ],
    ];
}
