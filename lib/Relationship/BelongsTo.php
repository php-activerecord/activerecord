<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Relation;
use ActiveRecord\Table;
use ActiveRecord\Types;

/**
 * Belongs to relationship.
 *
 * ```
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *   static array $belongs_to = [
 *     'school' => true
 *   ];
 * }
 * ```
 *
 * Example using options:
 *
 * ```
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *  static array $belongs_to = [
 *      'school' => [
 *          'primary_key' => 'school_id'
 *      ]
 *  ]
 * }
 * ```
 *
 * @phpstan-import-type BelongsToOptions from Types
 * @phpstan-import-type Attributes from Model
 *
 * @template TModel of Model
 *
 * @see valid_association_options
 */
class BelongsTo extends AbstractRelationship
{
    /**
     * @var class-string
     */
    public string $class_name;

    /**
     * @var list<string>
     */
    private array $primary_key;

    /**
     * @return list<string>
     */
    public function primary_key(): array
    {
        $this->primary_key ??= [Table::load($this->class_name)->pk[0]];

        return $this->primary_key;
    }

    public function __construct(string $attributeName, $options = [])
    {
        parent::__construct($attributeName, $options);

        if (!isset($this->class_name)) {
            $this->set_class_name(
                $this->inferred_class_name($this->attribute_name)
            );
        }

        // infer from class_name
        if (!$this->foreign_key) {
            $this->foreign_key = [Inflector::keyify($this->class_name)];
        }
    }

    /**
     * @return TModel|null
     */
    public function load(Model $model): ?Model
    {
        $keys = [];
        foreach ($this->foreign_key as $key) {
            $keys[] = Inflector::variablize($key);
        }

        if (!($conditions = $this->create_conditions_from_keys($model, $this->primary_key(), $keys))) {
            return null;
        }

        $options = $this->options;
        $options['conditions'] = array_merge($conditions, $this->options['conditions'] ?? []);
        $class = $this->class_name;

        /**
         * @var Relation<TModel> $rel
         */
        $rel = new Relation($class, [], $options);
        $first = $rel->first();

        return $first;
    }

    /**
     * @param list<Model>      $models
     * @param list<Attributes> $attributes
     * @param array<mixed>     $includes
     */
    public function load_eagerly(array $models, array $attributes, array $includes, Table $table): void
    {
        $this->query_and_attach_related_models_eagerly($table, $models, $attributes, $includes, $this->primary_key(), $this->foreign_key);
    }
}
