# `$has_and_belongs_to_many` (many-to-many)

The `$has_and_belongs_to_many` relationship in ActiveRecord represents a many-to-many association between two database tables. It allows you to specify that records in one table can be associated with multiple records in another table, and vice versa. To define a `$has_and_belongs_to_many` relationship, you need to configure the following properties:

- **Name of the Relationship:** This property specifies the name of the relationship, which is used to access the associated records. It is typically set as a string representing the name of the associated model in plural form.

    ```php
    static $has_and_belongs_to_many = ['categories' => []];
    ```

- **Class Name (Optional):** This property allows you to explicitly specify the class name of the associated model if it differs from the relationship name. This can be useful if your relationship and model names do not follow the default naming conventions.

    ```php
    static $has_and_belongs_to_many = [
        'customCategories' => [
            'className' => 'Category'
        ]
    ];
    ```

- **Association Foreign Key (Optional):** This property allows you to specify the name of the foreign key in the join table that references the associated model. Use it when the foreign key in the join table has a different name from the associated model's primary key. This property is optional.

    ```php
    static $has_and_belongs_to_many = [
        'categories' => [
            'association_foreign_key' => 'category_id']
        ];
    ```

- **Foreign Key (Optional):** By default, ActiveRecord assumes that the foreign key in the join table is derived from the name of the current model and suffixed with "_id" (e.g., "product_id" for a `Product` model). You can specify a different foreign key if needed. This property is optional.

    ```php
    static $has_and_belongs_to_many = [
        'categories' => ['foreign_key' => 'product_id']];
    ```

- **Join Table:** You may specify the name of the join table that connects the two models (if conventions aren't followed, see [join tables](conventions.md#join-tables-for-many-to-many-relationships). This table is responsible for mapping the relationships between the associated records.

    ```php
    static $has_and_belongs_to_many = [
        'categories' => [
            'join_table' => 'custom_categories_products'
        ]
    ];
    ```

Example of Usage:

```php
class Product extends ActiveRecord\Model {
    static $has_and_belongs_to_many = ['categories'];
}

class Category extends ActiveRecord\Model {}

// Finding a product and accessing its categories
$product = Product::find(1);

// Accessing the product's categories
$categories = $product->categories;

// Iterating through and displaying the product's categories
foreach ($categories as $category) {
    echo "Category Name: " . $category->name . "<br>";
    echo "Description: " . $category->description . "<br><br>";
}

```
