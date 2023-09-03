<?php

include 'helpers/foo.php';

use foo\bar\biz\Newsletter;
use foo\bar\biz\User;
use test\models\Venue;

class HasManyThroughTest extends DatabaseTestCase
{
    public function testGh101HasManyThrough()
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

    public function testGh101HasManyThroughInclude()
    {
        $user = User::find(1, [
            'include' => [
                'user_newsletters'
            ]
        ]);

        $this->assertEquals(1, $user->id);
        $this->assertEquals(1, $user->user_newsletters[0]->id);
    }

    public function testGh107HasManyThroughIncludeEager()
    {
        $venue = Venue::find(1, ['include' => ['events']]);
        $this->assertEquals(1, $venue->events[0]->id);

        $venue = Venue::find(1, ['include' => ['hosts']]);
        $this->assertEquals(1, $venue->hosts[0]->id);
    }

    public function testGh107HasManyThoughIncludeEagerWithNamespace()
    {
        $user = User::find(1, [
            'include' => [
                'newsletters'
            ]
        ]);

        $this->assertEquals(1, $user->id);
        $this->assertEquals(1, $user->newsletters[0]->id);
    }
}
// vim: noet ts=4 nobinary
