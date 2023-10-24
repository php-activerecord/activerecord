# `$validates_presence_of` Validation

The `$validates_presence_of` validation in ActiveRecord allows you to validate whether an attribute's value is present or not. It ensures that the attribute is not empty or null unless specific conditions are met. To use `$validates_presence_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for presence. It is typically set as a symbol or string.

    ```php
    static $validates_presence_of = ['name' => []];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_null` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_presence_of = [
        'middle_name' => [
            'allow_null' => true
        ]
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_presence_of = [
        'comments' => [
            'allow_blank' => true
        ]
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_presence_of = [
        'email' => [
            'message' => 'Email address is required'
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_presence_of = [
        'address' => [
            'on' => 'create'
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_presence_of = [
        'username' => ['allow_blank' => true],
        'email' => ['allow_null' => true],
        'first_name' => [],
    ];
}
