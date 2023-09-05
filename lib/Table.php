<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Adapter\PgsqlAdapter;
use ActiveRecord\Exception\RelationshipException;
use ActiveRecord\Relationship\AbstractRelationship;
use ActiveRecord\Relationship\BelongsTo;
use ActiveRecord\Relationship\HasAndBelongsToMany;
use ActiveRecord\Relationship\HasMany;
use ActiveRecord\Relationship\HasOne;

/**
 * Manages reading and writing to a database table.
 *
 * This class manages a database table and is used by the Model class for
 * reading and writing to its database table. There is one instance of Table
 * for every table you have a model for.
 *
 * @phpstan-import-type PrimaryKey from Types
 * @phpstan-import-type Attributes from Types
 */
class Table
{
    /**
     * @var array<string, Table>
     */
    private static array $cache = [];

    /**
     * @var \ReflectionClass<Model>
     */
    public \ReflectionClass $class;
    public Connection $conn;

    /**
     * @var array<string>
     */
    public array $pk = [];
    public string $last_sql = '';

    /**
     * @var array<string, Column>
     */
    public array $columns = [];

    /**
     * Name of the table.
     */
    public string $table;

    /**
     * Name of the database (optional)
     */
    public string $db_name;

    /**
     * Name of the sequence for this table (optional). Defaults to {$table}_seq
     */
    public ?string $sequence = null;

    /**
     * Whether to cache individual models or not (not to be confused with caching of table schemas).
     */
    public bool $cache_individual_model = false;

    /**
     * Expiration period for model caching.
     */
    public int $cache_model_expire;

    /**
     * A instance of CallBack for this model/table
     */
    public CallBack $callback;

    /**
     * @var array<string, AbstractRelationship>
     */
    private array $relationships = [];

    /**
     * @param class-string $model_class_name
     */
    public static function load(string $model_class_name): Table
    {
        if (!isset(self::$cache[$model_class_name])) {
            /* do not place set_assoc in constructor..it will lead to infinite loop due to
               relationships requesting the model's table, but the cache hasn't been set yet */
            self::$cache[$model_class_name] = new self($model_class_name);
            self::$cache[$model_class_name]->set_associations();
        }

        return self::$cache[$model_class_name];
    }

    public static function clear_cache(string $model_class_name = null): void
    {
        if ($model_class_name && array_key_exists($model_class_name, self::$cache)) {
            unset(self::$cache[$model_class_name]);
        } else {
            self::$cache = [];
        }
    }

    /**
     * @param class-string $class_name
     *
     * @throws Exception\ActiveRecordException
     */
    public function __construct(string $class_name)
    {
        $this->class = Reflections::instance()->add($class_name)->get($class_name);

        $this->reestablish_connection(false);
        $this->set_table_name();
        $this->get_meta_data();
        $this->set_primary_key();
        $this->set_sequence_name();
        $this->set_cache();

        $this->callback = new CallBack($class_name);
        $this->callback->register('before_save', function (Model $model) { $model->set_timestamps(); }, ['prepend' => true]);
        $this->callback->register('after_save', function (Model $model) { $model->reset_dirty(); }, ['prepend' => true]);
    }

    public function reestablish_connection(bool $close = true): Connection
    {
        // if connection name property is null the connection manager will use the default connection
        $connection = $this->class->getStaticPropertyValue('connection', null);

        if ($close) {
            ConnectionManager::drop_connection($connection);
            static::clear_cache();
        }

        return $this->conn = ConnectionManager::get_connection($connection ?? null);
    }

