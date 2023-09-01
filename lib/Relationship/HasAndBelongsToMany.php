<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Model;
use ActiveRecord\Table;

/**
 * @todo implement me
 *
 * @package ActiveRecord
 *
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasAndBelongsToMany extends AbstractRelationship
{
    public function __construct($options = [])
    {
        /* options =>
         *   join_table - name of the join table if not in lexical order
         *   foreign_key -
         *   association_foreign_key - default is {assoc_class}_id
         *   uniq - if true duplicate assoc objects will be ignored
         *   validate
         */
        parent::__construct($options);
    }

    public function load(Model $model): mixed
    {
        throw new \Exception("HasAndBelongsToMany doesn't need to load anything.");
    }

    public function load_eagerly($models, $attributes, $includes, Table $table): void
    {
        throw new \Exception('load_eagerly undefined for ' . __CLASS__);
    }
}
