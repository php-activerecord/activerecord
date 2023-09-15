<?php

namespace test;

use test\models\Venue;

class ActiveRecordIncludesTest extends \DatabaseTestCase
{
    public function testSimpleString(): void
    {
        $venue = Venue::includes('events')
            ->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->events[0]->title);
    }

    public function testChainSimpleString(): void
    {
        $venue = Venue::includes('events')
            ->includes('hosts')
            ->first();

        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->events[0]->title);
        $this->assertEquals('David Letterman', $venue->hosts[0]->name);
    }

    public function testArrayOfSimpleStrings(): void
    {
        $venue = Venue::includes(['events', 'hosts'])
            ->first();
        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->events[0]->title);
        $this->assertEquals('David Letterman', $venue->hosts[0]->name);
    }

    public function testListOfSimpleStrings(): void
    {
        $venue = Venue::includes('events', 'hosts')
            ->first();
        $this->assertEquals('Monday Night Music Club feat. The Shivers', $venue->events[0]->title);
        $this->assertEquals('David Letterman', $venue->hosts[0]->name);
    }
}
