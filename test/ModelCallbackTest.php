<?php

use ActiveRecord\Table;
use test\models\Venue;

class ModelCallbackTest extends DatabaseTestCase
{
    private $callback;
    private $venue;

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);

        $this->venue = new Venue();
        $this->callback = Table::load(Venue::class)->callback;
    }

    public function register_and_invoke_callbacks($callbacks, $return, $closure)
    {
        if (!is_array($callbacks)) {
            $callbacks = [$callbacks];
        }

        $fired = [];

        foreach ($callbacks as $name) {
            $this->callback->register($name, function ($model) use (&$fired, $name, $return) {
                $fired[] = $name;

                return $return;
            });
        }

        $closure($this->venue);

        return array_intersect($callbacks, $fired);
    }

    public function assert_fires($callbacks, $closure)
    {
        $executed = $this->register_and_invoke_callbacks($callbacks, true, $closure);
        $this->assertEquals(count((array) $callbacks), count((array) $executed));
    }

    public function assert_does_not_fire($callbacks, $closure)
    {
        $executed = $this->register_and_invoke_callbacks($callbacks, true, $closure);
        $this->assertEquals(0, count($executed));
    }

    public function assert_fires_returns_false($callbacks, $only_fire, $closure)
    {
        if (!is_array($only_fire)) {
            $only_fire = [$only_fire];
        }

        $executed = $this->register_and_invoke_callbacks($callbacks, false, $closure);
        sort($only_fire);
        $intersect = array_intersect($only_fire, $executed);
        sort($intersect);
        $this->assertEquals($only_fire, $intersect);
    }

    public function testAfterConstructFiresByDefault()
    {
        $this->assert_fires('after_construct', function ($model) { new Venue(); });
    }

    public function testFireValidationCallbacksOnInsert()
    {
        $this->assert_fires(['before_validation', 'after_validation', 'before_validation_on_create', 'after_validation_on_create'],
            function ($model) {
                $model = new Venue();
                $model->save();
            });
    }

    public function testFireValidationCallbacksOnUpdate()
    {
        $this->assert_fires(['before_validation', 'after_validation', 'before_validation_on_update', 'after_validation_on_update'],
            function ($model) {
                $model = Venue::first();
                $model->save();
            });
    }

    public function testValidationCallBacksNotFiredDueToBypassingValidations()
    {
        $this->assert_does_not_fire('before_validation', function ($model) { $model->save(false); });
    }

    public function testBeforeValidationReturningFalseCancelsCallbacks()
    {
        $this->assert_fires_returns_false(['before_validation', 'after_validation'], 'before_validation',
            function ($model) { $model->save(); });
    }

    public function testFiresBeforeSaveAndBeforeUpdateWhenUpdating()
    {
        $this->assert_fires(['before_save', 'before_update'],
            function ($model) {
                $model = Venue::first();
                $model->name = 'something new';
                $model->save();
            });
    }

    public function testBeforeSaveReturningFalseCancelsCallbacks()
    {
        $this->assert_fires_returns_false(['before_save', 'before_create'], 'before_save',
            function ($model) {
                $model = new Venue();
                $model->save();
            });
    }

    public function testDestroy()
    {
        $this->assert_fires(['before_destroy', 'after_destroy'],
            function ($model) { $model->delete(); });
    }
}
