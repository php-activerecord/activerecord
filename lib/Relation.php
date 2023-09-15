<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Exception\ValidationsArgumentError;

/**
 * @template TModel of Model
 *
 * @phpstan-import-type RelationOptions from Types
 */
class Relation implements \Iterator
{
    protected \Generator $generator;

    public function rewind(): void
    {
        $this->generator = $this->table()->find($this->options);
        $this->generator->rewind();
    }

    /**
     * @return TModel|null
     */
    public function current(): ?Model
    {
        return $this->generator->current();
    }

    public function key(): mixed
    {
        return $this->generator->key();
    }

    public function next(): void
    {
        $this->generator->next();
    }

    public function valid(): bool
    {
        return $this->generator->valid();
    }

    /**
     * @var array<string>
     */
    private array $alias_attribute;

    /**
     * @var class-string
     */
    private string $className;

    private ?Table $tableImpl = null;

    /**
     * @var RelationOptions
     */
    private array $options = [];

    /**
     * @param class-string    $className
     * @param array<string>   $alias_attribute
     * @param RelationOptions $options
     */
    public function __construct(string $className, array $alias_attribute, array $options = [])
    {
        $this->options = $options;
        $this->alias_attribute = $alias_attribute;
        $this->className = $className;
    }

    /**
     * @return Table<TModel>
     */
    private function table(): Table
    {
        if (null === $this->tableImpl) {
            $this->tableImpl = Table::load($this->className);
        }

        return $this->tableImpl;
    }

    /**
     * Modifies the SELECT statement for the query so that only certain
     * fields are retrieved.
     *
     * // string
     * Book::select("name")->take();  // SELECT name FROM `books`
     *                                // ORDER BY book_id ASC LIMIT 1
     *
     * // array
     * Book::select([                 // SELECT name, publisher FROM `books`
     *   "name",                      // ORDER BY book_id ASC LIMIT 1
     *   "publisher"
     * ])
     *
     * // list of strings
     * Book::select(                  // SELECT name, publisher FROM `books`
     *   "name",                      // ORDER BY book_id ASC LIMIT 1
     *   "publisher"
     * )
     *
     * You can also use one or more strings, which will be used unchanged as SELECT fields:
     *
     * Book::select([                 // SELECT name as title, 123 as ISBN
     *   'name AS title',             // FROM `authors` ORDER BY title desc LIMIT 1
     *   '123 AS ISBN'
     * ])
     *
     * If an alias was specified, it will be accessible from the resulting objects:
     *
     * Book::select('name AS title')
     *   ->first()
     *   ->title
     *
     * Accessing attributes of an object that do not have fields retrieved by a select
     * except +id+ will throw ActiveRecord::UndefinedPropertyException:
     *
     * Book::select("name")->first()->publisher
     * => ActiveRecord::UndefinedPropertyException: missing attribute: publisher
     *
     * @see UndefinedPropertyException
     *
     * @return Relation<TModel>
     */
    public function select(): Relation
    {
        $arg = static::toSingleArg(...func_get_args());
        $this->options['select'] ??= [];
        $this->options['select'] = array_merge((array) $this->options['select'], (array) $arg);

        return $this;
    }

    /**
     * Performs JOINs on +args+. The given symbol(s) should match the name of
     * the association(s).
     *
     * User::joins('posts') // SELECT "users".*
     *                      // FROM "users"
     *                      // INNER JOIN "posts" ON "posts"."user_id" = "users"."id"
     *
     * // Multiple joins:
     *
     * User::joins(['posts', 'account']) // SELECT "users".*
     *                                   // FROM "users"
     *                                   // INNER JOIN "posts" ON "posts"."user_id" = "users"."id"
     *                                   // INNER JOIN "accounts" ON "accounts"."id" = "users"."account_id"
     *
     * @param string|array<string> $joins
     *
     * @returns Relation<TModel>
     */
    public function joins(string|array $joins): Relation
    {
        $this->options['joins'] = $joins;

        return $this;
    }

    /**
     * @return Relation<TModel>
     */
    public function order(string $order): Relation
    {
        $this->options['order'] = $order;

        return $this;
    }

    /**
     * Specifies a limit for the number of records to retrieve.
     *
     * User::limit(10) // generated SQL has 'LIMIT 10'
     * User::limit(10)->limit(20) # generated SQL has 'LIMIT 20'
     *
     * @return Relation<TModel>
     */
    public function limit(int $limit): Relation
    {
        $this->options['limit'] = $limit;

        return $this;
    }

    /**
     * @return Relation<TModel>
     */
    public function group(string $columns): Relation
    {
        $this->options['group'] = $columns;

        return $this;
    }

