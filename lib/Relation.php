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

class Relation
{
    /**
     * A list of valid finder options.
     *
     * @var array<string>
     */
    public static array $VALID_OPTIONS = [
        'conditions',
        'limit',
        'offset',
        'order',
        'select',
        'joins',
        'include',
        'readonly',
        'group',
        'from',
        'having'
    ];

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
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * @param class-string  $className
     * @param array<string> $alias_attribute
     */
    public function __construct(string $className, array $alias_attribute, array $options = [])
    {
        $this->options = $options;
        $this->alias_attribute = $alias_attribute;
        $this->className = $className;
    }

    private function table(): Table
    {
        if (null === $this->tableImpl) {
            $this->tableImpl = Table::load($this->className);
        }

        return $this->tableImpl;
    }

    public function select(string $columns): Relation
    {
        $this->options['select'] = $columns;

        return $this;
    }

    /**
     * @param string|array<string> $joins
     */
    public function joins(string|array $joins): Relation
    {
        $this->options['joins'] = $joins;

        return $this;
    }

    public function order(string $order): Relation
    {
        $this->options['order'] = $order;

        return $this;
    }

    public function limit(int $limit): Relation
    {
        $this->options['limit'] = $limit;

        return $this;
    }

    public function group(string $columns): Relation
    {
        $this->options['group'] = $columns;

        return $this;
    }

    public function offset(int $offset): Relation
    {
        $this->options['offset'] = $offset;

        return $this;
    }

    public function having(string $having): Relation
    {
        $this->options['having'] = $having;

        return $this;
    }

    public function from(string $from): Relation
    {
        $this->options['from'] = $from;

        return $this;
    }

    /**
     * @param string|array<string|mixed> $include
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

    public function readonly(bool $readonly): Relation
    {
        $this->options['readonly'] = $readonly;

        return $this;
    }

    /**
     * Pulls out the options hash from $array if any.
     *
     * @param array<mixed> &$options An array
     *
     * @return array<string,mixed> A valid options array
     */
    public static function extract_and_validate_options(array $options): array
    {
        $res = [];
        if ($options) {
            $last = $options[count($options) - 1];

            try {
                if (self::is_options_hash($last)) {
                    array_pop($options);
                    $res = $last;
                }
            } catch (ActiveRecordException $e) {
                if (!is_hash($last)) {
                    throw $e;
                }
                $res = ['conditions' => $last];
            }
        }

        return $res;
    }

    /**
     * Determines if the specified array is a valid ActiveRecord options array.
     *
     * @param mixed $options An options array
     * @param bool  $throw   True to throw an exception if not valid
     *
     * @throws ActiveRecordException if the array contained any invalid options
     */
    public static function is_options_hash(mixed $options, bool $throw = true): bool
    {
        if (is_hash($options)) {
            $keys = array_keys($options);
            $diff = array_diff($keys, Relation::$VALID_OPTIONS);

            if (!empty($diff) && $throw) {
                throw new ActiveRecordException('Unknown key(s): ' . join(', ', $diff));
            }
            $intersect = array_intersect($keys, Relation::$VALID_OPTIONS);

            if (!empty($intersect)) {
                return true;
            }
        }

        return false;
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
     * @return Model|array<Model> See above
     */
    public function find(): Model|array
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
     * @return Model|array<Model>|null
     */
    public function first(int $limit = null): mixed
    {
        $this->limit($limit ?? 1);
        if (!isset($limit)) {
            $models = $this->to_a();

            return $models[0] ?? null;
        }

        return $this->to_a();
    }

    /**
     * @return Model|array<Model>|null
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
     * @param array<array<string>|string> $fragments
     *
     * @return array<string>
     */
    private function buildWhereSQL(array $fragments): array
    {
        $templates = [];
        $values = [];

        foreach ($fragments as $fragment) {
            if (is_array($fragment)) {
                if (is_hash($fragment)) {
                    foreach ($fragment as $key => $value) {
                        array_push($templates, "{$key} = (?)");
                        array_push($values, $value);
                    }
                } else {
                    array_push($templates, array_shift($fragment));
                    if (count($fragment) > 0) {
                        $values = array_merge($values, $fragment);
                    }
                }
            } else {
                array_push($templates, $fragment);
            }
        }

        array_unshift($values, implode(' AND ', $templates));

        return $values;
    }

    /**
     * @return array<Model> All the rows that matches query. If no rows match, returns []
     */
    public function to_a(): array
    {
        $this->options['mapped_names'] = $this->alias_attribute;

        return $this->table()->find($this->options);
    }

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
        $args =  func_get_args();
        // arg handling tbd

        //        $this->options['conditions'] = $where;
        //
        $this->select('COUNT(*)');
        $sql = $this->table()->options_to_sql($this->options);
        //        $values = $sql->get_where_values();

        $table = $this->table();
        $sql = $table->options_to_sql($this->options);
        $values = $sql->get_where_values();

        $res = $this->table()->conn->query_and_fetch_one($sql->to_s(), $values);

        return $res;
    }

    public function exists(mixed $conditions = []): int
    {
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

        $this->options['select'] = 1;
        $res = $this->count();

        return $res > 0;
    }

    /**
     * Will look up a list of primary keys from cache
     *
     * @param array<mixed> $pks An array of primary keys
     *
     * @return array<Model>
     */
    public function get_models_from_cache(array $pks): array
    {
        $models = [];
        $table = $this->table();

        foreach ($pks as $pk) {
            $options = ['conditions' => $this->pk_conditions($pk)];
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
     *
     * @return array<string, mixed>
     */
    private function pk_conditions(int|array $args): WhereClause
    {
        return new WhereClause([$this->table()->pk[0] => $args], []);
    }
}
