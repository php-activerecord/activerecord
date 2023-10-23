# Naming Conventions
Naming conventions in ActiveRecord are essential for maintaining a clean and consistent database schema and for making your code more readable and maintainable. These conventions help developers understand the relationships between database tables, columns, and PHP classes without needing to explicitly specify them. Here's a breakdown of the naming conventions in ActiveRecord:

## Table Names:
Table names should be pluralized, lowercase, and snake_case.
For example, if you have a model called `User`, the corresponding table should be named `users`.

If desired, you may explicitly define the table name:
```php
class User extends ActiveRecord\Model {
    public static string $table_name = "smf_members";
}
```

## Model/Class Names:
Model or class names should be singular, capitalized, and use CamelCase (also known as PascalCase).
The class name should match the singular form of the table name.
If the table is named `users`, the corresponding model/class should be named `User`.

### Primary Key:
By convention, ActiveRecord assumes that the primary key column in a table is named `id`. You don't need to specify this explicitly unless you want to use a different column name as the primary key:

```php
class User extends ActiveRecord\Model {
    public static string $primary_key = "member_id";
}
```

### Foreign Keys:
Foreign keys in tables should be named after the singular form of the associated table followed by _id. For example, if you have a foreign key referencing the users table, it should be named `user_id`.

### Join Tables (for many-to-many relationships):
Join tables that represent many-to-many relationships should be named by combining the singular form of the associated tables in alphabetical order, separated by underscores.
For example, if you have a many-to-many relationship between User and Role, the join table should be named `roles_users`.

### Columns:

Column names should be lowercase and snake_case.
They should be descriptive of the data they store to make it easier to understand the purpose of each column.
Avoid using reserved words or database-specific keywords as column names.
Here's an example that illustrates these conventions:

```php
// Model definition
class User extends ActiveRecord/Model
{
  // The corresponding table is assumed to be named "users"
}
```

```mysql
# typical table structure
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(255),
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

