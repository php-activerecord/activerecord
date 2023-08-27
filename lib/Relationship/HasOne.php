<?php

namespace ActiveRecord\Relationship;

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
 *   static $has_one = array(array('state'));
 * }
 * ```
 *
 * @package ActiveRecord
 *
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasOne extends HasMany
{
}
