<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\RecordNotFound;

class SQLExecutionPlan
{
    /**
     * @var array<string>
     */
    private array $alias_attribute;
    private Table $table;

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
        $this->table = Table::load($className);
    }

    public function select(string $columns): SQLExecutionPlan
    {
        $this->options['select'] = $columns;

        return $this;
    }

    public function join(string $joinStatement): SQLExecutionPlan
    {
        $this->options['joins'] = $joinStatement;

        return $this;
    }

    public function orderBy(string $order): SQLExecutionPlan
    {
        $this->options['order'] = $order;

        return $this;
    }

    public function limit(int $limit): SQLExecutionPlan
    {
        $this->options['limit'] = $limit;

        return $this;
    }

    public function groupBy(string $columns): SQLExecutionPlan
    {
        $this->options['group'] = $columns;

        return $this;
    }

    public function offset(int $offset): SQLExecutionPlan
    {
        $this->options['offset'] = $offset;

        return $this;
    }

    public function having(string $having): SQLExecutionPlan
    {
        $this->options['having'] = $having;

        return $this;
    }

    public function readonly(bool $readonly): SQLExecutionPlan
    {
        $this->options['readonly'] = $readonly;

        return $this;
    }

    /**
     * needle is one of:
     *
     * primary key value        where(3)     WHERE author_id=3
     * mapping of column names  where(["name"=>"Philip", "publisher"=>"Random House"]) finds the first row of WHERE name=Philip AND publisher=Random House
     * raw WHERE statement      where(['name = (?) and publisher <> (?)', 'Bill Clinton', 'Random House'])
     *
     * @param int|string|array<string> $needle
     * @param                          $returnFirstRow true to return the first row retrieved. false to return last row
     *
     * @return Model|null The single row that matches query. If no rows match, returns null
     */
    public function where(int|string|array $needle, bool $returnFirstRow=true): Model|null
    {
        $this->limit(1);

        if (!$returnFirstRow) {
            if (array_key_exists('order', $this->options)) {
                $this->options['order'] = SQLBuilder::reverse_order((string) $this->options['order']);
            } else {
                $this->options['order'] = join(' DESC, ', $this->table->pk) . ' DESC';
            }
        }

        if (is_array($needle)) {
            $this->options['conditions'] = $needle;
            $this->options['mapped_names'] = $this->alias_attribute;
            $list = $this->table->find($this->options);
        } else {
            unset($this->options['mapped_names']);
            $list = $this->find_by_pk($needle);
        }

        if (null == $list) {
            return null;
        }

        return $list[0];
    }

    /**
     * needle is one of:
     *
     * empty array              all()       returns all rows in the database
     * array of primary keys    all([1, 3]) WHERE author_id in (1, 3)
     * mapping of column names  all(["name"=>"Philip", "publisher"=>"Random House"]) WHERE name=Philip AND publisher=Random House
     * raw WHERE statement      all(['name = (?) and publisher <> (?)', 'Bill Clinton', 'Random House'])
     *
     * @param array<number|mixed|string> $needle An array containing values for the pk
     *
     * @return array<Model> All the rows that matches query. If no rows match, returns []
     */
    public function all(array $needle = []): array
    {
        $list = [];

        if (array_is_list($needle) && count($needle) > 0) {
            unset($this->options['mapped_names']);
            try {
                return $this->find_by_pk($needle);
            } catch (RecordNotFound $mustBeRawWhereStatement) {
            }
        }

        // Only for backwards compatibility with version 1 API
        $isVersion1 = false;
        foreach (['select', 'conditions', 'joins', 'order', 'limit', 'group', 'offset', 'having', 'readonly'] as $key) {
            if (array_key_exists($key, $needle)) {
                $this->options[$key] = $needle[$key];
                $isVersion1 = true;
            }
        }

        if (!$isVersion1) {
            $this->options['conditions'] = $needle;
        }
        $this->options['mapped_names'] = $this->alias_attribute;

        return $this->table->find($this->options);
    }

    /**
     * Finder method which will find by a single or array of primary keys for this model.
     *
     * @see find
     *
     * @param mixed $values An array containing values for the pk
     *
     * @throws RecordNotFound if a record could not be found
     *
     * @return array<Model>
     */
    private function find_by_pk(mixed $values): array
    {
        if ($this->table->cache_individual_model ?? false) {
            $pks = is_array($values) ? $values : [$values];
            $list = $this->get_models_from_cache($pks);
        } else {
            $this->options['conditions'] = $this->pk_conditions($values);
            $list = $this->table->find($this->options);
        }
        $results = count($list);

        if ($results != ($expected = @count((array) $values))) {
            $class = get_called_class();
            if (is_array($values)) {
                $values = join(',', $values);
            }

            if (1 == $expected) {
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
    private function get_models_from_cache(array $pks): array
    {
        $models = [];
        $table = $this->table;

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
        $ret = [$this->table->pk[0] => $args];

        return $ret;
    }
}
