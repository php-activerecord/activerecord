<?php

use ActiveRecord\ConnectionManager;
use ActiveRecord\DatabaseException;
use ActiveRecord\Exception\ExpressionsException;
use ActiveRecord\WhereClause;
use PHPUnit\Framework\TestCase;

class WhereClauseTest extends TestCase
{
    public function testValues()
    {
        $c = new WhereClause('a=? and b=?', [1, 2]);
        $this->assertEquals([1, 2], $c->values());
    }

    public function testOneVariable()
    {
        $c = new WhereClause('name=?', ['Tito']);
        $this->assertEquals('name=?', $c->to_s()[0]);
        $this->assertEquals(['Tito'], $c->values());
    }

    public function testArrayVariable()
    {
        $c = new WhereClause('name IN(?) and id=?', [['Tito', 'George'],1]);
        $this->assertEquals([['Tito', 'George'], 1], $c->values());
    }

    public function testMultipleVariables()
    {
        $c = new WhereClause('name=? and book=?', ['Tito', 'Sharks']);
        $this->assertEquals('name=? and book=?', $c->to_s()[0]);
        $this->assertEquals(['Tito', 'Sharks'], $c->values());
    }

    public function testToString()
    {
        $c = new WhereClause('name=? and book=?', ['Tito', 'Sharks']);
        $this->assertEquals('name=? and book=?', $c->to_s()[0]);
    }

    public function testToStringWithArrayVariable()
    {
        $c = new WhereClause('name IN(?) and id=?', [['Tito', 'George'], 1]);
        $this->assertEquals('name IN(?,?) and id=?', $c->to_s()[0]);
    }

    public function testToStringWithEmptyOptions()
    {
        $c = new WhereClause('name=? and book=?', ['Tito', 'Sharks']);
        $x = [];
        $this->assertEquals('name=? and book=?', $c->to_s(false, $x)[0]);
    }

    public function testInsufficientVariables()
    {
        $this->expectException(ExpressionsException::class);
        $c = new WhereClause('name=? and id=?', ['Tito']);
        $c->to_s();
    }

    public function testNoValues()
    {
        $c = new WhereClause( "name='Tito'");
        $this->assertEquals("name='Tito'", $c->to_s()[0]);
        $this->assertEquals(0, count($c->values()));
    }

    public function testEmptyVariable()
    {
        $a = new WhereClause('name=?', [null]);
        $this->assertEquals('name=?', $a->to_s()[0]);
        $this->assertEquals([null], $a->values());
    }

    public function testZeroVariable()
    {
        $a = new WhereClause('name=?', [0]);
        $this->assertEquals('name=?', $a->to_s()[0]);
        $this->assertEquals([0], $a->values());
    }

    public function testEmptyArrayVariable()
    {
        $a = new WhereClause(null, 'id IN(?)', []);
        $this->assertEquals('id IN(?)', $a->to_s());
        $this->assertEquals([[]], $a->values());
    }

    public function testIgnoreInvalidParameterMarker()
    {
        $a = new WhereClause(null, "question='Do you love backslashes?' and id in(?)", [1, 2]);
        $this->assertEquals("question='Do you love backslashes?' and id in(?,?)", $a->to_s());
    }

    public function testIgnoreParameterMarkerWithEscapedQuote()
    {
        $a = new WhereClause(null, "question='Do you love''s backslashes?' and id in(?)", [1, 2]);
        $this->assertEquals("question='Do you love''s backslashes?' and id in(?,?)", $a->to_s());
    }

    public function testIgnoreParameterMarkerWithBackspaceEscapedQuote()
    {
        $a = new WhereClause(null, "question='Do you love\\'s backslashes?' and id in(?)", [1, 2]);
        $this->assertEquals("question='Do you love\\'s backslashes?' and id in(?,?)", $a->to_s());
    }

    public function testSubstitute()
    {
        $a = new WhereClause(null, 'name=? and id=?', 'Tito', 1);
        $this->assertEquals("name='Tito' and id=1", $a->to_s(true));
    }

    public function testSubstituteQuotesScalarsButNotOthers()
    {
        $a = new WhereClause(null, 'id in(?)', [1, '2', 3.5]);
        $this->assertEquals("id in(1,'2',3.5)", $a->to_s(true));
    }

    public function testSubstituteWhereValueHasQuestionMark()
    {
        $a = new WhereClause(null, 'name=? and id=?', '??????', 1);
        $this->assertEquals("name='??????' and id=1", $a->to_s(true));
    }

    public function testSubstituteArrayValue()
    {
        $a = new WhereClause(null, 'id in(?)', [1, 2]);
        $this->assertEquals('id in(1,2)', $a->to_s(true));
    }

    public function testSubstituteEscapesQuotes()
    {
        $a = new WhereClause(null, 'name=? or name in(?)', "Tito's Guild", [1, "Tito's Guild"]);
        $this->assertEquals("name='Tito''s Guild' or name in(1,'Tito''s Guild')", $a->to_s(true));
    }

    public function testSubstituteEscapeQuotesWithConnectionsEscapeMethod()
    {
        try {
            $conn = ConnectionManager::get_connection();
        } catch (DatabaseException $e) {
            $this->markTestSkipped('failed to connect. ' . $e->getMessage());
        }
        $a = new WhereClause('name=?', ["Tito's Guild"]);
        $a->set_connection($conn);
        $escaped = $conn->escape("Tito's Guild");
        $this->assertEquals("name=$escaped", $a->to_s(substitute: true)[0]);
    }

    public function testBind()
    {
        $a = new WhereClause(null, 'name=? and id=?', 'Tito');
        $a->bind(2, 1);
        $this->assertEquals(['Tito', 1], $a->values());
    }

    public function testBindOverwriteExisting()
    {
        $a = new WhereClause(null, 'name=? and id=?', 'Tito', 1);
        $a->bind(2, 99);
        $this->assertEquals(['Tito', 99], $a->values());
    }

    public function testBindInvalidParameterNumber()
    {
        $this->expectException(ExpressionsException::class);
        $a = new WhereClause(null, 'name=?');
        $a->bind(0, 99);
    }

    public function testSubstituteUsingAlternateValues()
    {
        $a = new WhereClause(null, 'name=?', 'Tito');
        $this->assertEquals("name='Tito'", $a->to_s(true));
        $x = ['values' => ['Hocus']];
        $this->assertEquals("name='Hocus'", $a->to_s(true, $x));
    }

    public function testNullValue()
    {
        $a = new WhereClause(null, 'name=?', null);
        $this->assertEquals('name=NULL', $a->to_s(true));
    }

    public function testHashWithDefaultGlue()
    {
        $a = new WhereClause(null, ['id' => 1, 'name' => 'Tito']);
        $this->assertEquals('id=? AND name=?', $a->to_s());
    }

    public function testHashWithGlue()
    {
        $a = new WhereClause([
            'id' => 1,
            'name' => 'Tito'
        ]);
        $this->assertEquals('id=?, name=?', $a->to_s()[0]);
    }

    public function testHashWithArray()
    {
        $a = new WhereClause([
            'id' => 1,
            'name' => ['Tito', 'Mexican']
        ]);
        $this->assertEquals('id=? AND name IN(?,?)', $a->to_s()[0]);
    }
}
