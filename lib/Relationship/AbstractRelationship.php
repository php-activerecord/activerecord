<?php

namespace ActiveRecord\Relationship;

use function ActiveRecord\all;
use function ActiveRecord\classify;

use ActiveRecord\Exception\RelationshipException;

use function ActiveRecord\has_absolute_namespace;

use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Reflections;
use ActiveRecord\Relation;
use ActiveRecord\Table;
use ActiveRecord\Utils;
use ActiveRecord\WhereClause;

/**
 * Abstract class that all relationships must extend from.
 *
 * @phpstan-import-type Attributes from Model
 *
 * @see http://www.phpactiverecord.org/guides/associations
 */
abstract class AbstractRelationship
{
    /**
     * Name to be used that will trigger call to the relationship.
     */
    public string $attribute_name;

    /**
     * Class name of the associated model.
     *
     * @var class-string
     */
    public string $class_name;

    /**
     * Name of the foreign key.
     *
     * @var string[]
     */
    public array $foreign_key = [];

    /**
     * Options of the relationship.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * List of valid options for relationships.
     *
     * @var list<string>
     */
    protected static $valid_association_options = [
        'association_foreign_key',
        'class_name',
        'foreign_key',
        'conditions',
        'select',
        'join_table',
        'readonly',
        'namespace'
    ];

    /**
     * Constructs a relationship.
     *
     * @param array<mixed> $options Options for the relationship (see {@link valid_association_options})
     */
    public function __construct(string $attribute_name, array $options = [])
    {
        $this->attribute_name = $attribute_name;
        $this->options = $this->merge_association_options($options);

        $this->options['conditions'] = [];
        if (isset($options['conditions'])) {
            $this->options['conditions'][] = WhereClause::from_arg($options['conditions']);
        }

        if (isset($this->options['class_name'])) {
            $this->set_class_name($this->inferred_class_name($this->options['class_name']));
        }

        $this->attribute_name = strtolower(Inflector::variablize($this->attribute_name));

        $this->foreign_key = (array) ($this->options['foreign_key'] ?? []);
    }

    protected function get_table(): Table
    {
        return Table::load($this->class_name);
    }

    /**
     * @param list<Model>      $models     of model objects
     * @param list<Attributes> $attributes of attributes from $models
     * @param list<mixed>      $includes   of eager load directives
     */
    abstract public function load_eagerly(array $models, array $attributes, array $includes, Table $table): void;

    /**
     * What is this relationship's cardinality?
     */
    public function is_poly(): bool
    {
        return false;
    }

    /**
     * Eagerly loads relationships for $models.
     *
     * This method takes an array of models, collects PK or FK (whichever is needed for relationship), then queries
     * the related table by PK/FK and attaches the array of returned relationships to the appropriately named relationship on
     * $models.
     *
     * @param list<Model>      $models            of model objects
     * @param list<Attributes> $attributes        of attributes from $models
     * @param array<mixed>     $includes          of eager load directives
     * @param list<string>     $query_keys        -> key(s) to be queried for on included/related table
     * @param list<string>     $model_values_keys -> key(s)/value(s) to be used in query from model which is including
     */
    protected function query_and_attach_related_models_eagerly(Table $table, array $models, array $attributes, array $includes = [], array $query_keys = [], array $model_values_keys = []): void
    {
        $values = [];
        $options = $this->options;
        $query_key = $query_keys[0];
        $model_values_key = $model_values_keys[0];

        foreach ($attributes as $value) {
            $values[] = $value[Inflector::variablize($model_values_key)];
        }

        $values = [$values];
        $options['conditions'] ??= [];
        $options['conditions'][] = WhereClause::from_underscored_string($table->conn, $query_key, $values);

        if (!empty($includes)) {
            $options['include'] = $includes;
        }

        if ($this instanceof HasMany && !empty($options['through'])) {
            // save old keys as we will be resetting them below for inner join convenience
            $pk = $this->primary_key;
            $fk = $this->foreign_key;

            $this->set_keys($this->get_table()->class->getName(), true);

            if (!isset($options['class_name'])) {
                $class = classify($options['through'], true);
                if (isset($this->options['namespace']) && !class_exists($class)) {
                    $class = $this->options['namespace'] . '\\' . $class;
                }

                assert(class_exists($class));
                $through_table =  Table::load($class);
            } else {
                $class = $options['class_name'];
                if (isset($this->options['namespace']) && !class_exists($class)) {
                    $class = $this->options['namespace'] . '\\' . $class;
                }
                $relation = Table::load($class)->get_relationship($options['through']);
                assert(!is_null($relation));
                $through_table = $relation->get_table();
            }
            $options['joins'] = $this->construct_inner_join_sql($through_table, true);

            $query_key = $this->primary_key[0];

            // reset keys
            $this->primary_key = $pk;
            $this->foreign_key = $fk;
        }

        $class = $this->class_name;

        $rel = new Relation($class, [], $options);

        $related_models = $rel->to_a();
        $used_models_map = [];
        $related_models_map = [];
        $model_values_key = Inflector::variablize($model_values_key);
        $query_key = Inflector::variablize($query_key);

        foreach ($related_models as $related) {
            $related_models_map[$related->$query_key][] = $related;
        }

        foreach ($models as $model) {
            $key_to_match = $model->$model_values_key;

            if (isset($related_models_map[$key_to_match])) {
                foreach ($related_models_map[$key_to_match] as $related) {
                    $hash = spl_object_hash($related);

                    if (isset($used_models_map[$hash])) {
                        $model->set_relationship_from_eager_load(clone $related, $this->attribute_name);
                    } else {
                        $model->set_relationship_from_eager_load($related, $this->attribute_name);
                    }

                    $used_models_map[$hash] = true;
                }
            } else {
                $model->set_relationship_from_eager_load(null, $this->attribute_name);
            }
        }
    }

