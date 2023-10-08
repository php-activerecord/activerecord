<?php

namespace test;

use ActiveRecord;
use ActiveRecord\Table;
use test\models\Author;
use test\models\Venue;

class ActiveRecordGroupTest extends \DatabaseTestCase
{
    public function testWithString(): void
    {
        $venues = Venue::select('state')->group('state')->to_a();
        $this->assertTrue(count($venues) > 0);
        $this->assert_sql_includes('GROUP BY state', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testWithArray(): void
    {
        $venues = Venue::select(['city', 'state'])->group(['city', 'state'])->to_a();
        $this->assertTrue(count($venues) > 0);

        $this->assert_sql_includes('GROUP BY city, state', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testWithList(): void
    {
        $venues = Venue::select('city', 'state')->group('city', 'state')->to_a();
        $this->assertTrue(count($venues) > 0);

        $this->assert_sql_includes('GROUP BY city, state', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testGroupWithOrderAndLimitAndHaving(): void
    {
        $relation = Venue::select('state')
            ->group('state')
            ->having('length(state) = 2')
            ->order('state')
            ->limit(2);

        $venues = $relation->to_a();
        $this->assertTrue(count($venues) > 0);
        $this->assert_sql_includes('SELECT state FROM venues GROUP BY state HAVING length(state) = 2 ORDER BY state', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testHaving(): void
    {
        Author::select('date(created_at) as created_at')
            ->group('date(created_at)')
            ->having("date(created_at) > '2009-01-01'")
            ->first();
        $this->assert_sql_includes("GROUP BY date(created_at) HAVING date(created_at) > '2009-01-01'", Table::load(Author::class)->last_sql);
    }
}
