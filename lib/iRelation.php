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
}