    /**
     * @param array<string>|string $joins
     *
     * @throws RelationshipException
     */
    public function create_joins(array|string $joins): string
    {
        if (!is_array($joins)) {
            return $joins;
        }

        $ret = $space = '';

        $existing_tables = [];
        foreach ($joins as $value) {
            $ret .= $space;

            if (false === stripos($value, 'JOIN ')) {
                if (array_key_exists($value, $this->relationships)) {
                    $rel = $this->get_relationship($value);

                    /**
                     * PHPStan seems to be getting confused about the usage of a class-string
                     * as an array string.
                     *
                     * @phpstan-ignore-next-line
                     */
                    $alias = !empty($existing_tables[$rel->class_name]) ? $value : null;
                    /* @phpstan-ignore-next-line */
                    $existing_tables[$rel->class_name] = true;

                    /* @phpstan-ignore-next-line */
                    $ret .= $rel->construct_inner_join_sql($this, false, $alias);
                } else {
                    throw new RelationshipException("Relationship named $value has not been declared for class: {$this->class->getName()}");
                }
            } else {
                $ret .= $value;
            }

            $space = ' ';
        }

        return $ret;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws Exception\ActiveRecordException
     * @throws RelationshipException
     */
    public function options_to_sql(array $options): SQLBuilder
    {
        $table = array_key_exists('from', $options) ? $options['from'] : $this->get_fully_qualified_table_name();
        $sql = new SQLBuilder($this->conn, $table);

        if (array_key_exists('joins', $options)) {
            $joins = $this->create_joins($options['joins']);
            $sql->joins($joins);

            // by default, an inner join will not fetch the fields from the joined table
            if (!array_key_exists('select', $options)) {
                $options['select'] = $this->get_fully_qualified_table_name() . '.*';
            }
        }

        if (array_key_exists('select', $options)) {
            $sql->select($options['select']);
        }

        if (array_key_exists('conditions', $options)) {
            if (!is_hash($options['conditions'])) {
                if (is_string($options['conditions'])) {
                    $options['conditions'] = [$options['conditions']];
                }

                call_user_func_array([$sql, 'where'], $options['conditions']);
            } else {
                if (!empty($options['mapped_names'])) {
                    $options['conditions'] = $this->map_names($options['conditions'], $options['mapped_names']);
                }

                $sql->where($options['conditions']);
            }
        }

        if (array_key_exists('order', $options)) {
            $sql->order($options['order']);
        }

        if (array_key_exists('limit', $options)) {
            $sql->limit($options['limit']);
        }

        if (array_key_exists('offset', $options)) {
            $sql->offset($options['offset']);
        }

        if (array_key_exists('group', $options)) {
            $sql->group($options['group']);
        }

        if (array_key_exists('having', $options)) {
            $sql->having($options['having']);
        }

        return $sql;
    }

    /**
     * @param array<string, array<string,mixed>> $options
     *
     * @throws Exception\ActiveRecordException
     * @throws RelationshipException
     *
     * @return array<Model>
     */
    public function find(array $options): array
    {
        $sql = $this->options_to_sql($options);
        $readonly = (array_key_exists('readonly', $options) && $options['readonly']) ? true : false;

        return $this->find_by_sql($sql->to_s(), $sql->get_where_values(), $readonly, (array) ($options['include'] ?? []));
    }

    /**
     * @param PrimaryKey $pk
     */
    public function cache_key_for_model(mixed $pk): string
    {
        if (is_array($pk)) {
            $pk = implode('-', $pk);
        }

        return $this->class->name . '-' . $pk;
    }

    /**
     * @param array<string,mixed>|null $values
     * @param array<mixed>             $includes
     *
     * @throws RelationshipException
     *
     * @return array<Model>
     */
    public function find_by_sql(string $sql, array $values = null, bool $readonly = false, array $includes = []): array
    {
        $this->last_sql = $sql;

        $collect_attrs_for_includes = !empty($includes);
        $list = $attrs = [];
        $processedData = $this->process_data($values ?? []);
        $sth = $this->conn->query($sql, $processedData);

        $self = $this;
        while ($row = $sth->fetch()) {
            $cb = function () use ($row, $self) {
                return new $self->class->name($row, false, true, false);
            };
            if ($this->cache_individual_model ?? false) {
                $key = $this->cache_key_for_model(array_intersect_key($row, array_flip($this->pk)));
                $model = Cache::get($key, $cb, $this->cache_model_expire);
            } else {
                $model = $cb();
            }

            if ($readonly) {
                $model->readonly();
            }

            if ($collect_attrs_for_includes) {
                $attrs[] = $model->attributes();
            }

            $list[] = $model;
        }

        if ($collect_attrs_for_includes && !empty($list)) {
            $this->execute_eager_load($list, $attrs, $includes);
        }

        return $list;
    }

    /**
     * Executes an eager load of a given named relationship for this table.
     *
     * @param array<Model>                       $models
     * @param array<array<string,mixed>>         $attrs
     * @param array<string|array<string>>|string $includes
     *
     * @throws RelationshipException
     */
    private function execute_eager_load(array $models, array $attrs, array|string $includes): void
    {
        if (!is_array($includes)) {
            $includes = [$includes];
        }

        foreach ($includes as $index => $name) {
            $nested_includes = [];
            // nested include
            if (is_array($name)) {
                $nested_includes = $name;
                $name = $index;
            }

            $rel = $this->get_relationship($name, true);
            $rel?->load_eagerly($models, $attrs, $nested_includes, $this);
        }
    }

    public function get_column_by_inflected_name(string $inflected_name): Column|null
    {
        foreach ($this->columns as $raw_name => $column) {
            if ($column->inflected_name == $inflected_name) {
                return $column;
            }
        }

        return null;
    }

    public function get_fully_qualified_table_name(bool $quote_name = true): string
    {
        $table = $quote_name ? $this->conn->quote_name($this->table) : $this->table;

        if (isset($this->db_name)) {
            $table = $this->conn->quote_name($this->db_name) . ".$table";
        }

        return $table;
    }

    /**
     * Retrieve a relationship object for this table. Strict as true will throw an error
     * if the relationship name does not exist.
     *
     * @throws RelationshipException
     */
    public function get_relationship(string $name, bool $strict = false): ?AbstractRelationship
    {
        if ($this->has_relationship($name)) {
            return $this->relationships[$name];
        }

        if ($strict) {
            throw new RelationshipException("Relationship named $name has not been declared for class: {$this->class->getName()}");
        }

        return null;
    }

    /**
     * Does a given relationship exist?
     *
     * @param string $name name of Relationship
     */
    public function has_relationship(string $name): bool
    {
        return array_key_exists($name, $this->relationships);
    }

    /**
     * @param array<string,mixed> $data
     * @param (string|int)|null   $pk
     *
     * @throws Exception\ActiveRecordException
     */
    public function insert(array &$data, string|int $pk = null, string $sequence_name = null): \PDOStatement
    {
        $data = $this->process_data($data);

        $sql = new SQLBuilder($this->conn, $this->get_fully_qualified_table_name());
        $sql->insert($data, $pk, $sequence_name);

        $values = array_values($data);

        return $this->conn->query($this->last_sql = $sql->to_s(), $values);
    }

    /**
     * @param Attributes $data
     * @param Attributes $where
     *
     * @throws Exception\ActiveRecordException
     */
    public function update(array &$data, array $where): \PDOStatement
    {
        $data = $this->process_data($data);

        $sql = new SQLBuilder($this->conn, $this->get_fully_qualified_table_name());
        $sql->update($data)->where($where);

        $values = $sql->bind_values();

        return $this->conn->query($this->last_sql = $sql->to_s(), $values);
    }

    /**
     * @param array<string,mixed> $data
     *
     * @throws Exception\ActiveRecordException
     */
    public function delete(array $data): \PDOStatement
    {
        $data = $this->process_data($data);

        $sql = new SQLBuilder($this->conn, $this->get_fully_qualified_table_name());
        $sql->delete($data);

        $values = $sql->bind_values();

        return $this->conn->query($this->last_sql = $sql->to_s(), $values);
    }

    private function add_relationship(AbstractRelationship $relationship): void
    {
        $this->relationships[$relationship->attribute_name] = $relationship;
    }

    private function get_meta_data(): void
    {
        // as more adapters are added probably want to do this a better way
        // than using instanceof but gud enuff for now
        $quote_name = !($this->conn instanceof PgsqlAdapter);

        $table_name = $this->get_fully_qualified_table_name($quote_name);
        $conn = $this->conn;
        $this->columns = Cache::get("get_meta_data-$table_name", function () use ($conn, $table_name) { return $conn->columns($table_name); });
    }

    /**
     * Replaces any aliases used in a hash based condition.
     *
     * @param array<string, string> $hash A hash
     * @param array<string, string> $map  Hash of used_name => real_name
     *
     * @return array<string, string> Array with any aliases replaced with their read field name
     */
    private function map_names(array &$hash, array &$map): array
    {
        $ret = [];

        foreach ($hash as $name => &$value) {
            if (array_key_exists($name, $map)) {
                $name = $map[$name];
            }

            $ret[$name] = $value;
        }

        return $ret;
    }

    /**
     * @param array<string,mixed> $hash
     *
     * @return array<string,mixed> $hash
     */
    private function process_data(array $hash = []): array
    {
        foreach ($hash as $name => $value) {
            if ($value instanceof \DateTime) {
                $column = $this->columns[$name] ?? null;
                if (isset($column) && Column::DATE == $column->type) {
                    $hash[$name] = $this->conn->date_string($value);
                } else {
                    $hash[$name] = $this->conn->datetime_string($value);
                }
            } else {
                $hash[$name] = $value;
            }
        }

        return $hash;
    }

    private function set_primary_key(): void
    {
        if (($pk = $this->class->getStaticPropertyValue('pk', null)) || ($pk = $this->class->getStaticPropertyValue('primary_key', null))) {
            $this->pk = is_array($pk) ? $pk : [$pk];
        } else {
            $this->pk = [];

            foreach ($this->columns as $c) {
                if ($c->pk) {
                    $this->pk[] = $c->inflected_name;
                }
            }
        }
    }

    private function set_table_name(): void
    {
        if (($table = $this->class->getStaticPropertyValue('table', null)) || ($table = $this->class->getStaticPropertyValue('table_name', null))) {
            $this->table = $table;
        } else {
            // infer table name from the class name
            $this->table = Inflector::tableize($this->class->getName());

            // strip namespaces from the table name if any
            $parts = explode('\\', $this->table);
            $this->table = $parts[count($parts) - 1];
        }

        if (($db = $this->class->getStaticPropertyValue('db', null)) || ($db = $this->class->getStaticPropertyValue('db_name', null))) {
            $this->db_name = $db;
        }
    }

    private function set_cache(): void
    {
        if (!Cache::$adapter) {
            return;
        }

        $model_class_name = $this->class->name;
        $this->cache_individual_model = $model_class_name::$cache;

        $this->cache_model_expire = $model_class_name::$cache_expire ?? Cache::$options['expire'] ?? 0;
    }

    private function set_sequence_name(): void
    {
        if (!$this->conn->supports_sequences()) {
            return;
        }

        if (!($this->sequence = $this->class->getStaticPropertyValue('sequence'))) {
            $this->sequence = $this->conn->get_sequence_name($this->table, $this->pk[0]);
        }
    }

    private function set_associations(): void
    {
        $namespace = $this->class->getNamespaceName();

        foreach ($this->class->getStaticProperties() as $name => $definitions) {
            if (!$definitions) {// || !is_array($definitions))
                continue;
            }

            foreach (wrap_values_in_arrays($definitions) as $attribute => $definition) {
                $relationship = null;
                $definition += ['namespace' => $namespace];

                switch ($name) {
                    case 'has_many':
                        $relationship = new HasMany($attribute, $definition);
                        break;

                    case 'has_one':
                        $relationship = new HasOne($attribute, $definition);
                        break;

                    case 'belongs_to':
                        $relationship = new BelongsTo($attribute, $definition);
                        break;

                    case 'has_and_belongs_to_many':
                        $relationship = new HasAndBelongsToMany($definition);
                        break;
                }

                if ($relationship) {
                    $this->add_relationship($relationship);
                }
            }
        }
    }
}
