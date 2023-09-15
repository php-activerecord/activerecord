<?php
/**
 * This file is not something we need to execute in tests. It's included
 * only as a means to test and aid in the development of dynamic PHPStan
 * extensions. If it doesn't emit any errors when you run 'composer stan',
 * then everything is working fine.
 *
 * see lib/PhpStan/FindDynamicMethodReturnTypeReflection.php
 */

use ActiveRecord\Relation;
use test\models\Book;

class RelationTester
{
    /**
     * Confirm that anything that returns a Relation has knowledge of the static type
     */

    /**
     * @return Relation<Book>
     */
    public function all(): Relation
    {
        return Book::all();
    }

    /**
     * @return Relation<Book>
     */
    public function where(): Relation
    {
        return Book::where([]);
    }

    /**
     * @return Relation<Book>
     */
    public function includes(): Relation
    {
        return Book::includes([]);
    }
}
