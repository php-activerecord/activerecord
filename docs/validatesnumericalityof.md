# `validates_numericality_of`

The `validates_numericality_of` validation in ActiveRecord allows you to validate whether an attribute's value is a valid numeric value. It ensures that the attribute contains a number and optionally checks for numerical constraints such as minimum and maximum values. To use `validates_numericality_of`, you need to specify the following options:

- **Attribute Name:** This option specifies the name of the attribute you want to validate for numericality. It is typically set as a symbol or string.

    ```php
    static $validates_numericality_of = [
        'age' => []
    ];
    ```

- **Message (Optional):** To customize the error message displayed when the validation fails, you can use the `message` option to provide a custom error message.

    ```php
    static $validates_numericality_of = [
        'price' => [
            'message' => 'Price must be a valid number.',
        ],
    ];
    ```

- **Only Integer (Optional):** The `only_integer` option allows you to specify whether the attribute's value must be an integer. By default, `only_integer` is set to `false`, meaning that any numeric value is accepted. You can set it to `true` to enforce integer values.

    ```php
    static $validates_numericality_of = [
        'quantity' => [
            'only_integer' => true
        ]
    ];
    ```

- **Greater Than (Optional):** The `greater_than` option allows you to specify a minimum value that the attribute's value must be greater than. It ensures that the numeric value is greater than the specified threshold.

    ```php
    static $validates_numericality_of = [
        'score' => [
            'greater_than' => 0
        ]
    ];
    ```

- **Greater Than Or Equal To (Optional):** The `greater_than_or_equal_to` option allows you to specify a minimum value that the attribute's value must be greater than or equal to. It ensures that the numeric value is greater than or equal to the specified threshold.

    ```php
    static $validates_numericality_of = [
        'temperature' => [
            'greater_than_or_equal_to' => -20
        ]
    ];
    ```

- **Less Than (Optional):** The `less_than` option allows you to specify a maximum value that the attribute's value must be less than. It ensures that the numeric value is less than the specified threshold.

    ```php
    static $validates_numericality_of = [
        'discount' => [
            'less_than' => 100
        ]
    ];
    ```

- **Less Than Or Equal To (Optional):** The `less_than_or_equal_to` option allows you to specify a maximum value that the attribute's value must be less than or equal to. It ensures that the numeric value is less than or equal to the specified threshold.

    ```php
    static $validates_numericality_of = [
        'percentage' => [
            'less_than_or_equal_to' => 100
        ]
    ];
    ```

- **Equal To (Optional):** The `equal_to` option allows you to specify a value that the attribute's value must be equal to. It ensures that the numeric value matches the specified value.

    ```php
    static $validates_numericality_of = [
        'quantity' => [
            'equal_to' => 10
        ]
    ];
    ```

- **Allow Blank (Optional):** The `allow_blank` option allows you to specify whether the attribute is allowed to be empty (e.g., an empty string). By default, `allow_blank` is set to `false`, meaning that the attribute must not be blank. You can set it to `true` to allow blank values.

    ```php
    static $validates_numericality_of = [
        'price' => [
            'allow_blank' => true
        ]
    ];
    ```

- **Allow Null (Optional):** The `allow_null` option allows you to specify whether the attribute is allowed to be null. By default, `allow_nil` is set to `false`, meaning that the attribute must not be null. You can set it to `true` to allow null values.

    ```php
    static $validates_numericality_of = [
        'height' => [
            'allow_null' => true
        ]
    ];
    ```

- **On (Optional):** The `on` option allows you to specify when the validation should occur. By default, it is set to `'save'`, which means the validation is performed when the record is saved. You can set it to `'create'` to validate only during the creation of a new record or `'update'` to validate only during updates.

    ```php
    static $validates_numericality_of = [
        'quantity' => [
            'on' => 'create'
        ]
    ];
    ```

Example of Usage:

```php
class Product extends ActiveRecord\Model {
    static $validates_numericality_of = [
      'quantity' => [
        'only_integer' => true, 
        'greater_than' => 0
        ]
    ];
}
```
