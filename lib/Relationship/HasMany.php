<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Exception\HasManyThroughAssociationException;
use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Table;

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
 *   static $has_many = array(
 *     array('people')
 *   );
 * });
 * ```
 *
 * Example using options:
 *
 * ```php
 * class Payment extends ActiveRecord\Model {
 *   static array $belongs_to = array(
 *     array('person'),
 *     array('order')
 *   );
 * }
 *
 * class Order extends ActiveRecord\Model {
 *   static $has_many = array(
 *     [
 *          'people',
 *          'through'    => 'payments',
 *          'select'     => 'people.*, payments.amount',
 *          'conditions' => 'payments.amount < 200')
 *     ];
 * }
 * ```
 *
 * @phpstan-import-type Attributes from Model
 *
 * @phpstan-type HasManyOptions array{
 *  limit?: int,
 *  offset?: int,
 *  primary_key?: string|array<string>,
 *  group?: string,
 *  order?: string,
 *  through?: string
 * }
 *
 * @see http://www.phpactiverecord.org/guides/associations
 * @see valid_association_options
 */
class HasMany extends AbstractRelationship
{
    protected bool $initialized;

    /**
     * Valid options to use for a {@link HasMany} relationship.
     *
     * @var array<string>
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
     * @var string|array<string>
     */
    protected string|array $primary_key;

    private string $through;

    /**
     * Constructs a {@link HasMany} relationship.
     *
     * @param HasManyOptions $options Options for the association
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        if (isset($this->options['through'])) {
            $this->through = $this->options['through'];

            if (isset($this->options['source'])) {
                $this->set_class_name($this->options['source']);
            }
        }

        if (isset($this->options['primary_key'])) {
            $this->primary_key = is_array($this->options['primary_key']) ? $this->options['primary_key'] : [$this->options['primary_key']];
        }

        if (!$this->class_name) {
            $this->set_inferred_class_name();
        }
    }

    protected function set_keys(string $model_class_name, bool $override = false): void
    {
        // infer from class_name
        if (!$this->foreign_key || $override) {
            $this->foreign_key = [Inflector::instance()->keyify($model_class_name)];
        }

        if (!isset($this->primary_key) || $override) {
            $this->primary_key = Table::load($model_class_name)->pk;
        }
    }

    /**
     * @throws HasManyThroughAssociationException
     * @throws \ActiveRecord\Exception\RelationshipException
     *
     * @return Model|array<Model>|null
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
                if (!($through_relationship instanceof self) && !($through_relationship instanceof BelongsTo)) {
                    throw new HasManyThroughAssociationException('has_many through can only use a belongs_to or has_many association');
                }
                // save old keys as we will be reseting them below for inner join convenience
                $pk = $this->primary_key;
                $fk = $this->foreign_key;

                $this->set_keys($this->get_table()->class->getName(), true);

                $class = $this->class_name;
                $relation = $class::table()->get_relationship($this->through);
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

        $options = $this->unset_non_finder_options($this->options);
        $options['conditions'] = $conditions;

        $res = $class_name::find($this->poly_relationship ? 'all' : 'first', $options);

        return $res;
    }

    /**
     * Get an array containing the key and value of the foreign key for the association
     *
     * @return array<string, mixed>
     */
    private function get_foreign_key_for_new_association(Model $model): array
    {
        $this->set_keys(get_class($model));
        $primary_key = Inflector::instance()->variablize($this->foreign_key[0]);

        /*
         * TODO: set up model property reflection stanning
         *
         * @phpstan-ignore-next-line
         */
        return [$primary_key => $model->id];
    }

    /**
     * @param Attributes $attributes
     * @param bool       $guard_attributes
     */
    public function build_association(Model $model, array $attributes = [], $guard_attributes = true): Model
    {
        $relationship_attributes = $this->get_foreign_key_for_new_association($model);

        if ($guard_attributes) {
            // First build the record with just our relationship attributes (unguarded)
            $record = parent::build_association($model, $relationship_attributes, false);

            // Then, set our normal attributes (using guarding)
            $record->set_attributes($attributes);
        } else {
            // Merge our attributes
            $attributes = array_merge($relationship_attributes, $attributes);

            // First build the record with just our relationship attributes (unguarded)
            $record = parent::build_association($model, $attributes, $guard_attributes);
        }

        return $record;
    }

    public function create_association(Model $model, $attributes = [], $guard_attributes = true): Model
    {
        $relationship_attributes = $this->get_foreign_key_for_new_association($model);

        if ($guard_attributes) {
            // First build the record with just our relationship attributes (unguarded)
            $record = parent::build_association($model, $relationship_attributes, false);

            // Then, set our normal attributes (using guarding)
            $record->set_attributes($attributes);

            // Save our model, as a "create" instantly saves after building
            $record->save();
        } else {
            // Merge our attributes
            $attributes = array_merge($relationship_attributes, $attributes);

            // First build the record with just our relationship attributes (unguarded)
            $record = parent::create_association($model, $attributes, $guard_attributes);
        }

        return $record;
    }

    public function load_eagerly($models, $attributes, $includes, Table $table): void
    {
        $this->set_keys($table->class->name);
        $this->query_and_attach_related_models_eagerly($table, $models, $attributes, $includes, $this->foreign_key, $table->pk);
    }
}
