# 1.x -> 2.x
2.x has a number of breaking API changes to be aware of. First, be aware that the minimum required PHP version is now 8.1. Other than that, there are some key API changes to be aware of.

## Methods

### `Model::find`

Much of the old `find` functionality has been moved to `Relation`. Most notably, the `conditions` argument is no longer supported, and has been replaced by `where()`: 
```php
// 1.x
$books = Book::find([1,2,3], ['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::where('title = ?', 'Walden')->to_a();
```

Also, in 2.x we have fixed a bug around calling `find` with an empty array. It will now throw a `RecordNotFound` exception, as it should. If you were relying on the old behavior, you should switch to `where`:
```php
// 1.x
$ids = [];
$books = Book::find($ids);

// 2.0
$books::find($ids); // danger! now throws RecordNotFound
$books = Book::where(['book_id' => $ids])->to_a(); // this is okay
```


The `all` argument has been removed in favor of `Relation::all` (which does nothing but return a relation object), or `Relation::where():
```php
// 1.x
$books = Book::find('all', ['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::all()->where('title = ?', 'Walden')->to_a();
```

The same goes for `first` and `last`:
```php
// 1.x
$book = Book::find('first');
$book = Book::find('last');

// 2.0
$book = Book::first();
$book = Book::last();
```

### `Model::all`
`Model::all()` no longer takes any arguments, and now returns an iterable `Relation` instead of an array of models:
```php
// 1.x
$books = Book::all(['conditions'=>['title = ?', 'Walden']]);
foreach($books as $book) {}

// 2.0
$books = Book::where('title = ?', 'Walden')->all();
foreach($books as $book) {}
```

The `all` argument has been removed in favor of `Relation::all`:
```php
// 1.x
$books = Book::find('all', ['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::all()->where('title = ?', 'Walden')->to_a();
```

### `Model::count`
The changes to `count` mirror the changes to `find`:
```php
// 1.x
$numBooks = Book::count(['conditions'=>['title = ?', 'Walden']]);

// 2.0
$books = Book::where('title = ?', 'Walden')->count();
```

### `Model::delete_all`
Likewise for `delete_all`:
```php
// 1.x
$numDeleted = Book::delete_all(['conditions'=>['title = ?', 'Walden']]);

// 2.0
$numDeleted = Book::where('title = ?', 'Walden')->delete_all();
```

### `Model::update_all`
`update_all` has undergone a similar transformation, and now simply takes a string or hash of attributes as an argument:
```php
// 1.x
$numUpdated = Book::update_all([
  'conditions'=>['title = ?', 'Walden'],
  'set' => ['author_id' => 1]
]);

// 2.0
$numUpdated = Book::where(['title = ?', 'Walden'])->update_all([
  'author_id' => 1
]);
```

### `Model::find_all_by_<attribute>`
The `find_all_by...` style of magic method has been removed entirely.
```php
// 1.x
$books = Book::find_all_by_title('Ubik');

// 2.0
$books = Book::where('title = ?', 'Ubik')->to_a();
```

### `Model::table`
The static `table` accessor on `Model` is now protected. If you were making calls directly on `Table`, you will need to refactor your code.
```php
// 1.x
Book::table()->update($attributes, $where);

// 2.0
Book::where($where)->update_all($attributes);
```

If you do need access to the table instance for some reason, you can still get to it:
```php
  $table = Table::load(Book::class);
```


## Static Properties

The static relationship properties have changed shape, moving from a flat array to a key-config format:

### `Model::$has_one`

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

### `Model::$has_many`

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

### `Model::$belongs_to`

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

### `Model::$validates_inclusion_of`

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

## Other Changes

### `Table::update`
You generally shouldn't be working directly with a `Table` instance, but if you are you should be aware that the `update` method has changed shape:
```php
// 1.x 
$table = Book::table();
$table->update([ 'title' => 'Walden` ], ['author_id` => 1]);

// 2.0
$table = Table::load(Book::class);
$options = [
    'conditions' => [new WhereClause(['author_id` => 1])]
];
$table->update([ 'title' => 'Walden' ], $options); // where $options is a RelationOptions
```

### `Table::delete`
You generally shouldn't be working directly with a `Table` instance, but if you are you should be aware that the `delete` method has changed shape:
```php
// 1.x 
$table = Book::table();
$table->delete(['author_id' => 1]);

// 2.0
$table = Table::load(Book::class);
$options = [
    'conditions' => [new WhereClause(['author_id` => 1])]
];
$table->delete($options); // where $options is a RelationOptions.
```

### `Config::set_model_directory`

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

### exceptions location

All crafted exceptions have moved to a new home in the `Exceptions` namespace.

```php
// 1.x
use ActiveRecord\RecordNotFound

try {
...
}
catch(RecordNotFound)

// 2.0
use ActiveRecord\Exception\RecordNotFound

try {
...
}
catch(RecordNotFound)
```


