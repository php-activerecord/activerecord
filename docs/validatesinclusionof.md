# `validates_inclusion_of`

The `validates_inclusion_of` validation in ActiveRecord allows you to validate whether an attribute's value is included in a specified set of values. It is commonly used to ensure that an attribute has a value that falls within a predefined list or range. To use `validates_inclusion_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for inclusion. It is typically set as a symbol or string.

    ```php
    static $validates_inclusion_of = [
        'gender' => []
    ];
    ```

- **In (Required):** The `in` option defines the set of values that the attribute's value must be included in. It is required and should be set as an array of valid values.

    ```php
    static $validates_inclusion_of = [
        'gender' => [
            'in' => ['male', 'female', 'non-binary']
        ]
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_inclusion_of = [
        'role' => [
            'in' => ['admin', 'user'],
            'message' => 'Invalid role. Please choose a valid role (admin or user).',
        ],
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_inclusion_of = [
        'subscription_type' => [
            'in' => ['free', 'premium'], 
            'allow_blank' => true
        ]
    ];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_null` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_inclusion_of = [
        'country' => [
            'in' => ['USA', 'Canada'], 
            'allow_null' => true
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_inclusion_of = [
        'status' => [
            'in' => ['active', 'inactive'], 
            'on' => 'update'
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_inclusion_of = [
        'role' => [
            'in' => ['admin', 'user'],
            'message' => 'Invalid role. Please choose a valid role (admin or user).',
            'allow_blank' => true,
        ],
    ];
}
