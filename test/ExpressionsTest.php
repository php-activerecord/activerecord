<?php

require_once __DIR__ . '/../lib/Expressions.php';

use ActiveRecord\ConnectionManager;
use ActiveRecord\DatabaseException;
use ActiveRecord\Expressions;

class ExpressionsTest extends SnakeCase_PHPUnit_Framework_TestCase
{
    public function test_values()
    {
        $c = new Expressions(null, 'a=? and b=?', 1, 2);
        $this->assert_equals([1, 2], $c->values());
    }

    public function test_one_variable()
    {
        $c = new Expressions(null, 'name=?', 'Tito');
        $this->assert_equals('name=?', $c->to_s());
        $this->assert_equals(['Tito'], $c->values());
    }

    public function test_array_variable()
    {
        $c = new Expressions(null, 'name IN(?) and id=?', ['Tito', 'George'], 1);
        $this->assert_equals([['Tito', 'George'], 1], $c->values());
    }

    public function test_multiple_variables()
    {
        $c = new Expressions(null, 'name=? and book=?', 'Tito', 'Sharks');
        $this->assert_equals('name=? and book=?', $c->to_s());
        $this->assert_equals(['Tito', 'Sharks'], $c->values());
    }

    public function test_to_string()
    {
        $c = new Expressions(null, 'name=? and book=?', 'Tito', 'Sharks');
        $this->assert_equals('name=? and book=?', $c->to_s());
    }

    public function test_to_string_with_array_variable()
    {
        $c = new Expressions(null, 'name IN(?) and id=?', ['Tito', 'George'], 1);
        $this->assert_equals('name IN(?,?) and id=?', $c->to_s());
    }

    public function test_to_string_with_null_options()
    {
        $c = new Expressions(null, 'name=? and book=?', 'Tito', 'Sharks');
        $x = null;
        $this->assert_equals('name=? and book=?', $c->to_s(false, $x));
    }

    public function test_insufficient_variables()
    {
        $this->expectException(\ActiveRecord\ExpressionsException::class);
        $c = new Expressions(null, 'name=? and id=?', 'Tito');
        $c->to_s();
    }

    public function test_no_values()
    {
        $c = new Expressions(null, "name='Tito'");
        $this->assert_equals("name='Tito'", $c->to_s());
        $this->assert_equals(0, count($c->values()));
    }

    public function test_null_variable()
    {
        $a = new Expressions(null, 'name=?', null);
        $this->assert_equals('name=?', $a->to_s());
        $this->assert_equals([null], $a->values());
    }

    public function test_zero_variable()
    {
        $a = new Expressions(null, 'name=?', 0);
        $this->assert_equals('name=?', $a->to_s());
        $this->assert_equals([0], $a->values());
    }

    public function test_empty_array_variable()
    {
        $a = new Expressions(null, 'id IN(?)', []);
        $this->assert_equals('id IN(?)', $a->to_s());
        $this->assert_equals([[]], $a->values());
    }

    public function test_ignore_invalid_parameter_marker()
    {
        $a = new Expressions(null, "question='Do you love backslashes?' and id in(?)", [1, 2]);
        $this->assert_equals("question='Do you love backslashes?' and id in(?,?)", $a->to_s());
    }

    public function test_ignore_parameter_marker_with_escaped_quote()
    {
        $a = new Expressions(null, "question='Do you love''s backslashes?' and id in(?)", [1, 2]);
        $this->assert_equals("question='Do you love''s backslashes?' and id in(?,?)", $a->to_s());
    }

    public function test_ignore_parameter_marker_with_backspace_escaped_quote()
    {
        $a = new Expressions(null, "question='Do you love\\'s backslashes?' and id in(?)", [1, 2]);
        $this->assert_equals("question='Do you love\\'s backslashes?' and id in(?,?)", $a->to_s());
    }

    public function test_substitute()
    {
        $a = new Expressions(null, 'name=? and id=?', 'Tito', 1);
        $this->assert_equals("name='Tito' and id=1", $a->to_s(true));
    }

    public function test_substitute_quotes_scalars_but_not_others()
    {
        $a = new Expressions(null, 'id in(?)', [1, '2', 3.5]);
        $this->assert_equals("id in(1,'2',3.5)", $a->to_s(true));
    }

    public function test_substitute_where_value_has_question_mark()
    {
        $a = new Expressions(null, 'name=? and id=?', '??????', 1);
        $this->assert_equals("name='??????' and id=1", $a->to_s(true));
    }

    public function test_substitute_array_value()
    {
        $a = new Expressions(null, 'id in(?)', [1, 2]);
        $this->assert_equals('id in(1,2)', $a->to_s(true));
    }

    public function test_substitute_escapes_quotes()
    {
        $a = new Expressions(null, 'name=? or name in(?)', "Tito's Guild", [1, "Tito's Guild"]);
        $this->assert_equals("name='Tito''s Guild' or name in(1,'Tito''s Guild')", $a->to_s(true));
    }

    public function test_substitute_escape_quotes_with_connections_escape_method()
    {
        try {
            $conn = ConnectionManager::get_connection();
        } catch (DatabaseException $e) {
            $this->mark_test_skipped('failed to connect. ' . $e->getMessage());
        }
        $a = new Expressions(null, 'name=?', "Tito's Guild");
        $a->set_connection($conn);
        $escaped = $conn->escape("Tito's Guild");
        $this->assert_equals("name=$escaped", $a->to_s(true));
    }

    public function test_bind()
    {
        $a = new Expressions(null, 'name=? and id=?', 'Tito');
        $a->bind(2, 1);
        $this->assert_equals(['Tito', 1], $a->values());
    }

    public function test_bind_overwrite_existing()
    {
        $a = new Expressions(null, 'name=? and id=?', 'Tito', 1);
        $a->bind(2, 99);
        $this->assert_equals(['Tito', 99], $a->values());
    }

    public function test_bind_invalid_parameter_number()
    {
        $this->expectException(\ActiveRecord\ExpressionsException::class);
        $a = new Expressions(null, 'name=?');
        $a->bind(0, 99);
    }

    public function test_subsitute_using_alternate_values()
    {
        $a = new Expressions(null, 'name=?', 'Tito');
        $this->assert_equals("name='Tito'", $a->to_s(true));
        $x = ['values' => ['Hocus']];
        $this->assert_equals("name='Hocus'", $a->to_s(true, $x));
    }

    public function test_null_value()
    {
        $a = new Expressions(null, 'name=?', null);
        $this->assert_equals('name=NULL', $a->to_s(true));
    }

    public function test_hash_with_default_glue()
    {
        $a = new Expressions(null, ['id' => 1, 'name' => 'Tito']);
        $this->assert_equals('id=? AND name=?', $a->to_s());
    }

    public function test_hash_with_glue()
    {
        $a = new Expressions(null, ['id' => 1, 'name' => 'Tito'], ', ');
        $this->assert_equals('id=?, name=?', $a->to_s());
    }

    public function test_hash_with_array()
    {
        $a = new Expressions(null, ['id' => 1, 'name' => ['Tito', 'Mexican']]);
        $this->assert_equals('id=? AND name IN(?,?)', $a->to_s());
    }
}
