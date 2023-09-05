<?php

use test\models\Author;

class RelationTest extends DatabaseTestCase
{
    public function testWherePrimaryKey()
    {
        $query = Author::where(3);
        $this->assertEquals('Bill Clinton', $query->name);
        $this->assertEquals(['sharks' => 'lasers'], $query->return_something());
    }

    public function testWhereNull()
    {
        $query = Author::where(99999);
        $this->assertEquals(null, $query);
    }

    public function testWhereOrder()
    {
        $query = Author::where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Bill Clinton', $query->name);

        $relation = Author::select('name');

        $query = $relation->last->where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = $relation->last(1)->where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = $relation->last(1)->last(2)->where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = Author::orderBy('parent_author_id DESC')->where(['mixedCaseField'=>'Bill'], false);
        $this->assertEquals('Uncle Bob', $query->name);
    }

    public function testWhereAnd()
    {
        $query = Author::where(['mixedCaseField'=>'Bill', 'parent_author_id'=>1]);
        $this->assertEquals('Bill Clinton', $query->name);

        $query = Author::where(['mixedCaseField'=>'Bill', 'parent_author_id'=>2]);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = Author::where(['mixedCaseField = (?) and parent_author_id <> (?)', 'Bill', 1]);
        $this->assertEquals('Uncle Bob', $query->name);
    }

    public function testWhereChained()
    {
        $relation = Author::select('authors.author_id, authors.name');
        $relation = Author::join('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)');
        $relation = Author::orderBy('name DESC');
        $relation = Author::limit(2);
        $relation = Author::groupBy('name');
        $relation = Author::offset(2);
        $query = $relation->where(3);

        $query = Author::select('authors.author_id, authors.name')
            ->orderBy('name DESC')
            ->limit(2)
            ->groupBy('name')
            ->offset(2)
            ->having('length(name) = 2')
            ->readonly(true)
            ->where(3);
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

        $queries = $relation->last(2)->all(['mixedCaseField'=>'Bill']);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);

        $queries = $relation->last(2)->last(1)->all(['mixedCaseField'=>'Bill']);
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
    }

    public function testAllChained()
    {
        $queries = Author::select('authors.author_id, authors.name')
            ->orderBy('name DESC')
            ->limit(2)
            ->groupBy('name')
            ->offset(2)
            ->having('length(name) = 2')
            ->readonly(true)
            ->all([3]);
        $this->assertEquals(0, count($queries));
    }
}
