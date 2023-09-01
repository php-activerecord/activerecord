<?php

use ActiveRecord\Exception\ActiveRecordException;
use test\models\VenueAfterCreate;
use test\models\VenueCB;

class CallBackTest extends DatabaseTestCase
{
    private $callback;

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);

        // ensure VenueCB model has been loaded
        VenueCB::find(1);

        $this->callback = new ActiveRecord\CallBack(VenueCB::class);
    }

    public function assert_has_callback($callback_name, $method_name=null)
    {
        if (!$method_name) {
            $method_name = $callback_name;
        }

        $this->assertTrue(in_array($method_name, $this->callback->get_callbacks($callback_name)));
    }

    public function assert_implicit_save($first_method, $second_method)
    {
        $i_ran = [];
        $this->callback->register($first_method, function ($model) use (&$i_ran, $first_method) { $i_ran[] = $first_method; });
        $this->callback->register($second_method, function ($model) use (&$i_ran, $second_method) { $i_ran[] = $second_method; });
        $this->callback->invoke(null, $second_method);
        $this->assertEquals([$first_method, $second_method], $i_ran);
    }

    public function test_gh_266_calling_save_in_after_save_callback_uses_update_instead_of_insert()
    {
        $venue = new VenueAfterCreate();
        $venue->name = 'change me';
        $venue->city = 'Awesome City';
        $venue->save();

        $this->assertTrue(VenueAfterCreate::exists(['conditions'=> ['name'=>'changed!']]));
        $this->assertFalse(VenueAfterCreate::exists(['conditions'=> ['name'=>'change me']]));
    }

    public function test_generic_callback_was_auto_registered()
    {
        $this->assert_has_callback('after_construct');
    }

    public function test_register()
    {
        $this->callback->register('after_construct');
        $this->assert_has_callback('after_construct');
    }

    public function test_register_non_generic()
    {
        $this->callback->register('after_construct', 'non_generic_after_construct');
        $this->assert_has_callback('after_construct', 'non_generic_after_construct');
    }

    public function test_register_invalid_callback()
    {
        $this->expectException(ActiveRecordException::class);
        $this->callback->register('invalid_callback');
    }

    public function test_register_callback_with_undefined_method()
    {
        $this->expectException(ActiveRecordException::class);
        $this->callback->register('after_construct', 'do_not_define_me');
    }

    public function test_register_with_string_definition()
    {
        $this->callback->register('after_construct', 'after_construct');
        $this->assert_has_callback('after_construct');
    }

    public function test_register_with_closure()
    {
        $this->expectNotToPerformAssertions();
        $this->callback->register('after_construct', function ($mode) { });
    }

    public function test_register_with_null_definition()
    {
        $this->callback->register('after_construct', null);
        $this->assert_has_callback('after_construct');
    }

    public function test_register_with_no_definition()
    {
        $this->callback->register('after_construct');
        $this->assert_has_callback('after_construct');
    }

    public function test_register_appends_to_registry()
    {
        $this->callback->register('after_construct');
        $this->callback->register('after_construct', 'non_generic_after_construct');
        $this->assertEquals(['after_construct', 'after_construct', 'non_generic_after_construct'], $this->callback->get_callbacks('after_construct'));
    }

    public function test_register_prepends_to_registry()
    {
        $this->callback->register('after_construct');
        $this->callback->register('after_construct', 'non_generic_after_construct', ['prepend' => true]);
        $this->assertEquals(['non_generic_after_construct', 'after_construct', 'after_construct'], $this->callback->get_callbacks('after_construct'));
    }

    public function test_registers_via_static_array_definition()
    {
        $this->assert_has_callback('after_destroy', 'after_destroy_one');
        $this->assert_has_callback('after_destroy', 'after_destroy_two');
    }

    public function test_registers_via_static_string_definition()
    {
        $this->assert_has_callback('before_destroy', 'before_destroy_using_string');
    }

    public function test_register_via_static_with_invalid_definition()
    {
        $this->expectException(ActiveRecordException::class);
        $class_name = 'Venues_' . md5(uniqid());
        eval("class $class_name extends ActiveRecord\\Model { static string \$table_name = 'venues'; static \$after_save = 'method_that_does_not_exist'; };");
        new $class_name();
        new ActiveRecord\CallBack($class_name);
    }

    public function test_can_register_same_multiple_times()
    {
        $this->callback->register('after_construct');
        $this->callback->register('after_construct');
        $this->assertEquals(['after_construct', 'after_construct', 'after_construct'], $this->callback->get_callbacks('after_construct'));
    }

    public function test_register_closure_callback()
    {
        $closure = function ($model) {};
        $this->callback->register('after_save', $closure);
        $this->assertEquals([$closure], $this->callback->get_callbacks('after_save'));
    }

    public function test_get_callbacks_returns_array()
    {
        $this->callback->register('after_construct');
        $this->assertTrue(is_array($this->callback->get_callbacks('after_construct')));
    }

    public function test_get_callbacks_returns_empty()
    {
        $this->assertEquals([], $this->callback->get_callbacks('this_callback_name_should_never_exist'));
    }

    public function test_invoke_runs_all_callbacks()
    {
        if (method_exists($this, 'createMock')) {
            $mock = $this->createMock(VenueCB::class, ['after_destroy_one', 'after_destroy_two']);
        } else {
            $mock = $this->get_mock(VenueCB::class, ['after_destroy_one', 'after_destroy_two']);
        }
        $mock->expects($this->once())->method('after_destroy_one');
        $mock->expects($this->once())->method('after_destroy_two');
        $this->callback->invoke($mock, 'after_destroy');
    }

    public function test_invoke_closure()
    {
        $i_ran = false;
        $this->callback->register('after_validation', function ($model) use (&$i_ran) { $i_ran = true; });
        $this->callback->invoke(null, 'after_validation');
        $this->assertTrue($i_ran);
    }

    public function test_invoke_implicitly_calls_save_first()
    {
        $this->assert_implicit_save('before_save', 'before_create');
        $this->assert_implicit_save('before_save', 'before_update');
        $this->assert_implicit_save('after_save', 'after_create');
        $this->assert_implicit_save('after_save', 'after_update');
    }

    public function test_invoke_unregistered_callback()
    {
        $this->expectException(ActiveRecordException::class);
        $mock = $this->createMock(VenueCB::class, ['columns']);
        $this->callback->invoke($mock, 'before_validation_on_create');
    }

    public function test_before_callbacks_pass_on_false_return_callback_returned_false()
    {
        $this->callback->register('before_validation', function ($model) { return false; });
        $this->assertFalse($this->callback->invoke(null, 'before_validation'));
    }

    public function test_before_callbacks_does_not_pass_on_false_for_after_callbacks()
    {
        $this->callback->register('after_validation', function ($model) { return false; });
        $this->assertTrue($this->callback->invoke(null, 'after_validation'));
    }

    public function test_gh_28_after_create_should_be_invoked_after_auto_incrementing_pk_is_set()
    {
        $that = $this;
        VenueCB::$after_create = function ($model) use ($that) { $that->assertNotNull($model->id); };
        ActiveRecord\Table::clear_cache('VenueCB');
        $venue = VenueCB::find(1);
        $venue = new VenueCB($venue->attributes());
        $venue->id = null;
        $venue->name = 'alksdjfs';
        $venue->save();
    }

    public function test_before_create_returned_false_halts_execution()
    {
        VenueCB::$before_create = ['before_create_halt_execution'];
        ActiveRecord\Table::clear_cache(VenueCB::class);
        $table = ActiveRecord\Table::load(VenueCB::class);

        $i_ran = false;
        $i_should_have_ran = false;
        $table->callback->register('before_save', function ($model) use (&$i_should_have_ran) { $i_should_have_ran = true; });
        $table->callback->register('before_create', function ($model) use (&$i_ran) { $i_ran = true; });
        $table->callback->register('after_create', function ($model) use (&$i_ran) { $i_ran = true; });

        $v = VenueCB::find(1);
        $v->id = null;
        VenueCB::create($v->attributes());

        $this->assertTrue($i_should_have_ran);
        $this->assertFalse($i_ran);
        $this->assertTrue(false === strpos(ActiveRecord\Table::load(VenueCB::class)->last_sql, 'INSERT'));
    }

    public function test_before_save_returned_false_halts_execution()
    {
        VenueCB::$before_update = ['before_update_halt_execution'];
        ActiveRecord\Table::clear_cache(VenueCB::class);
        $table = ActiveRecord\Table::load(VenueCB::class);

        $i_ran = false;
        $i_should_have_ran = false;
        $table->callback->register('before_save', function ($model) use (&$i_should_have_ran) { $i_should_have_ran = true; });
        $table->callback->register('before_update', function ($model) use (&$i_ran) { $i_ran = true; });
        $table->callback->register('after_save', function ($model) use (&$i_ran) { $i_ran = true; });

        $v = VenueCB::find(1);
        $v->name .= 'test';
        $ret = $v->save();

        $this->assertTrue($i_should_have_ran);
        $this->assertFalse($i_ran);
        $this->assertFalse($ret);
        $this->assertTrue(false === strpos(ActiveRecord\Table::load(VenueCB::class)->last_sql, 'UPDATE'));
    }

    public function test_before_destroy_returned_false_halts_execution()
    {
        VenueCB::$before_destroy = ['before_destroy_halt_execution'];
        ActiveRecord\Table::clear_cache(VenueCB::class);
        $table = ActiveRecord\Table::load(VenueCB::class);

        $i_ran = false;
        $table->callback->register('before_destroy', function ($model) use (&$i_ran) { $i_ran = true; });
        $table->callback->register('after_destroy', function ($model) use (&$i_ran) { $i_ran = true; });

        $v = VenueCB::find(1);
        $ret = $v->delete();

        $this->assertFalse($i_ran);
        $this->assertFalse($ret);
        $this->assertTrue(false === strpos(ActiveRecord\Table::load(VenueCB::class)->last_sql, 'DELETE'));
    }

    public function test_before_validation_returned_false_halts_execution()
    {
        VenueCB::$before_validation = ['before_validation_halt_execution'];
        ActiveRecord\Table::clear_cache(VenueCB::class);
        ActiveRecord\Table::load(VenueCB::class);

        $v = VenueCB::find(1);
        $v->name .= 'test';
        $ret = $v->save();

        $this->assertFalse($ret);
        $this->assertTrue(false === strpos(ActiveRecord\Table::load(VenueCB::class)->last_sql, 'UPDATE'));
    }
}
