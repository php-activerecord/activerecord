<?php

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\ValidationsArgumentError;
use test\models\Author;

class RelationTest extends DatabaseTestCase
{
    public function testFindPrimaryKey()
    {
        $rel = Author::select('name');

        $query = $rel->find(1);
        $this->assertEquals('Tito', $query->name);
        $this->assertEquals(['sharks' => 'lasers'], $query->return_something());

        $query = $rel->find('1');
        $this->assertEquals('Tito', $query->name);
        $queries = $rel->find([1, 2]);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Tito', $queries[0]->name);
        $this->assertEquals('George W. Bush', $queries[1]->name);

        $queries = $rel->find([1]);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Tito', $queries[0]->name);
    }

    public function testFindSingleArrayElementNotFound()
    {
        $this->expectException(RecordNotFound::class);
        $queries = Author::select('name')->find([999999]);
    }

    public function testFindNotAllArrayElementsFound()
    {
        $this->expectException(RecordNotFound::class);
        $queries = Author::select('name')->find([1, 999999]);
    }

    public function testFindWrongType()
    {
        $this->expectException(TypeError::class);
        Author::select('name')->find('not a number');
    }

    public function testFindSingleElementNotFound()
    {
        $this->expectException(RecordNotFound::class);
        $query = Author::select('name')->find(99999);
    }

    public function testFindNoWhere()
    {
        $this->expectException(ValidationsArgumentError::class);
        $query = Author::select('name')->find();
    }

    public function testWhere()
    {
//        $models = Author::where("mixedCaseField = 'Bill'")->to_a();
//        $this->assertEquals(2, count($models));
//        $this->assertEquals('Bill Clinton', $models[0]->name);
//        $this->assertEquals('Uncle Bob', $models[1]->name);

        $queries = Author::select('name')->where(['name = (?)', 'Bill Clinton'])->to_a();
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);
        $queries = Author::select('name')->where(['name = (?)', 'Not found'])->to_a();
        $this->assertEquals(0, count($queries));

        $queries = Author::select('name')->where(['mixedCaseField'=>'Bill'])->to_a();
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);
        $this->assertEquals('Uncle Bob', $queries[1]->name);
    }

    public function testWhereWithPrimaryKey()
    {
        $rel = Author::select('name')->where(["mixedCaseField = 'Bill'"]);
        $queries = $rel->find([3, 4]);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);
        $this->assertEquals('Uncle Bob', $queries[1]->name);

        $queries = $rel->find([4, 999999]);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = $rel->find([999998, 999999]);
        $this->assertEquals(0, count($queries));

        $query = $rel->find(999999);
        $this->assertNull($query);

        $query = $rel->find(4);
        $this->assertEquals('Uncle Bob', $query->name);
    }

    public function testWhereOrder()
    {
        $relation = Author::select('name')->where("mixedCaseField = 'Bill'");

        $queries = $relation->last->find;
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = $relation->last(1)->find;
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = $relation->last(2)->find;
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);

        $queries = $relation->last(1)->last(2)->find;
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);

        $queries = Author::order('parent_author_id DESC')->where(['mixedCaseField'=>'Bill'])->find;
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);
    }

    public function testWhereAnd()
    {
        $queries = Author::select('name')->where(['mixedCaseField'=>'Bill', 'parent_author_id'=>1])->find;
        $this->assertEquals('Bill Clinton', $queries[0]->name);

        $queries = Author::select('name')->where(['mixedCaseField'=>'Bill', 'parent_author_id'=>2])->find;
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = Author::select('name')->where(['mixedCaseField = (?) and parent_author_id <> (?)', 'Bill', 1])->find;
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = Author::select('name')
            ->where(['mixedCaseField = (?)', 'Bill'])
            ->where(['parent_author_id = (?)', 1])
            ->where("author_id = '3'")
            ->where(['mixedCaseField'=>'Bill', 'name'=>'Bill Clinton'])
            ->find;
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);
    }

    public function testWhereChained()
    {
        $relation = Author::select('authors.author_id, authors.name');
        $relation = Author::joins(['LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)']);
        $relation = Author::order('name DESC');
        $relation = Author::limit(2);
        $relation = Author::group('name');
        $relation = Author::offset(2);
        $query = $relation->where(3);

        $query = Author::select('name')
            ->order('name DESC')
            ->limit(2)
            ->group('name')
            ->offset(2)
            ->having('length(name) = 2')
            ->readonly(true)
            ->from('books')
            ->where(['name' => 'Bill Clinton'])
            ->find(3);
        $this->assertEquals(null, $query);
    }

    public function testAllNoParameters()
    {
        $queries = Author::all();
        $this->assertEquals(4, count($queries));
    }

    public function testAllPrimaryKeys()
    {
        $queries = Author::all([1, 3]);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Tito', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);
    }

    public function testAllAnd()
    {
        $queries = Author::all(['mixedCaseField'=>'Bill', 'parent_author_id'=>1]);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);

        $queries = Author::all(['mixedCaseField'=>'Bill', 'parent_author_id'=>2]);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);

        $queries = Author::all(['mixedCaseField = (?) and parent_author_id <> (?)', 'Bill', 1]);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
    }

    public function testAllLast()
    {
        $relation = Author::select('name');

        $models = $relation->last(2)->to_a();
        $this->assertEquals(2, count($models));
        $this->assertEquals('Uncle Bob', $models[0]->name);
        $this->assertEquals('Bill Clinton', $models[1]->name);

        $models = $relation->last(2)->last(1)->where(['mixedCaseField'=>'Bill'])->to_a();
        $this->assertEquals(1, count($models));
        $this->assertEquals('Uncle Bob', $models[0]->name);
    }

    public function testAllChained()
    {
        $queries = Author::select('name')
            ->order('name DESC')
            ->limit(2)
            ->group('name')
            ->offset(2)
            ->having('length(name) = 2')
            ->from('books')
            ->readonly(true)
            ->all([3]);
        $this->assertEquals(0, count($queries));
    }
}
