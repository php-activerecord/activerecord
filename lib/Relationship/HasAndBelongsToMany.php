<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Model;
use ActiveRecord\Table;
use ActiveRecord\Types;

/**
 * @todo implement me
 *
 * @package ActiveRecord
 *
 * @phpstan-import-type HasAndBelongsToManyOptions from Types
 */
class HasAndBelongsToMany extends AbstractRelationship
{
    /**
     * @param HasAndBelongsToManyOptions $options
     */
    public function __construct(string $attribute, array $options = [])
    {
        parent::__construct($attribute, $options);
    }

    public function is_poly(): bool
    {
        return true;
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
