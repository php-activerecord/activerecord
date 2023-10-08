<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\ValidationsArgumentError;

/**
 * @template TModel of Model
 *
 * @phpstan-import-type RelationOptions from Types
 * @phpstan-import-type Attributes from Types
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
     * @var bool Whether $this->none() was ever called
     */
    private bool $isNone = false;

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
     * Returns a blank Relation and fires no queries.
     *
     * @returns Relation<TModel>
     */
    public function none(): Relation
    {
        $this->isNone = true;

        return $this;
    }

    /**
     * Enables the use of dynamic finders.
     *
     * Dynamic finders are just an easy way to do queries quickly without having to
     * specify an options array with conditions in it.
     *
     * ```
     * Person::select('id')->find_by_first_name('Tito');
     * Person::select('id')->order('last_name DESC')->find_by_first_name('Tito');
     * Person::select('id')->find_by_first_name_and_last_name('Tito','the Grief');
     * Person::select('id')->find_by_first_name_or_last_name('Tito','the Grief');
     * ```
     *
     * You can also create the model if the find call returned no results:
     *
     * ```
     * Person::find_or_create_by_name('Tito');
     *
     * # would be the equivalent of
     * if (!Person::find_by_name('Tito'))
     *   Person::create(['Tito']);
     * ```
     *
     * Some other examples of find_or_create_by:
     *
     * ```
     * Person::select('id')->find_or_create_by_name_and_id('Tito',1);
     * Person::find_or_create_by_name_and_id(['name' => 'Tito', 'id' => 1]);
     * ```
     *
     * @param $method Name of method
     * @param $args   Method args
     *
     * @throws ActiveRecordException If the method name does not start with the find_by or find_or_create_by templates
     *
     * @see find
     *
     * @return Model|null The first model that meets the find by criteria, or null if no row meets that criteria
     */
    public function __call(string $method, mixed $args): Model|null
    {
        $create = false;

        if ($attributes = $this->extract_dynamic_vars($method, 'find_or_create_by')) {
            // can't take any finders with OR in it when doing a find_or_create_by
            if (false !== strpos($attributes, '_or_')) {
                throw new ActiveRecordException("Cannot use OR'd attributes in find_or_create_by");
            }
            $create = true;
            $method = 'find_by_' . $attributes;
        }

        $attributes = $this->extract_dynamic_vars($method, 'find_by');
        assert('' !== $attributes, new ActiveRecordException("Call to undefined method: $method"));

        $options = $this->options;

        $options['conditions'] ??= [];
        $options['conditions'][] = WhereClause::from_underscored_string($this->table()->conn, $attributes, $args, $this->alias_attribute);

        $options['limit'] = 1;
        $ret = $this->_to_a($options);

        if (0 === count($ret)) {
            if ($create) {
                return $this->className::create(SQLBuilder::create_hash_from_underscored_string(
                    $attributes,
                    $args,
                    $this->alias_attribute
                ));
            }

            return null;
        }

        return $ret[0];
    }

    private function extract_dynamic_vars(string $methodName, string $dynamicPart): string
    {
        if (str_starts_with($methodName, $dynamicPart)) {
            $attributes = substr($methodName, strlen($dynamicPart) + 1);

            return $attributes;
        }

        return '';
    }

    /**
     * Use pluck as a shortcut to select one or more attributes without
     * loading an entire record object per row.
     *
     * $names = Person::pluck('name');
     *
     * instead of
     *
     * $names = array_map(fn($person) =>$person->name, Person::all()->to_a());
     *
     * Pluck returns an Array of attribute values type-casted to match
     * the plucked column names, if they can be deduced.
     *
     * Person::pluck('name')                // SELECT people.name FROM people
     *  => ['David', 'Jeremy', 'Jose']
     *
     * Person::pluck('id', 'name');         // SELECT people.id, people.name FROM people
     *  => [[1, 'David'], [2, 'Jeremy'], [3, 'Jose']]
     *
     * Person::distinct()->pluck('role');   // SELECT DISTINCT role FROM people
     *  => ['admin', 'member', 'guest']
     *
     * Person::where(['age' => 21])         // SELECT people.id FROM people WHERE people.age = 21 LIMIT 5
     *  ->limit(5).pluck('id')
     * => [2, 3]
     *
     * @see Relation::ids()
     *
     * @return array<mixed>
     */
    public function pluck(): array
    {
        $args = func_get_args();
        if (0 === count($args)) {
            throw new ValidationsArgumentError('pluck requires at least one argument');
        }

        $options = array_merge($this->options, ['select' => [static::toSingleArg(...$args)]]);
        $table = $this->table();
        $sql = $table->options_to_sql($options);
        $retValue = iterator_to_array(
            $table->conn->query_and_fetch($sql->to_s(), $sql->get_where_values(), \PDO::FETCH_NUM)
        );

        return array_map(static function ($row) {
            return 1 == count($row) ? $row[0] : $row;
        }, $retValue);
    }

    /**
     * Plucks the primary keys from the table
     *
     * @return array<mixed>
     */
    public function ids(): array
    {
        $pk = $this->table()->pk;

        return $this->pluck(1 === count($pk) ? $pk[0] : $pk);
    }

    /**
     * Selects columns in a table, anding previous calls to select()
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
        $this->options['select'] ??= [];
        assert(is_array($this->options['select']));
        $this->options['select'][] = static::toSingleArg(...func_get_args());

        return $this;
    }

    /**
     * Selects columns in a table, overriding previous calls to select()
     *
     * @return Relation<TModel>
     */
    public function reselect(): Relation
    {
        $this->options['select'] = [static::toSingleArg(...func_get_args())];

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
     * @return Relation<TModel>
     */
    public function joins(string|array $joins): Relation
    {
        $this->options['joins'] = $joins;

        return $this;
    }

    /**
     * Applies an ```ORDER BY``` clause to a query.
     *
     * This could be a source of SQL injection, so only strings composed of plain
     * column names and simple ```function(column_name)``` expressions
     * with optional ASC/DESC modifiers are allowed.
     *
     * User::order('name')              // SELECT "users".*
     *                                  // FROM "users" ORDER BY name
     *
     * User::order('name DESC')         // SELECT "users".*
     *                                  // FROM "users" ORDER BY name DESC
     *
     * User::order('name DESC, email')  // SELECT "users".*
     *                                  // FROM "users" ORDER BY name DESC, email
     *
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
     * Allows to specify a group attribute:
     *
     * User::group("name")      // SELECT "users".*
     *                          // FROM "users" GROUP BY name
     *
     * Returns an array with records based on the group attribute:
     *
     * User::select(["id", "name"])             // [
     *                                          //      <User id: 1, name: "Oscar">,
     *                                          //      <User id: 2, name: "Oscar">,
     *                                          //      <User id: 3, name: "Foo">
     *                                          // ]
     *
     * User::group('name')                      // [
     *                                          //      <User id: 3, name: "Foo", ...>,
     *                                          //      <User id: 2, name: "Oscar", ...>
     *                                          // ]
     *
     * User::group('name AS grouped_name, age') // [
     *                                          //      <User id: 3, name: "Foo", age: 21, ...>,
     *                                          //      <User id: 2, name: "Oscar", age: 21, ...>,
     *                                          //      <User id: 5, name: "Foo", age: 23, ...>
     *                                          // ]
     *
     * Passing in an array of attributes to group by is also supported.
     *
     * User::select(['id', 'first_name'])       // [
     *  ->group(['id', 'first_name'])           //      <User id: 1, first_name: "Bill">
     *  ->first(3)                              //      <User id: 2, first_name: "Earl">,
     *                                          //      <User id: 3, first_name: "Beto">
     *                                          // ]
     *
     * A list of arguments is also supported.
     *
     * User::select('id', 'first_name')         // [
     *  ->group('id', 'first_name')             //      <User id: 1, first_name: "Bill">
     *  ->first(3)                              //      <User id: 2, first_name: "Earl">,
     *                                          //      <User id: 3, first_name: "Beto">
     *                                          // ]
     *
     * @return Relation<TModel>
     */
    public function group(): Relation
    {
        $this->options['group'] = static::toSingleArg(...func_get_args());

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
     * Specifies the table from which the records will be fetched. For example:
     *
     * Topic::select('title')           // SELECT title FROM posts
     *   ->from('posts');
     *
     * Aliases are permitted:
     *
     * Topic::()
     *   ->from('topics as old_topics');  // SELECT * FROM posts as old_topics
     *
     * As are subqueries:
     *
     * Topic::()
     *   ->from('SELECT * FROM (SELECT * from posts where title="Greetings, all" as friendly_posts');
     *
     *
     * Be cautious when using this method, as it can make the
     * query more difficult to understand and maintain.
     *
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
     * User::where([
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
     * User::where([
     *   "name = :name and email = :email", [
     *     name => "Joe",
     *     email => "joe@example.com"
     * ]])
     * # SELECT * FROM users WHERE name = 'Joe' AND email = 'joe@example.com';
     *
     * If where is called with multiple arguments, these are treated as
     * if they were passed as the elements of a single array.
     *
     * User::where("name = :name and email = :email", [
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
     * User::where([
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
        if (1 === count($args)) {
            $args = $args[0];
        }

        return $args;
    }

    /**
     * Returns a new relation expressing WHERE !(condition) according to the
     * conditions in the arguments.
     *
     * {@link not()} accepts conditions as a string, array, or hash. @See Relation::where
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
     * Specifies whether the records should be unique or not. For example:
     *
     * User::select('name') // Might return two records with the same name
     *
     * User::select('name')->distinct() // Returns 1 record per distinct name
     *
     * User::select('name')->distinct()->distinct(false) // You can also remove the uniqueness
     *
     * @return Relation<TModel>
     */
    public function distinct(bool $distinct=true): Relation
    {
        $this->options['distinct'] = $distinct;

        return $this;
    }

    protected function rnf(): RecordNotFound
    {
        return new RecordNotFound("Couldn't find " . $this->className . ' without an ID');
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
     * @return TModel|array<TModel>
     */
    public function find(): Model|array
    {
        if ($this->isNone) {
            throw $this->rnf();
        }

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
        $table = $this->table();
        if ($table->cache_individual_model) {
            $list = static::get_models_from_cache((array) $args);
        } else {
            $options = $this->options;
            $options['conditions'] ??= [];
            $options['conditions'][] = $this->pk_conditions($args);

            if (is_array($args) && 0 === count($args)) {
                throw $this->rnf();
            }

            $list = $this->_to_a($options);
        }

        if (is_array($args) && count($list) != count($args)) {
            throw new RecordNotFound('found ' . count($list) . ', but was looking for ' . count($args));
        }

        return $single ? ($list[0] ?? throw $this->rnf()) : $list;
    }

    /**
     * Check cache for each primary key before querying the database.
     *
     * @param list<int|string> $pks An array of primary keys
     *
     * @return array<TModel>
     */
    protected function get_models_from_cache(array $pks)
    {
        $models = [];
        $table = $this->table();

        foreach ($pks as $pk) {
            $options = $this->options;
            $options['conditions'] ??= [];
            $options['conditions'][] = $this->pk_conditions((array) $pk);
            $models[] = Cache::get($table->cache_key_for_model($pk), function () use ($table, $options) {
                $res = iterator_to_array($table->find($options));

                return $res[0] ?? null;
            }, $table->cache_model_expire);
        }

        return array_filter($models);
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
    public function take(int $limit = null): Model|array|null
    {
        $options = array_merge($this->options, ['limit' => $limit ?? 1]);
        $models = $this->_to_a($options);

        return isset($limit) ? $models : $models[0] ?? null;
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
     *
     * @return TModel|array<TModel>|null
     */
    public function first(int $limit = null): Model|array|null
    {
        $models = $this->firstOrLast($limit, true);

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
    public function last(int $limit = null): Model|array|null
    {
        $models = $this->firstOrLast($limit, false);

        return isset($limit) ? $models : $models[0] ?? null;
    }

    /**
     * Reverses the ordering clause if specified; otherwise, sorts in descending order by primary key
     * as returning in ascending order is the default
     *
     * @return array<TModel>
     */
    public function reverse_order(): array
    {
        $pk = $this->table()->pk;
        if (!empty($pk)) {
            if (array_key_exists('order', $this->options)) {
                $this->options['order'] = SQLBuilder::reverse_order((string) $this->options['order']);
            } else {
                $this->options['order'] = implode(' DESC, ', $this->table()->pk) . ' DESC';
            }
        }

        return $this->to_a();
    }

    /**
     * @return array<TModel>
     */
    private function firstOrLast(int $limit = null, bool $isAscending): array
    {
        $options = array_merge($this->options, ['limit' => $limit ?? 1]);

        $pk = array_map(function ($pk) {
            $col = $this->table()->get_column_by_inflected_name($pk);

            return $this->table()->conn->guard_name($col->name ?? '');
        }, $this->table()->pk);

        if (!empty($pk)) {
            if (array_key_exists('order', $options)) {
                if (!$isAscending) {
                    if (str_contains($options['order'], implode(' DESC, ', $pk) . ' DESC')) {
                        $options['order'] = SQLBuilder::reverse_order((string) $options['order']);
                    }
                }
            } elseif (!array_key_exists('having', $options)) {
                $command = $isAscending ? 'ASC' : 'DESC';
                $options['order'] = implode(" {$command}, ", $pk) . " {$command}";
            }
        }

        return $this->_to_a($options);
    }

    /**
     * Converts relation objects to array with the currently set of options.
     *
     * @return array<TModel> All the rows that matches query. If no rows match, returns []
     */
    public function to_a(): array
    {
        return $this->_to_a($this->options);
    }

    /**
     * Updates all records in the current relation with provided attributes.
     * This method constructs a single SQL UPDATE statement and sends it
     * straight to the database. It does not instantiate the involved models
     * and it does not trigger Active Record callbacks or validations.
     * However, values passed to #update_all will still go through Active
     * Record's normal type casting and serialization. Returns the number of
     * rows affected.
     *
     * Note: As Active Record callbacks are not triggered, this method will
     * not automatically update updated_at+/+updated_on columns.
     *
     * // Update all customers with the given attributes
     * Customer::update_all( ['wants_email'] => true)
     *
     * // Update all books with 'Rails' in their title
     * Book::where('title LIKE ?', '%Rails%')
     *   ->update_all(['author', 'David'])
     *
     * // Update all books that match conditions, but limit it to 5 ordered by date
     * Book::where('title LIKE ?', '%Rails%')
     *   ->order('created_at')
     *   ->limit(5)
     *   ->update_all(['author'=> 'David'])
     *
     * // Update all invoices and set the number column to its id value.
     * Invoice::update_all('number = id')
     *
     * @param string|Attributes $attributes a string or hash representing the SET part of
     *                                      an SQL statement
     *
     * @return int number of affected records
     */
    public function update_all(array|string $attributes): int
    {
        return $this->table()->update($attributes, $this->options);
    }

    /**
     * Deletes the records without instantiating the records
     * first, and without invoking callbacks.
     *
     * This is a single SQL DELETE statement that goes straight to the
     * database. Returns the number of rows affected.
     *
     * ```
     *   Post::where('person_id = ?', 5)
     *     ->where( ['category' => ['Something', 'Else']])
     *     ->delete_all();
     * ```
     *
     * If an invalid method is supplied, #delete_all raises an ActiveRecordError:
     *
     * ```
     *  Post::distinct()->delete_all()
     * ```
     * => ActiveRecord::ActiveRecordError: delete_all doesn't support distinct
     */
    public function delete_all(): int
    {
        if (isset($this->options['distinct'])) {
            throw new ActiveRecordException("delete_all doesn't support distinct");
        }

        $table = $this->table();
        $options = array_intersect_key($this->options, array_flip([
            'conditions',
            'limit',
            'order'
        ]));

        return $table->delete($options);
    }

    /**
     * @param RelationOptions $options
     *
     * @throws ActiveRecordException
     * @throws Exception\RelationshipException
     *
     * @return array<TModel> All the rows that matches query. If no rows match, returns []
     */
    protected function _to_a(array $options): array
    {
        if ($this->isNone) {
            return [];
        }

        $options['mapped_names'] = $this->alias_attribute;

        return iterator_to_array($this->table()->find($options));
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
     * People::count() // Return the number of records in table
     * People::where('name' => 'Bill').count() // Return the number of people whose name is Bill
     *
     * ```
     *
     * @see find
     *
     * @return int Number of records that matched the query
     */
    public function count(): int
    {
        if ($this->isNone) {
            return 0;
        }
        $table = $this->table();
        $options = array_merge($this->options, ['select' => ['COUNT(*)']]);
        $sql = $table->options_to_sql($options);
        $values = $sql->get_where_values();

        return $table->conn->query_and_fetch_one($sql->to_s(), $values);
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
     * Returns sql statement for the relation.
     *
     * User::where([            // SELECT "users".* FROM "users"
     *   'name' => 'Oscar'      // WHERE "users"."name" = 'Oscar'
     * ])->to_sql()
     *
     * @throws Exception\ActiveRecordException
     * @throws Exception\RelationshipException
     */
    public function to_sql(): string
    {
        $this->options['mapped_names'] = $this->alias_attribute;

        return $this->table()->options_to_sql($this->options);
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
