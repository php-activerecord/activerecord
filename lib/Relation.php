<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

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
    public function __construct(string $className, array $alias_attribute)
    {
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

    public function last(int $limit): Relation
    {
        $this->limit($limit);

        if (array_key_exists('order', $this->options)) {
            if (str_contains($this->options['order'], join(' ASC, ', $this->table()->pk) . ' ASC')) {
                $this->options['order'] = SQLBuilder::reverse_order((string) $this->options['order']);
            }
        } else {
            $this->options['order'] = join(' DESC, ', $this->table()->pk) . ' DESC';
        }

        return $this;
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
     * @param string|array<string|mixed> $where
     */
    public function where(string|array $where): Relation
    {
        $this->options['conditions'] ??= [];
        $this->options['conditions'][] = $where;

        return $this;
    }

    public function readonly(bool $readonly): Relation
    {
        $this->options['readonly'] = $readonly;

        return $this;
    }

    /**
     * Implementation of Ruby On Rails finder
     *
     * @see https://api.rubyonrails.org/v7.0.7.2/classes/ActiveRecord/FinderMethods.html#method-i-find
     *
     * Person.find(1)          # returns the object for ID = 1
     * Person.find("1")        # returns the object for ID = 1
     * Person.find(999999)     # throws RecordNotFound
     * Person.find("can't be casted to int") # throws TypeError
     *
     * Person.find([1, 2])     # returns an array for objects with IDs in (7, 17)
     * Person.find([1, -999])  # throws RecordNotFound
     * Person.find([1])        # returns an array for the object with ID = 1
     * Person.find([-11])      # returns an empty array
     *
     * Person.where('name = Bob').find() # executes the where statement
     * Person.find()           # throws ValidationsArgumentError as there's no where statement to execute
     *
     * @param int|string|array<int|string> $id
     *
     * @throws RecordNotFound if any of the records cannot be found
     *
     * @return Model|array<Model>|null See above
     */
    public function find(int|string|array $id = null): Model|null|array
    {
        if (array_key_exists('where', $this->options)) {
            $values = $this->buildWhereSQL($this->options['where']);

            if (null !== $id) {
                $values[0] = "({$values[0]}) AND ";
                if (is_array($id)) {
                    $values[0] .= $this->table()->pk[0] . ' in (' . implode(',', $id) . ')';
                } else {
                    $values[0] .= $this->table()->pk[0] . ' = ' . $id;
                }
            }
            $this->options['conditions'] = $values;

            $this->options['mapped_names'] = $this->alias_attribute;
            $list = $this->table()->find($this->options);

            unset($this->options['conditions']);
        } else {
            if (null === $id) {
                throw new ValidationsArgumentError('Cannot call find() without where() being first specified');
            }

            unset($this->options['mapped_names']);
            $list = $this->find_by_pk($id, true);
            unset($this->options['conditions']);
        }

        if (null === $id || is_array($id)) {
            return $list;
        }
        if (0 === count($list)) {
            return null;
        }

        return $list[0];
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
     * needle is one of:
     *
     * primary key value        where(3)     WHERE author_id=3
     * mapping of column names  where(["name"=>"Philip", "publisher"=>"Random House"]) finds the first row of WHERE name=Philip AND publisher=Random House
     * raw WHERE statement      where(['name = (?) and publisher <> (?)', 'Bill Clinton', 'Random House'])
     *
     * @param int|string|array<string> $needle
     * @param bool                     $isUsingOriginalFind true if called from version 1 find, false otherwise
     *
     * @return Model|null The single row that matches query. If no rows match, returns null
     */
    public function whereToBeReplacedByFind(int|string|array $needle, bool $isUsingOriginalFind = false): Model|null
    {
        $this->limit(1);

        if (is_array($needle)) {
            if (!array_key_exists('conditions', $this->options) && count($needle) > 0) {
                $this->options['conditions'] = $needle;
            }
            $this->options['mapped_names'] = $this->alias_attribute;
            $list = $this->table()->find($this->options);
        } else {
            unset($this->options['mapped_names']);
            $list = $this->find_by_pk($needle, $isUsingOriginalFind);
        }

        if (null == $list) {
            return null;
        }

        return $list[0];
    }

    /**
     * @return array<Model> All the rows that matches query. If no rows match, returns []
     */
    public function to_a(): array
    {
        $this->options['mapped_names'] = $this->alias_attribute;
        return $this->table()->find($this->options);
    }

    public function all(): Relation {
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
     * @param mixed $where The qualifications for a row to be counted
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

        $values = [];

        $res = $this->table()->conn->query_and_fetch_one($sql->to_s(), $values);

        return $res;
    }

    /**
     * Finder method which will find by a single or array of primary keys for this model.
     *
     * @see find
     *
     * @param mixed $values               An array containing values for the pk
     * @param bool  $throwErrorIfNotFound True if version 1 behavior, false is version 2
     *
     * @throws RecordNotFound if a record could not be found
     *
     * @return array<Model>
     */
    private function find_by_pk(mixed $values, bool $throwErrorIfNotFound): array
    {
        if ($this->table()->cache_individual_model ?? false) {
            $pks = is_array($values) ? $values : [$values];
            $list = $this->get_models_from_cache($pks);
        } else {
            $this->options['conditions'] = $this->pk_conditions($values);
            $list = $this->table()->find($this->options);
        }
        $results = count($list);

        if ($results != ($expected = @count((array) $values))) {
            $class = get_called_class();
            if (is_array($values)) {
                $values = implode(',', $values);
            }

            if (1 == $expected && !$throwErrorIfNotFound) {
                return [];
            }

            throw new RecordNotFound("Couldn't find all $class with IDs ($values) (found $results, but was looking for $expected)");
        }

        return $list;
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
    private function pk_conditions(int|array $args): array
    {
        $ret = [$this->table()->pk[0] => $args];

        return $ret;
    }
}
