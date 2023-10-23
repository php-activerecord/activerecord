# Validations

## Introduction
Validations in PHP ActiveRecord are a set of rules and techniques used to ensure that data stored in the database meets specific criteria and adheres to the application's requirements. Proper validation helps maintain data accuracy, integrity, and consistency, while also improving security and user experience.

## Why Use Validations?
Data Integrity: Validations help ensure that your database contains accurate and meaningful data by preventing the storage of incorrect or incomplete information.

Security: Properly validated data can protect your application from common security threats, such as SQL injection and data manipulation attacks.

User Experience: Validations enhance the user experience by providing clear and helpful error messages when data submission fails.

Common Validation Scenarios
In PHP ActiveRecord, you can apply validations to your models to ensure data correctness in various scenarios, including:

1. **Presence Validation**
Use the validatesPresenceOf validation to ensure that specific attributes are not empty or null.

    ```php
    class User extends ActiveRecord\Model {
      static $validates_presence_of = [
        'username' => true,
        'email' => true
      ];
    }
    ```

2. **Length Validation** Control the length of attributes using `validates_length_of` to specify minimum and maximum lengths.

    ```php
    class Post extends ActiveRecord\Model {
      static $validates_length_of = [
        'title' => [
          'minimum' => 5, 
          'maximum' => 100
        ]
      ];
    }
    ```

3. **Uniqueness Validation** Ensure that a particular attribute's value is unique across records using `validates_uniqueness_of`.

    ```php
    class Product extends ActiveRecord\Model {
      static $validatesUniqueness = [
        'sku'=>true
      ];
    }
    ```

4. **Format Validation** Validate attributes against specific formats using `validates_format_of`, often used for email addresses, URLs, or custom formats.

```php
class Article extends ActiveRecord\Model {
  static $validates_format_of = [
    [
        'url' => [
            'with' => '/\A[\w+\-.]+@[a-z\d\-.]+\.[a-z]+\z/i')
        ]
    ];
}
```

5. **Numericality** Ensure that attributes receive only numerical values, and specify legal values.

```php
class Product extends ActiveRecord\Model {
  static $validates_numericality_of = [
    [
        'price' => [
            'greater_than' => 0,
        ]
    ]
  ];
}
```
