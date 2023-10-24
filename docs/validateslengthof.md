# `validates_length_of` Validation

The `validates_length_of` validation in ActiveRecord allows you to validate the length of an attribute's value, such as a string or an array. You can set minimum and maximum length constraints to ensure that the attribute's value falls within the desired range. To use `validates_length_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate. It is typically set as a symbol or string.

    ```php
    static $validates_length_of = [
        'name' => []
    ];
    ```

- **Minimum Length (Optional):** You can set the minimum length that the attribute's value must have by using the `minimum` option. If the value is shorter than the specified minimum, the validation will fail.

    ```php
    static $validates_length_of = [
        'name' => [
            'minimum' => 3
        ]
    ];
    ```

- **Maximum Length (Optional):** You can set the maximum length that the attribute's value can have by using the `maximum` option. If the value exceeds the specified maximum, the validation will fail.

    ```php
    static $validates_length_of = [
        'description' => [
            'maximum' => 255
        ]
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `too_short` and `too_long` options to provide custom error messages.

    ```php
    static $validates_length_of = [
        'username' => [
            'minimum' => 6,
            'message' => 'Username is too short (minimum is 6 characters)',
        ],
        'bio' => [
            'maximum' => 500,
            'message' => 'Bio is too long (maximum is 500 characters)',
        ],
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_length_of = ['username' => ['minimum' => 3, 'maximum' => 20]];
}
```