    /**
     * Specifies the number of rows to skip before returning rows.
     *
     *  User::offset(10) // generated SQL has "OFFSET 10"
     *
     * Should be used with order.
     *
     *  User::offset(10)->order("name ASC")
     *
     * @return Relation<TModel>
     */
    public function offset(int $offset): Relation
    {
        $this->options['offset'] = $offset;

        return $this;
    }

    /**
     * Allows to specify a HAVING clause. Note that you can't use HAVING
     * without also specifying a GROUP clause.
     *
     * Order::having('SUM(price) > 30')->group('user_id')
     *
     * @return Relation<TModel>
     */
    public function having(string $having): Relation
    {
        $this->options['having'] = $having;

        return $this;
    }

    /**
     * @return Relation<TModel>
     */
    public function from(string $from): Relation
    {
        $this->options['from'] = $from;

        return $this;
    }

    /**
     * The purpose of includes is to solve N+1 problems in relational situations.
     * Let's say you have an Author, and authors write many Books. You would
     * ordinarily need to execute one query (+1) to retrieve the Author,
     * then one query for each of his books (N).
     *
     * You can avoid this problem by specifying relationships to be included in the
     * result set. For example:
     *
     * $users = User::includes('address');
     *  foreach($users as $user) {
     *    $user->address->city
     *  }
     *
     * ...allows you to access the address attribute of the User model without
     * firing an additional query. This will often result in a performance
     * improvement over a simple join.  You can also specify multiple
     * relationships, like this:
     *
     * $users = User::includes('address', 'friends');
     *
     * Loading nested relationships is possible using a Hash:
     *
     * $users = User::includes(
     *   'address',
     *   'friends' => ['address', 'followers']
     * )
     *
     * @return Relation<TModel>
     */
    public function includes(): Relation
    {
        $includes = static::toSingleArg(...func_get_args());
        $this->options['include'] = $includes;

        return $this;
    }

    /**
     * Returns a new relation, which is the result of filtering the
     * current relation according to the conditions in the arguments.
     *
     * // string (not recommended; see alternatives below)
     * Book::where("book_id = '2'");
     * // SELECT * from clients where orders_count = '2';
     *
     * // array
     * If an array is passed, then the first element of the array
     * is treated as a template, and the remaining elements are
     * inserted into the template to generate the condition.
     * Active Record takes care of building the query to avoid
     * injection attacks, and will convert from the PHP type to the
     * database type where needed. Elements are inserted into the string
     * in the order in which they appear.
     *
     * User.where([
     *   "name = ? and email = ?",
     *   "Joe",
     *   "joe@example.com"
     * ])
     * # SELECT * FROM users WHERE name = 'Joe' AND email = 'joe@example.com';
     *
     * Alternatively, you can use named placeholders in the template, and pass
     * a hash as the second element of the array. The names in the template
     * are replaced with the corresponding values from the hash.
     *
     * User.where([
     *   "name = :name and email = :email", [
     *     name => "Joe",
     *     email => "joe@example.com"
     * ]])
     * # SELECT * FROM users WHERE name = 'Joe' AND email = 'joe@example.com';
     *
     * If where is called with multiple arguments, these are treated as
     * if they were passed as the elements of a single array.
     *
     * User.where("name = :name and email = :email", [
     *   name => "Joe",
     *   email => "joe@example.com"
     * ])
     * # SELECT * FROM users WHERE name = 'Joe' AND email = 'joe@example.com';
     *
     * # hash
     * `where` will also accept a hash condition, in which the keys are fields
     * and the values are values to be searched for.
     *
     * Fields can be symbols or strings. Values can be single values, arrays, or ranges.
     *
     * User.where([
     *   'name' => "Joe",
     *   'email' => "joe@example.com"
     * ])
     * # SELECT * FROM users WHERE name = 'Joe' AND email = 'joe@example.com'
     *
     * @return Relation<TModel>
     */
    public function where(): Relation
    {
        $this->options['conditions'] ??= [];

        $arg = static::toSingleArg(...func_get_args());
        $expression = WhereClause::from_arg($arg);

        $this->options['conditions'][] = $expression;

        return $this;
    }

    public static function toSingleArg(): mixed
    {
        $args = func_get_args();
        if (count($args) > 1) {
            $arg = $args;
        } else {
            $arg = $args[0];
        }

        return $arg;
    }

