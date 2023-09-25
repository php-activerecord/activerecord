<?php

namespace test;

use test\models\Event;
use test\models\Venue;

class DelegateTest extends \DatabaseTestCase
{
    private $options;

    public function testDelegate()
    {
        $event = Event::first();
        $this->assertEquals($event->venue->state, $event->state);
        $this->assertEquals($event->venue->address, $event->address);
    }

    public function testDelegatePrefix()
    {
        $event = Event::first();
        $this->assertEquals($event->host->name, $event->woot_name);
    }

    public function testDelegateReturnsNullIfRelationshipDoesNotExist()
    {
        $event = new Event();
        $this->assertNull($event->state);
    }

    public function testDelegateSetAttribute()
    {
        $event = Event::first();
        $event->state = 'MEXICO';
        $this->assertEquals('MEXICO', $event->venue->state);
    }

    public function testDelegateGetterGh98()
    {
        Venue::$use_custom_get_state_getter = true;

        $event = Event::first();
        $this->assertEquals('ny', $event->venue->state);
        $this->assertEquals('ny', $event->state);

        Venue::$use_custom_get_state_getter = false;
    }

    public function testDelegateSetterGh98()
    {
        Venue::$use_custom_set_state_setter = true;

        $event = Event::first();
        $event->state = 'MEXICO';
        $this->assertEquals('MEXICO#', $event->venue->state);

        Venue::$use_custom_set_state_setter = false;
    }
}
