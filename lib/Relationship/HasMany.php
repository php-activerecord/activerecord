<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Exception\HasManyThroughAssociationException;
use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Relation;
use ActiveRecord\Table;
use ActiveRecord\Types;

/**
 * One-to-many relationship.
 *
 * ```php
 * # Table: people
 * # Primary key: id
 * # Foreign key: school_id
 * class Person extends ActiveRecord\Model {}
 *
 * # Table: schools
 * # Primary key: id
 * class School extends ActiveRecord\Model {
 *   static array $has_many = [
 *     'people' => true
 *   ];
 * });
 * ```
 *
 * Example using options:
 *
 * ```php
 * class Payment extends ActiveRecord\Model {
 *   static array $belongs_to = [
 *     'person'=>true,
 *     'order'=>true
 *   ];
 * }
 *
 * class Order extends ActiveRecord\Model {
 *   static array $has_many = [
 *      'people' => [
 *          'through'    => 'payments',
 *          'select'     => 'people.*, payments.amount',
 *     ];
 * ]
 * ```
 *
 * @phpstan-import-type Attributes from Model
 * @phpstan-import-type HasManyOptions from Types
 *
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasMany extends AbstractRelationship
{
    protected bool $initialized;

    /**
     * Valid options to use for a {@link HasMany} relationship.
     *
     * @var list<string>
     */
    protected static $valid_association_options = [
        'primary_key',
        'order',
        'group',
        'having',
        'limit',
        'offset',
        'through',
        'source'
    ];

    /**
     * @var list<string>
     */
    protected array $primary_key;

    private string $through;

    public function is_poly(): bool
    {
        return true;
    }

    /**
     * Constructs a {@link HasMany} relationship.
     *
     * @param HasManyOptions $options
     */
    public function __construct(string $attribute, array $options = [])
    {
        parent::__construct($attribute, $options);

        if (isset($this->options['through'])) {
            $this->through = $this->options['through'];

            if (isset($this->options['source'])) {
                $this->set_class_name($this->inferred_class_name($this->options['source']));
            }
        }

        if (isset($this->options['primary_key'])) {
            $this->primary_key = is_array($this->options['primary_key']) ? $this->options['primary_key'] : [$this->options['primary_key']];
        }

        if (!isset($this->class_name)) {
            $this->set_class_name($this->inferred_class_name($this->attribute_name));
        }
    }

    /**
     * @param class-string $model_class_name
     */
    protected function set_keys(string $model_class_name, bool $override = false): void
    {
        // infer from class_name
        if (!$this->foreign_key || $override) {
            $this->foreign_key = [Inflector::keyify($model_class_name)];
        }

        if (!isset($this->primary_key) || $override) {
            $this->primary_key = Table::load($model_class_name)->pk;
        }
    }

    /**
     * @throws HasManyThroughAssociationException
     * @throws \ActiveRecord\Exception\RelationshipException
     *
     * @return Model|list<Model>|null
     */
    public function load(Model $model): mixed
    {
        $class_name = $this->class_name;
        $this->set_keys(get_class($model));

        // since through relationships depend on other relationships we can't do
        // this initialization in the constructor since the other relationship
        // may not have been created yet, and we only want this to run once
        if (!isset($this->initialized)) {
            if (isset($this->through)) {
                // verify through is a belongs_to or has_many for access of keys
                if (!($through_relationship = $this->get_table()->get_relationship($this->through))) {
                    throw new HasManyThroughAssociationException("Could not find the association $this->through in model " . get_class($model));
                }
                assert(($through_relationship instanceof self) || ($through_relationship instanceof BelongsTo),
                    'has_many through can only use a belongs_to or has_many association');
                // save old keys as we will be reseting them below for inner join convenience
                $pk = $this->primary_key;
                $fk = $this->foreign_key;

                $this->set_keys($this->get_table()->class->getName(), true);

                $class = $this->class_name;
                $relation = Table::load($class)->get_relationship($this->through);
                assert(!is_null($relation));
                $through_table = $relation->get_table();
                $this->options['joins'] = $this->construct_inner_join_sql($through_table, true);

                // reset keys
                $this->primary_key = $pk;
                $this->foreign_key = $fk;
            }

            $this->initialized = true;
        }

        if (!($conditions = $this->create_conditions_from_keys($model, $this->foreign_key, $this->primary_key))) {
            return null;
        }

        $options = $this->options;
        $options['conditions'] = array_merge($conditions, $options['conditions']);

        $rel = new Relation($class_name, [], $options);
        if ($this->is_poly()) {
            return $rel->to_a();
        }

        return $rel->first();
    }

    /**
     * Get an array containing the key and value of the foreign key for the association
     *
     * @return array<string, mixed>
     */
    private function get_foreign_key_for_new_association(Model $model): array
    {
        $this->set_keys(get_class($model));
        $primary_key = Inflector::variablize($this->foreign_key[0]);

        /*
         * TODO: set up model property reflection stanning
         *
         * @phpstan-ignore-next-line
         */
        return [$primary_key => $model->id];
    }

    /**
     * @param Attributes $attributes
     */
    public function build_association(Model $model, array $attributes = [], bool $guard_attributes = true): Model
    {
        $relationship_attributes = $this->get_foreign_key_for_new_association($model);

        // First build the record with just our relationship attributes (unguarded)
        $record = parent::build_association($model, $relationship_attributes, false);

        // Then, set our normal attributes (using guarding)
        $record->set_attributes($attributes);

        return $record;
    }

    /**
     * @param list<Model>      $models
     * @param list<Attributes> $attributes
     * @param array<mixed>     $includes
     */
    public function load_eagerly(array $models, array $attributes, array $includes, Table $table): void
    {
        $this->set_keys($table->class->name);
        $this->query_and_attach_related_models_eagerly($table, $models, $attributes, $includes, $this->foreign_key, $table->pk);
    }
}