    /**
     * Creates a new instance of specified {@link Model} with the attributes pre-loaded.
     *
     * @param Model      $model            The model which holds this association
     * @param Attributes $attributes       Hash containing attributes to initialize the model with
     * @param bool       $guard_attributes Set to true to guard protected/non-accessible attributes on the new instance
     */
    public function build_association(Model $model, array $attributes = [], bool $guard_attributes = true): Model
    {
        $class_name = $this->class_name;

        $model = new $class_name($attributes, $guard_attributes);
        assert($model instanceof Model);

        return $model;
    }

    /**
     * Creates a new instance of {@link Model} and invokes save.
     *
     * @param Model      $model            The model which holds this association
     * @param Attributes $attributes       Hash containing attributes to initialize the model with
     * @param bool       $guard_attributes Set to true to guard protected/non-accessible attributes on the new instance
     */
    public function create_association(Model $model, array $attributes = [], bool $guard_attributes = true): Model
    {
        $new_record = static::build_association($model, $attributes, true);
        $new_record->save();

        return $this->append_record_to_associate($model, $new_record);
    }

    protected function append_record_to_associate(Model $associate, Model $record): Model
    {
        $association = &$associate->{$this->attribute_name};

        if ($this->is_poly()) {
            $association[] = $record;
        } else {
            $association = $record;
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return array<string,mixed>
     */
    protected function merge_association_options(array $options): array
    {
        $available_options = array_merge(self::$valid_association_options, static::$valid_association_options);
        $valid_options = array_intersect_key(array_flip($available_options), $options);

        foreach ($valid_options as $option => $v) {
            $valid_options[$option] = $options[$option];
        }

        return $valid_options;
    }

    /**
     * @return class-string
     */
    protected function inferred_class_name(string $rawName): string
    {
        $name = $rawName;
        if (!class_exists($name) && !has_absolute_namespace($name) && isset($this->options['namespace'])) {
            if (!isset($this->options['class_name'])) {
                $name = classify($name, $this instanceof HasMany);
            }
            $name = $this->options['namespace'] . '\\' . $name;
        }

        if (!class_exists($name)) {
            throw new \ReflectionException('Unknown class name: ' . $name);
        }

        return $name;
    }

    /**
     * @param class-string $class_name
     *
     * @throws RelationshipException
     * @throws \ActiveRecord\Exception\ActiveRecordException
     */
    protected function set_class_name(string $class_name): void
    {
        $reflection = Reflections::instance()->add($class_name)->get($class_name);

        if (!$reflection->isSubClassOf('ActiveRecord\\Model')) {
            throw new RelationshipException("'$class_name' must extend from ActiveRecord\\Model");
        }
        $this->class_name = $class_name;
    }

    /**
     * @param list<string> $condition_keys
     * @param list<string> $value_keys
     *
     * @return list<mixed>
     */
    protected function create_conditions_from_keys(Model $model, array $condition_keys = [], array $value_keys = []): ?array
    {
        $condition_string = implode('_and_', $condition_keys);
        $condition_values = array_values($model->get_values_for($value_keys));

        // return null if all the foreign key values are null so that we don't try to do a query like "id is null"
        if (all(null, $condition_values)) {
            return null;
        }

        $conditions[] = WhereClause::from_underscored_string(
            Table::load(get_class($model))->conn,
            $condition_string,
            $condition_values
        );

        return $conditions; // Utils::add_condition($options_conditions, $conditions ?? []);
    }

    /**
     * Creates INNER JOIN SQL for associations.
     *
     * @param Table  $from_table    the table used for the FROM SQL statement
     * @param bool   $using_through is this a THROUGH relationship?
     * @param string $alias         a table alias for when a table is being joined twice
     *
     * @return string SQL INNER JOIN fragment
     */
    public function construct_inner_join_sql(Table $from_table, bool $using_through = false, string $alias = null)
    {
        if ($using_through) {
            $join_table_name = $from_table->get_fully_qualified_table_name();
            $from_table_name = Table::load($this->class_name)->get_fully_qualified_table_name();
        } else {
            $join_table = Table::load($this->class_name);
            $join_table_name = $join_table->get_fully_qualified_table_name();
            $from_table_name = $from_table->get_fully_qualified_table_name();
        }

        // need to flip the logic when the key is on the other table
        /* @phpstan-ignore-next-line */
        if (($this instanceof HasMany) || ($this instanceof HasOne)) {
            $this->set_keys($from_table->class->getName());

            if ($using_through) {
                $foreign_key = $this->primary_key[0];
                $join_primary_key = $this->foreign_key[0];
            } else {
                $join_primary_key = $this->foreign_key[0];
                $foreign_key = $this->primary_key[0];
            }
        } elseif ($this instanceof BelongsTo) {
            $foreign_key = $this->foreign_key[0];
            $join_primary_key = $this->primary_key()[0];
        }

        if (!is_null($alias)) {
            $aliased_join_table_name = $alias = $this->get_table()->conn->quote_name($alias);
            $alias .= ' ';
        } else {
            $aliased_join_table_name = $join_table_name;
        }

        assert(isset($foreign_key), 'foreign key must be set');
        assert(isset($join_primary_key), 'join primary key must be set');

        return "INNER JOIN $join_table_name {$alias}ON($from_table_name.$foreign_key = $aliased_join_table_name.$join_primary_key)";
    }

    /**
     * This will load the related model data.
     *
     * @param Model $model The model this relationship belongs to
     *
     * @return Model|array<Model>|null
     */
    abstract public function load(Model $model): mixed;
}
