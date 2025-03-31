<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Inflector;
use ActiveRecord\Model;
use ActiveRecord\Relation;
use ActiveRecord\Table;
use ActiveRecord\Types;
use ActiveRecord\Utils;

/**
 * @todo implement me
 *
 * @template TModel of Model
 *
 * @phpstan-import-type HasAndBelongsToManyOptions from Types
 */
class HasAndBelongsToMany extends AbstractRelationship
{
    protected string $association_foreign_key = '';

    /**
     * @param HasAndBelongsToManyOptions $options
     */
    public function __construct(string $attribute, array $options = [])
    {
        parent::__construct($attribute, $options);

        $this->set_class_name($this->inferred_class_name(Utils::singularize($attribute)));

        $this->options['association_foreign_key'] ??= Inflector::keyify($this->class_name);
    }

    public function is_poly(): bool
    {
        return true;
    }

    /**
     * @return list<TModel>
     */
    public function load(Model $model): mixed
    {
        $rel = new Relation($this->class_name, [], []);
        // Use the join table rather than the attribute name.
        $join_table = $this->options['join_table'];
        $rel->from($join_table);

        // Filter on the join table's column that references the parent model.
        $rel->where($join_table . '.' . $this->options['foreign_key'] . ' = ?', $model->{$model->get_primary_key()});

        // Now join the associated table using its actual primary key.
        $associated_table = Table::load($this->class_name)->table;
        $associated_pk  = Table::load($this->class_name)->pk[0];
        $rel->joins([
            'INNER JOIN ' . $associated_table .
            ' ON ' . $associated_table . '.' . $associated_pk . ' = ' . $join_table . '.' . $this->options['association_foreign_key']
        ]);

        $results = $rel->to_a();
        /** @var list<TModel> $results */
        return $results;
    }

    public static function inferJoiningTableName(string $class_name, string $association_name): string
    {
        $parts = [$association_name, $class_name];
        sort($parts);

        return implode('_', $parts);
    }

    public function construct_inner_join_sql(Table $from_table, bool $using_through = false, string $alias = null): string
    {
        $other_table = Table::load($this->class_name);
        $associated_table_name = $other_table->table;
        $from_table_name = $from_table->table;
        $foreign_key = $this->options['foreign_key'];
        $join_primary_key = $this->options['association_foreign_key'];
        $linkingTableName = $this->options['join_table'];
        $res = 'INNER JOIN ' . $linkingTableName . " ON ($from_table_name.$foreign_key = " . $linkingTableName . ".$foreign_key) "
            . 'INNER JOIN ' . $associated_table_name . ' ON ' . $associated_table_name . '.' . $join_primary_key . ' = ' . $linkingTableName . '.' . $join_primary_key;

        return $res;
    }

    public function load_eagerly($models, $attributes, $includes, Table $table): void
    {
        throw new \Exception('load_eagerly undefined for ' . __CLASS__);
    }
}
