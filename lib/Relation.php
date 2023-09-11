<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\ValidationsArgumentError;

/**
 * @template TModel of Model
 *
 * @phpstan-import-type RelationOptions from Types
 */
class Relation
{
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
     * @return Relation<TModel>
     */
    public function select(string $columns): Relation
    {
        $this->options['select'] = $columns;

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
     * @param string|array<string|mixed> $include
     *
     * @return Relation<TModel>
     */
    public function include(string|array $include): Relation
    {
        $this->options['include'] = $include;

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

        $args = func_get_args();
        $numArgs = count($args);

        if (1 != $numArgs) {
            throw new \ArgumentCountError('`where` requires exactly one argument.');
        }
        $arg = $args[0];

        $expression = WhereClause::from_arg($arg);

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
        $class = $this->className;

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

        $list = $this->table()->find($options);
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

        if (!isset($limit)) {
            $models = $this->to_a();

            return $models[0] ?? null;
        }

        return $this->to_a();
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

        return $this->table()->find($this->options);
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

        $this->options['select'] = '1';
        $res = $this->count();

        return $res > 0;
    }

    /**
     * Will look up a list of primary keys from cache
     *
     * @param array<mixed> $pks An array of primary keys
     *
     * @return array<TModel>
     */
    public function get_models_from_cache(array $pks): array
    {
        $models = [];
        $table = $this->table();

        foreach ($pks as $pk) {
            $options = ['conditions' => [$this->pk_conditions($pk)]];
            $models[] = Cache::get($table->cache_key_for_model($pk), function () use ($table, $options) {
                $res = $table->find($options);

                return $res ? $res[0] : null;
            }, $table->cache_model_expire);
        }

        return array_filter($models);
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
