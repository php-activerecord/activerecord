<?php
/**
 * Interface
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

interface iRelation
{
    /**
     * @return Model|array<Model>
     */
    public function find(): Model|array;

    /**
     * @return static|array<static>|null
     */
    public static function first(int $limit = null): mixed;
}
