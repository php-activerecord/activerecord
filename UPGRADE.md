# 1.x -> 2.0
2.0 has a number of breaking changes.

In 2.0, PHP Active Record aligns more closely with modern Ruby on Rails, introducing a new `Relation` class to chain commands together. Any old functions on `Model` that previously took a `conditions` argument must now move to using a `where` clause:

#### methods

- [Model::find()](#modelfind)
- [Model::count()](#modelcount)
- [Model::delete_all()](#modeldelete_all)
- [Model::find_all_by_...()](#modelfind_all_by_attribute)

#### static properties 

- [Model::$has_one](#modelhas_one)
- [Model::$has_many](#modelhas_many)
- [Model::$belongs_to](#modelbelongs_to)
- [Model::$validates_inclusion_of](#modelvalidates_inclusion_of)

#### other changes
- [Config::set_model_directory](#configset_model_directory)

# Methods

## `Model::find`
Much of the old `find` functionality has been moved to `Relation`. Most notably, `conditions` is no longer supported: 
```php
// 1.x
$books = Book::find([1,2,3], ['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::where('title = ?', 'Walden')->to_a();
```

The `all` argument has been removed in favor of `Relation::all`:
```php
// 1.x
$books = Book::find('all', ['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::where('title = ?', 'Walden')->to_a();
```

The same goes for `first` and `last`:
```php
// 1.x
$book = Book::find('first');
$book = Book::last('last');

// 2.0
$book = Book::last();
```

## `Model::count`
The changes to `count` mirror the changes to `find`:
```php
// 1.x
$numBooks = Book::count(['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::where('title = ?', 'Walden')->count();
```

## `Model::delete_all`
Likewise for `delete_all`:
```php
// 1.x
$numDeleted = Book::delete_all(['conditions'=>['title = ?', 'Walden']]);

// 2.0
$numDeleted = Book::where('title = ?', 'Walden')->delete_all();
```

## `Model::find_all_by_<attribute>`
The `find_all_by...` style of magic method has been removed entirely.
```php
// 1.x
$books = Book::find_all_by_title('Ubik');

// 2.0
$books = Book::where('title = ?', 'Ubik')->to_a();
```

# static properties

The static relationship properties have changed shape, moving from a flat array to a key-config format:

## `Model::$has_one`

```php
// 1.x
class Author extends ActiveRecord 
{
  static $has_one =  [
    ['awesome_person', 'foreign_key' => 'author_id', 'primary_key' => 'author_id'],
    ['parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id']];
}

// 2.0
class Author extends ActiveRecord 
{
  static $has_one =  [
    'awesome_person' => [
      'foreign_key' => 'author_id', 
      'primary_key' => 'author_id'
    ],
    [
      'parent_author' => [
        'class_name' => 'Author', 
        'foreign_key' => 'parent_author_id'
        ]
    ]
  ];
}
```

## `Model::$has_many`

```php
// 1.x
class Person extends ActiveRecord 
{
  static $has_many = [
    ['children', 'foreign_key' => 'parent_id', 'class_name' => 'Person'],
    ['orders']
  ];
}

// 2.0
class Person extends ActiveRecord 
{
  static $has_many = array(
    [
      'children' => [
        'foreign_key' => 'parent_id', 
        'class_name' => 'Person'
      ],
      'orders' => true
   ];
}
```

## `Model::$belongs_to`

```php
// 1.x
class Person extends ActiveRecord 
{
  static $belongs_to = [
    ['parent', 'foreign_key' => 'parent_id', 'class_name' => 'Person'],
    ['orders']
  ];
}

// 2.0
class Person extends ActiveRecord 
{
  static $belongs_to = array(
    [
      'parent' => [
        'foreign_key' => 'parent_id', 
        'class_name' => 'Person'
      ],
   ];
}
```

## `Model::$validates_inclusion_of`

(note: the same changes apply for `Model::$validates_exclusion_of`)

```php
// 1.x
class Book extends ActiveRecord 
{
  public static $validates_exclusion_of = [
        ['name', 'in' => ['blah', 'alpha', 'bravo']]
    ];
}

// 2.0
class Book extends ActiveRecord 
{
  public static $validates_exclusion_of = [
    'name' => [
      'in' => ['blah', 'alpha', 'bravo']]
    ];
}
```

# other changes

## `Config::set_model_directory`

`Config::set_model_directory` has been removed, meaning that the active record library no longer maintains its own autoloader or knowledge of where your models are kept. In 2.0 it is recommended to use your own autoloader to manage your models as you would any other classes in your project.

When setting up relationships, active record will assume that any associations are in the same namespace as the class they are bound to. Alternatively, you can specify a class_name in the config options.

Any of the following should work fine:

```php
// 2.0
namespace test\models;

use ActiveRecord\Model;

class Author extends Model
{
    public static array $has_many = [
        'books' => true // will attempt to load from test\models
    ];
    
    public static array $has_one = [
        'parent_author' => [
            'class_name' => Author::class, 
            'foreign_key' => 'parent_author_id'
        ]
    ];
    
}


```



