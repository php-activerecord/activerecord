<?php

include 'helpers/foo.php';

use foo\bar\biz\Newsletter;
use foo\bar\biz\User;
use test\models\Venue;

class HasManyThroughTest extends DatabaseTestCase
{
    public function testHasManyThrough()
    {
        $user = User::find(1);
        $newsletter = Newsletter::find(1);

        $this->assertEquals($newsletter->id, $user->newsletters[0]->id);
        $this->assertEquals(
            'foo\bar\biz\Newsletter',
            get_class($user->newsletters[0])
        );
        $this->assertEquals($user->id, $newsletter->users[0]->id);
        $this->assertEquals(
            'foo\bar\biz\User',
            get_class($newsletter->users[0])
        );
    }

    public function testHasManyThroughInclude()
    {
        $user = User::includes([
            'user_newsletters'
        ])->find(1);

        $this->assertEquals(1, $user->id);
        $this->assertEquals(1, $user->user_newsletters[0]->id);
    }

    public function testHasManyThroughIncludeEager()
    {
        $venue = Venue::includes('events')->find(1);
        $this->assertEquals(1, $venue->events[0]->id);

        $venue = Venue::includes('hosts')->find(1);
        $this->assertEquals(1, $venue->hosts[0]->id);
    }

    public function testHasManyThoughIncludeEagerWithNamespace()
    {
        $user = User::includes('newsletters')->find(1);

        $this->assertEquals(1, $user->id);
        $this->assertEquals(1, $user->newsletters[0]->id);
    }
}
