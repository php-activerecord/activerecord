<?php

namespace ActiveRecord\Relationship;

use ActiveRecord\Types;

/**
 * One-to-one relationship.
 *
 * ```
 * # Table name: states
 * # Primary key: id
 * class State extends ActiveRecord\Model {}
 *
 * # Table name: people
 * # Foreign key: state_id
 * class Person extends ActiveRecord\Model {
 *   static array $has_one = [
 *    'state' => true
 *   ];
 * }
 * ```
 *
 * @phpstan-import-type HasManyOptions from Types
 *
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasOne extends HasMany
{
    /**
     * @param HasManyOptions $options
     */
    public function __construct(string $attribute, array $options = [])
    {
        parent::__construct($attribute, $options);
    }

    public function is_poly(): bool
    {
        return false;
    }
}
