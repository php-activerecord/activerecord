<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Table;

/**
 * Belongs to relationship.
 *
 * ```
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *   static $belongs_to = array(
 *     array('school')
 *   );
 * }
 * ```
 *
 * Example using options:
 *
 * ```
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *   static $belongs_to = array(
 *     array('school', 'primary_key' => 'school_id')
 *   );
 * }
 * ```
 *
 * @package ActiveRecord
 *
 * @see valid_association_options
 * @see http://www.phpactiverecord.org/guides/associations
 */
class BelongsTo extends AbstractRelationship
{
    private array $primary_key;

    public function primary_key(): array {
        if(!isset($this->primary_key)) {
            $this->primary_key = [Table::load($this->class_name)->pk[0]];
        }
        return $this->primary_key;
    }

    public function __construct($options = [])
    {
        parent::__construct($options);

        if (!$this->class_name) {
            $this->set_inferred_class_name();
        }

        //infer from class_name
        if (!$this->foreign_key) {
            $this->foreign_key = [Inflector::instance()->keyify($this->class_name)];
        }
    }

    public function load(Model $model)
    {
        $keys = [];
        $inflector = Inflector::instance();

        foreach ($this->foreign_key as $key) {
            $keys[] = $inflector->variablize($key);
        }

        if (!($conditions = $this->create_conditions_from_keys($model, $this->primary_key(), $keys))) {
            return null;
        }

        $options = $this->unset_non_finder_options($this->options);
        $options['conditions'] = $conditions;
        $class = $this->class_name;

        return $class::first($options);
    }

    public function load_eagerly($models, $attributes, $includes, Table $table)
    {
        $this->query_and_attach_related_models_eagerly($table, $models ?? [], $attributes, $includes, $this->primary_key(), $this->foreign_key);
    }
}
