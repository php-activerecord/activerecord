<?php

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\SQLBuilder;
use ActiveRecord\Table;
use ActiveRecord\WhereClause;
use test\models\Author;

class SQLBuilderTest extends DatabaseTestCase
{
    private $sql;

    protected $table_name = 'authors';
    protected $class_name = Author::class;
    protected $table;

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
        $this->sql = new SQLBuilder($this->connection, $this->table_name);
        $this->table = Table::load($this->class_name);
    }

    protected function cond_from_s($name, $values=[], $map=[]): ?WhereClause
    {
        return WhereClause::from_underscored_string($this->table->conn, $name, $values, $map);
    }

    public function assert_conditions($expected_sql, $values, $underscored_string, $map=[])
    {
        $cond = WhereClause::from_underscored_string($this->table->conn, $underscored_string, $values, $map);
        $this->assert_sql_has($expected_sql, $cond->expression());

        if ($values) {
            $this->assertEquals(array_values(array_filter($values, function ($s) { return null !== $s; })), $cond->values());
        } else {
            $this->assertEquals([], $cond->values());
        }
    }

    public function testNothing()
    {
        $this->assertEquals('SELECT * FROM authors', (string) $this->sql);
    }

    public function testWhereWithArray()
    {
        $this->sql->where([
            new WhereClause('id=? AND name IN(?)', [1, ['Tito', 'Mexican']])]);
        $this->assert_sql_has('SELECT * FROM authors WHERE id=? AND name IN(?,?)', (string) $this->sql);
        $this->assertEquals([1, 'Tito', 'Mexican'], $this->sql->get_where_values());
    }

    public function testWhereWithHash()
    {
        $this->sql->where([new WhereClause([
            'id' => 1,
            'name' => 'Tito'
        ])]);
        $this->assert_sql_has('SELECT * FROM authors WHERE id=? AND name=?', (string) $this->sql);
        $this->assertEquals([1, 'Tito'], $this->sql->get_where_values());
    }

    public function testWhereWithHashAndArray()
    {
        $this->sql->where([new WhereClause(['id' => 1, 'name' => ['Tito', 'Mexican']])]);
        $this->assert_sql_has('SELECT * FROM authors WHERE id=? AND name IN(?,?)', (string) $this->sql);
        $this->assertEquals([1, 'Tito', 'Mexican'], $this->sql->get_where_values());
    }

    public function testGh134WhereWithHashAndNull()
    {
        $this->sql->where([new WhereClause(['id' => 1, 'name' => null])]);
        $this->assert_sql_has('SELECT * FROM authors WHERE id=? AND name IS ?', (string) $this->sql);
        $this->assertEquals([1, null], $this->sql->get_where_values());
    }

    public function testWhereWithEmpty()
    {
        $this->sql->where([]);
        $this->assertEquals('SELECT * FROM authors', (string) $this->sql);
    }

    public function testWhereWithNoArgs()
    {
        $this->sql->where();
        $this->assertEquals('SELECT * FROM authors', (string) $this->sql);
    }

    public function testOrder()
    {
        $this->sql->order('name');
        $this->assertEquals('SELECT * FROM authors ORDER BY name', (string) $this->sql);
    }

    public function testLimit()
    {
        $this->sql->limit(10)->offset(1);
        $this->assertEquals($this->connection->limit('SELECT * FROM authors', 1, 10), (string) $this->sql);
    }

    public function testSelect()
    {
        $this->sql->select('id,name');
        $this->assertEquals('SELECT id,name FROM authors', (string) $this->sql);
    }

    public function testJoins()
    {
        $join = 'inner join books on(authors.id=books.author_id)';
        $this->sql->joins($join);
        $this->assertEquals("SELECT * FROM authors $join", (string) $this->sql);
    }

    public function testGroup()
    {
        $this->sql->group('name');
        $this->assertEquals('SELECT * FROM authors GROUP BY name', (string) $this->sql);
    }

    public function testHaving()
    {
        $this->sql->having("created_at > '2009-01-01'");
        $this->assertEquals("SELECT * FROM authors HAVING created_at > '2009-01-01'", (string) $this->sql);
    }

    public function testAllClausesAfterWhereShouldBeCorrectlyOrdered()
    {
        $this->sql->limit(10)->offset(1);
        $this->sql->having("created_at > '2009-01-01'");
        $this->sql->order('name');
        $this->sql->group('name');
        $this->sql->where([new WhereClause(['id' => 1])]);
        $this->assert_sql_has($this->connection->limit("SELECT * FROM authors WHERE id=? GROUP BY name HAVING created_at > '2009-01-01' ORDER BY name", 1, 10), (string) $this->sql);
    }

    public function testInsertRequiresHash()
    {
        $this->expectException(ActiveRecordException::class);
        $this->sql->insert([1]);
    }

    public function testInsert()
    {
        $this->sql->insert(['id' => 1, 'name' => 'Tito']);
        $this->assert_sql_has('INSERT INTO authors(id,name) VALUES(?,?)', (string) $this->sql);
    }

    public function testInsertWithNull()
    {
        $this->sql->insert(['id' => 1, 'name' => null]);
        $this->assert_sql_has('INSERT INTO authors(id,name) VALUES(?,?)', $this->sql->to_s());
    }

    public function testUpdateWithHash()
    {
        $this->sql->update(['id' => 1, 'name' => 'Tito'])
            ->where([new WhereClause('id=1 AND name IN(?)', [['Tito', 'Mexican']])]);
        $this->assert_sql_has('UPDATE authors SET id=?, name=? WHERE id=1 AND name IN(?,?)', (string) $this->sql);
        $this->assertEquals([1, 'Tito', 'Tito', 'Mexican'], $this->sql->bind_values());
    }

    public function testUpdateWithLimitAndOrder()
    {
        if (!$this->connection->accepts_limit_and_order_for_update_and_delete()) {
            $this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE operation');
        }

        $this->sql->update(['id' => 1])->order('name asc')->limit(1);
        $this->assert_sql_has('UPDATE authors SET id=? ORDER BY name asc LIMIT 1', $this->sql->to_s());
    }

    public function testUpdateWithString()
    {
        $this->sql->update("name='Bob'");
        $this->assert_sql_has("UPDATE authors SET name='Bob'", $this->sql->to_s());
    }

    public function testUpdateWithNull()
    {
        $this->sql->update(['id' => 1, 'name' => null])
            ->where([new WhereClause('id=1')]);
        $this->assert_sql_has('UPDATE authors SET id=?, name=? WHERE id=1', $this->sql->to_s());
    }

    public function testDelete()
    {
        $this->sql->delete();
        $this->assertEquals('DELETE FROM authors', $this->sql->to_s());
    }

    public function testDeleteWithWhere()
    {
        $this->sql->delete(['id=? or name in(?)', 1, ['Tito', 'Mexican']]);
        $this->assertEquals('DELETE FROM authors WHERE id=? or name in(?,?)', $this->sql->to_s());
        $this->assertEquals([1, 'Tito', 'Mexican'], $this->sql->bind_values());
    }

    public function testDeleteWithHash()
    {
        $this->sql->delete(['id' => 1, 'name' => ['Tito', 'Mexican']]);
        $this->assert_sql_has('DELETE FROM authors WHERE id=? AND name IN(?,?)', $this->sql->to_s());
        $this->assertEquals([1, 'Tito', 'Mexican'], $this->sql->get_where_values());
    }

    public function testDeleteWithLimitAndOrder()
    {
        if (!$this->connection->accepts_limit_and_order_for_update_and_delete()) {
            $this->markTestSkipped('Only MySQL & Sqlite accept limit/order with DELETE operation');
        }

        $this->sql->delete(['id' => 1])->order('name asc')->limit(1);
        $this->assert_sql_has('DELETE FROM authors WHERE id=? ORDER BY name asc LIMIT 1', $this->sql->to_s());
    }

    public function testReverseOrder()
    {
        $this->assertEquals('id ASC, name DESC', SQLBuilder::reverse_order('id DESC, name ASC'));
        $this->assertEquals('id ASC, name DESC , zzz ASC', SQLBuilder::reverse_order('id DESC, name ASC , zzz DESC'));
        $this->assertEquals('id DESC, name DESC', SQLBuilder::reverse_order('id, name'));
        $this->assertEquals('id DESC', SQLBuilder::reverse_order('id'));
        $this->assertEquals('', SQLBuilder::reverse_order(''));
        $this->assertEquals(' ', SQLBuilder::reverse_order(' '));
    }

    public function testCreateConditionsFromUnderscoredString()
    {
        $this->assert_conditions('id=? AND name=? OR z=?', [1, 'Tito', 'X'], 'id_and_name_or_z');
        $this->assert_conditions('id=?', [1], 'id');
        $this->assert_conditions('id IN(?)', [[1, 2]], 'id');
    }

    public function testCreateConditionsFromUnderscoredStringWithNulls()
    {
        $this->assert_conditions('id=? AND name IS NULL', [1, null], 'id_and_name');
    }

    public function testCreateConditionsFromUnderscoredStringWithMissingArgs()
    {
        $this->assert_conditions('id=? AND name IS NULL OR z IS NULL', [1, null], 'id_and_name_or_z');
        $this->assert_conditions('id IS NULL', [], 'id');
    }

    public function testCreateConditionsFromUnderscoredStringWithBlank()
    {
        $this->assert_conditions('id=? AND name IS NULL OR z=?', [1, null, ''], 'id_and_name_or_z');
    }

    public function testCreateConditionsFromUnderscoredStringInvalid()
    {
        $this->assertEquals(null, $this->cond_from_s(''));
        $this->assertEquals(null, $this->cond_from_s(null));
    }

    public function testCreateConditionsFromUnderscoredStringWithMappedColumns()
    {
        $this->assert_conditions('id=? AND name=?', [1, 'Tito'], 'id_and_my_name', ['my_name' => 'name']);
    }

    public function testCreateHashFromUnderscoredString()
    {
        $values = [1, 'Tito'];
        $hash = SQLBuilder::create_hash_from_underscored_string('id_and_my_name', $values);
        $this->assertEquals(['id' => 1, 'my_name' => 'Tito'], $hash);
    }

    public function testCreateHashFromUnderscoredStringWithMappedColumns()
    {
        $values = [1, 'Tito'];
        $map = ['my_name' => 'name'];
        $hash = SQLBuilder::create_hash_from_underscored_string('id_and_my_name', $values, $map);
        $this->assertEquals(['id' => 1, 'name' => 'Tito'], $hash);
    }

    public function testWhereWithJoinsPrependsTableNameToFields()
    {
        $joins = 'INNER JOIN books ON (books.id = authors.id)';
        // joins needs to be called prior to where
        $this->sql->joins($joins);
        $this->sql->where([new WhereClause(['id' => 1, 'name' => 'Tito'])]);

        $this->assert_sql_has("SELECT * FROM authors $joins WHERE authors.id=? AND authors.name=?", (string) $this->sql);
    }
}
