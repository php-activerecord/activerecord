# Callbacks in PHP ActiveRecord
## Introduction
Callbacks in PHP ActiveRecord are methods that allow you to execute custom code at specific points in a model's lifecycle. They provide hooks to intervene in various stages of record creation, updating, and deletion, as well as to implement data validation. Understanding and using callbacks effectively enhances the flexibility and customizability of your models.

## Why Use Callbacks?
**Customization**: Callbacks enable you to customize and extend the behavior of your models without modifying core code.

**Data Integrity**: They help enforce data integrity by allowing you to run validation checks and perform data transformations.

**Business Logic**: Callbacks let you encapsulate complex business logic within your models, improving maintainability.

## Callbacks Overview 
1. `after_construct`
   The `after_construct` callback is triggered immediately after a new model instance is created, but before it's populated with data. It's useful for performing tasks like setting default attribute values or initializing resources.

    Example:

    ```php
    class User extends ActiveRecord\Model {
      public function after_construct() {
        $this->status = 'active'; // Set a default value for 'status'
      }
    }
    ```
   
2. `before_create`
   The `before_create` callback runs just before a new record is saved to the database. It's ideal for actions such as data manipulation or validation before data insertion.

    Example:

    ```php
    class User extends ActiveRecord\Model {
      public function before_create() {
        $this->password = password_hash($this->password, PASSWORD_DEFAULT); // Hash the password before saving
      }
    }
    ```

3. `after_create` The `after_create` callback is executed after a new record has been successfully created and saved to the database. It's suitable for post-creation actions, like sending notifications or updating related records.

    Example:

    ```php
    class User extends ActiveRecord\Model {
        public function after_create() {
            // Send a welcome email to the user
            Mailer::sendWelcomeEmail($this->email); 
        }
    }
    ```
   
4. `before_update`
      The `before_update` callback is invoked just before an existing record is updated in the database. It allows you to modify data or perform actions on the model instance before saving changes.

       Example:

       ```php
       class User extends ActiveRecord\Model {
        public function before_update() {
          // Update the 'updated_at' timestamp
          $this->updated_at = date('Y-m-d H:i:s'); 
        }
       }
       ```
      
5. `after_update`
   The `after_update` callback is triggered after an existing record has been successfully updated in the database. It can be used for tasks like logging changes or sending notifications.

    Example:

    ```php
    class User extends ActiveRecord\Model {
        public function after_update() {
            Logger::logUserUpdate($this->id); // Log the user's update activity
        }
    }
    ```

6. `before_destroy`
   The `before_destroy` callback is invoked just before a record is deleted from the database. It allows you to perform any necessary cleanup or validation before deletion.

    Example:

    ```php
    class User extends ActiveRecord\Model {
        public function before_destroy() {
            if ($this->hasPendingOrders()) {
                throw new Exception('Cannot delete user with pending orders');
            }
        }
    }
    ```

7. `after_destroy` The `after_destroy` callback is triggered after a record has been successfully deleted from the database. It can be used for tasks like updating related records or sending notifications.

    Example:

    ```php
    class User extends ActiveRecord\Model {
        public function after_destroy() {
            // Clear user association from orders
            Order::where('user_id', $this->id)->update(array('user_id' => null)); 
        }
    }
    ```