    /**
     * Returns a new relation expressing WHERE !(condition) according to the
     * conditions in the arguments.
     *
     * not accepts conditions as a string, array, or hash. @See Relation::where
     * for more details on each format.
     *
     * User::where()                  // SELECT * FROM users
     *   ->not("name = 'Jon'")        // WHERE !(name = 'Jon')
     *
     * User::where()                  // SELECT * FROM users
     *   ->not([                      // WHERE !(name = 'Jon')
     *      "name = ?",
     *      "Jon"
     *   ])
     *
     * User::where()                  // SELECT * FROM users
     *   ->not('name', "Jon")         // WHERE name != 'Jon'
     *
     * User::where()                  // SELECT * FROM users
     *   ->not('name', null)          // WHERE !(name IS NULL)
     *
     * User::where()                  // SELECT * FROM users
     *   ->not([                      // WHERE !(name == 'Jon' AND role == 'admin')
     *     'name' => "Jon",
     *     'role' => "admin"
     *   ])
     *
     * If there is a non-nil condition on a nullable column in the hash condition,
     * the records that have nil values on the nullable column won't be returned.
     *
     * User::create( [
     *   'nullable_country' => null
     * ])
     * User::where()->not([             // SELECT * FROM users
     *   'nullable_country' => "UK"     // WHERE NOT (nullable_country = 'UK')  // => []
     * ])
     *
     * @return Relation<TModel>
     */
    public function not(): Relation
    {
        $this->options['conditions'] ??= [];

        $arg = static::toSingleArg(...func_get_args());
        $expression = WhereClause::from_arg($arg, true);

        $this->options['conditions'][] = $expression;

        return $this;
    }

    /**
     * Sets readonly attributes for the returned relation. If value is true (default),
     * attempting to update a record will result in an error.
     *
     * $users = User::readonly()
     * $users->first()->save()  // throws exception ActiveRecord::ReadOnlyRecord: User is marked as readonly
     */
    public function readonly(bool $readonly): Relation
    {
        $this->options['readonly'] = $readonly;

        return $this;
    }

    /**
     * Find by id - This can either be a specific id (1),
     * a list of ids (1, 5, 6), or an array of ids ([5, 6, 10]).
     *
     * If one or more records cannot be found for the requested ids,
     * then ActiveRecord::RecordNotFound will be raised.
     *
     * If the primary key is an integer, find by id coerces its arguments by using to_i.
     *
     * Person.find(1)          # returns the object for ID = 1
     * Person.find("1")        # returns the object for ID = 1
     * Person.find("31-sarah") # returns the object for ID = 31
     * Person.find(1, 2, 6)    # returns an array for objects with IDs in (1, 2, 6)
     * Person.find([7, 17])    # returns an array for objects with IDs in (7, 17)
     * Person.find([1])        # returns an array for the object with ID = 1
     *
     * Person.where("administrator = 1").order("created_on DESC").find(1)
     *
     * Person.find(-1)          # throws RecordNotFound
     *
     * Person.find()           # throws ValidationsArgumentError as there's no where statement to execute
     *
     * @throws RecordNotFound if any of the records cannot be found
     *
     * @return Model|array<Model>
     */
    public function find()
    {
        $args = func_get_args();
        $num_args = count($args);

        if (0 === $num_args) {
            throw new ValidationsArgumentError('find requires at least one argument');
        }

        // find by pk
        if (1 === $num_args) {
            $args = $args[0];
        }

        $single = !is_array($args) || !array_is_list($args);

        $options = $this->options;
        $options['conditions'] ??= [];
        $options['conditions'][] = $this->pk_conditions($args);
        $options['mapped_names'] = $this->alias_attribute;

        $list = iterator_to_array($this->table()->find($options));
        if (is_array($args) && count($list) != count($args)) {
            throw new RecordNotFound('found ' . count($list) . ', but was looking for ' . count($args));
        }

        return $single ? ($list[0] ?? throw new RecordNotFound('tbd')) : $list;
    }

    /**
     * Returns a record (or N records if a parameter is supplied) without any implied
     * order. The order will depend on the database implementation.
     * If an order is supplied it will be respected.
     *
     *   Person::take() # returns an object fetched by SELECT * FROM people LIMIT 1
     *   Person::take(5) # returns 5 objects fetched by SELECT * FROM people LIMIT 5
     *   Person::where(["name LIKE '%?'", name])->take()
     *
     * @return TModel|array<TModel>|null
     */
    public function take(int $limit = null): mixed
    {
        $this->limit($limit ?? 1);
        if (!isset($limit)) {
            $models = $this->to_a();

            return $models[0] ?? null;
        }

        return $this->to_a();
    }

    /**
     * Find the first record (or first N records if a parameter is supplied).
     * If no order is defined it will order by primary key.
     *
     * Person::first() // returns the first object fetched by SELECT * FROM people ORDER BY people.id LIMIT 1
     * Person::where(["user_name = ?", user_name])->first()
     * Person::where(["user_name = :u", { u: user_name }])->first()
     * Person::order("created_on DESC")->offset(5).first()
     * Person.first(3) // returns the first three objects fetched by SELECT * FROM people ORDER BY people.id LIMIT 3
     */
    public function first(int $limit = null): mixed
    {
        $this->limit($limit ?? 1);

        $pk = $this->table()->pk;
        if (!empty($pk) && !array_key_exists('order', $this->options) && !array_key_exists('group', $this->options)) {
            $this->options['order'] = implode(' ASC, ', $this->table()->pk) . ' ASC';
        }

        $models = $this->to_a();

        return isset($limit) ? $models : $models[0] ?? null;
    }

