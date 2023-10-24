# `validates_format_of`

The `validates_format_of` validation in ActiveRecord allows you to validate whether an attribute's value matches a specified format using a regular expression. It is commonly used to ensure that attributes such as email addresses, URLs, or phone numbers adhere to a particular pattern. To use `validates_format_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for format. It is typically set as a symbol or string.

    ```php
    static $validates_format_of = ['email' => []];
    ```

- **With (Required):** The `with` option defines the regular expression pattern that the attribute's value must match. It is required and should be set as a regular expression pattern enclosed in forward slashes.

    ```php
    static $validates_format_of = [
        'email' => [
            'with' => '/\A[^@\s]+@([^@\s]+\.)+[^@\s]+\z/'
        ]
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_format_of = [
        'phone_number' => [
            'with' => '/\d{3}-\d{3}-\d{4}/',
            'message' => 'Please enter a valid phone number in the format XXX-XXX-XXXX',
        ],
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_format_of = [
        'website' => [
            'with' => '/^https?:\/\//', 'allow_blank' => true
        ]
    ];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_nil` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_format_of = [
        'ssn' => [
            'with' => '/^\d{3}-\d{2}-\d{4}/', 
            'allow_null' => true
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_format_of = [
        'credit_card' => [
            'with' => '/^\d{16}/', 'on' => 'create'
        ]
    ];
    ```

Example of Usage:

```php
class User extends ActiveRecord\Model {
    static $validates_format_of = [
        'email' => [
            'with' => '/\A[^@\s]+@([^@\s]+\.)+[^@\s]+\z/',
            'message' => 'Please enter a valid email address.',
        ],
    ];
}
```
