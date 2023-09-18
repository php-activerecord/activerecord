<?php

namespace test;

use test\models\Venue;

class ActiveRecordFromTest extends \DatabaseTestCase
{
    public function testSimpleTableName(): void
    {
        $venues = Venue::from('events');
        $this->assert_sql_has('FROM events', $venues->to_sql());

        $venue = $venues->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->title);
    }

    public function testAlias(): void
    {
        $venues = Venue::from('events as old_events');
        $this->assert_sql_has('FROM events as old_events', $venues->to_sql());

        $venue = $venues->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->title);
    }

    public function testSubquery(): void
    {
        $venues = Venue::from('(SELECT * from events where title="Monday Night Music Club feat. The Shivers") as specific_events');
        $this->assert_sql_has('SELECT * FROM (SELECT * from events where title=Monday Night Music Club feat. The Shivers) as specific_events', $venues->to_sql());

        $venue = $venues->first();
        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->title);
    }
}