    /**
     * Find the last record (or last N records if a parameter is supplied). If no order is
     * defined it will order by primary key.
     *
     * Person::last()  // returns the last object fetched by SELECT * FROM people
     * Person::where(["user_name = ?", user_name])->last()
     * Person::order("created_on DESC")->offset(5)->last()
     * Person::last(3) // returns the last three objects fetched by SELECT * FROM people.
     *
     * Returns a single record, unless limit is supplied, in which case an array of
     * records is returned. If no records are found, returns null.
     *
     * @return TModel|array<TModel>|null
     */
    public function last(int $limit = null): mixed
    {
        $this->limit($limit ?? 1);

        if (array_key_exists('order', $this->options)) {
            if (str_contains($this->options['order'], implode(' ASC, ', $this->table()->pk) . ' ASC')) {
                $this->options['order'] = SQLBuilder::reverse_order((string) $this->options['order']);
            }
        } else {
            $this->options['order'] = implode(' DESC, ', $this->table()->pk) . ' DESC';
        }

        $models = $this->to_a();

        return isset($limit) ? $models : $models[0] ?? null;
    }

    /**
     * Converts relation objects to array.
     *
     * @return array<TModel> All the rows that matches query. If no rows match, returns []
     */
    public function to_a(): array
    {
        $this->options['mapped_names'] = $this->alias_attribute;

        return iterator_to_array($this->table()->find($this->options));
    }

    /**
     * Returns a Relation scope object.
     *
     * $posts = Post::all()
     * $posts->count() // Fires "select count(*) from  posts" and returns the count
     * foreach($posts as $post) { echo $p->name; } # Fires "select * from posts" and loads post objects
     *
     * $fruits = Fruit::all()
     * $fruits = $fruits->where(['color' => 'red'])
     * $fruits = $fruits->limit(10)
     *
     * @return Relation<TModel>
     */
    public function all(): Relation
    {
        return $this;
    }

    /**
     * Get a count of qualifying records.
     *
     * ```
     * People::count() // all
     * People::count('name'); // SELECT COUNT("people"."age") FROM "people"
     * People::count(['conditions' => "age > 30"])
     *
     * ```
     *
     * @see find
     *
     * @return int Number of records that matched the query
     */
    public function count(): int
    {
        unset($this->options['select']);
        $this->select('COUNT(*)');

        $table = $this->table();
        $sql = $table->options_to_sql($this->options);
        $values = $sql->get_where_values();

        return $this->table()->conn->query_and_fetch_one($sql->to_s(), $values);
    }

    /**
     * Returns true if a record exists in the table that matches the id or conditions given, or false
     * otherwise. The argument can take six forms:
     *
     *   * Integer - Finds the record with this primary key.
     *   * String - Finds the record with a primary key corresponding to this string (such as '5').
     *   * Array - Finds the record that matches these where-style conditions (such as ['name LIKE ?', "%#{query}%"]).
     *   * Hash - Finds the record that matches these where-style conditions (such as ['name' => 'David']).
     *   * false - Returns always false.
     *   * No args - Returns false if the relation is empty, true otherwise.
     *
     *  // no arguments
     *  Person::exists()
     *
     *  // by primary key
     *  Person::exists(5)
     *  Person::exists('5')
     *
     *  // by array conditions
     *  Person::exists(['name LIKE ?', "%#{query}%"])
     *
     *  // by hash conditions
     *  Person::exists(['id': [1, 4, 8]])
     *  Person::exists([name: 'David'])
     *
     *  // by boolean
     *  Person::exists(false)
     *
     *  // chained
     *  Person::where([name=> 'Spartacus', 'rating' => 4]).exists()
     */
    public function exists(mixed $conditions = []): bool
    {
        if (is_bool($conditions) && empty($conditions)) {
            return false;
        }

        if (is_array($conditions) || is_hash($conditions)) {
            !empty($conditions) && $this->where($conditions);
        } else {
            try {
                static::find($conditions);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        $res = $this->count();

        return $res > 0;
    }

    /**
     * Returns a hash containing the names => values of the primary key.
     *
     * @param int|array<number|string> $args Primary key value(s)
     */
    private function pk_conditions(int|array $args): WhereClause
    {
        return new WhereClause([$this->table()->pk[0] => $args], []);
    }
}
