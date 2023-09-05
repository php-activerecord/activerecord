<?php

use test\models\Author;

class SQLExecutionPlanTest extends DatabaseTestCase
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

        $sqlPlan = Author::select('name');

        $query = $sqlPlan->last->where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = $sqlPlan->last(1)->where(['mixedCaseField'=>'Bill']);
        $this->assertEquals('Uncle Bob', $query->name);

        $query = $sqlPlan->last(1)->last(2)->where(['mixedCaseField'=>'Bill']);
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
        $sqlPlan = Author::select('authors.author_id, authors.name');
        $sqlPlan = Author::join('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)');
        $sqlPlan = Author::orderBy('name DESC');
        $sqlPlan = Author::limit(2);
        $sqlPlan = Author::groupBy('name');
        $sqlPlan = Author::offset(2);
        $query = $sqlPlan->where(3);

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
        $sqlPlan = Author::select('name');

        $queries = $sqlPlan->last(2)->all(['mixedCaseField'=>'Bill']);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);

        $queries = $sqlPlan->last(2)->last(1)->all(['mixedCaseField'=>'Bill']);
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
