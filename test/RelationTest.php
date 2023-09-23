<?php

use ActiveRecord\Exception\ValidationsArgumentError;
use ActiveRecord\Relation;
use test\models\Author;

class RelationTest extends DatabaseTestCase
{
    public function testWhereString()
    {
        $models = Author::where("mixedCaseField = 'Bill'")->to_a();
        $this->assertEquals(2, count($models));
        $this->assertEquals('Bill Clinton', $models[0]->name);
        $this->assertEquals('Uncle Bob', $models[1]->name);
    }

    public function testWhereTooManyArguments()
    {
        $models = Author::where('mixedCaseField = ?', 'Bill')->to_a();
        $this->assertEquals(2, count($models));
        $this->assertEquals('Bill Clinton', $models[0]->name);
        $this->assertEquals('Uncle Bob', $models[1]->name);
    }

    public function testWhereArray()
    {
        $authors = Author::where(['name = ?', 'Bill Clinton'])->to_a();
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]->name);
    }

    public function testWhereHash()
    {
        $authors = Author::where(['name' => 'Bill Clinton'])->to_a();
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]->name);
    }

    public function testWhereOrder()
    {
        $relation = Author::select('name')->where("mixedCaseField = 'Bill'");

        $authors = $relation->last(1);
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Uncle Bob', $authors[0]->name);

        $authors = $relation->last(2);
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Uncle Bob', $authors[0]->name);
        $this->assertEquals('Bill Clinton', $authors[1]->name);

        $queries = Author::order('parent_author_id DESC')->where(['mixedCaseField'=>'Bill'])->to_a();
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Uncle Bob', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);
    }

    public function testWhereAnd()
    {
        $authors = Author::select('name')
            ->where(['mixedCaseField'=>'Bill', 'parent_author_id'=>1])
            ->to_a();
        $this->assertEquals('Bill Clinton', $authors[0]->name);

        $authors = Author::select('name')
            ->where([
                'mixedCaseField'=>'Bill',
                'parent_author_id'=>2]
            )->to_a();
        $this->assertEquals('Uncle Bob', $authors[0]->name);

        $authors = Author::select('name')
            ->where([
                'mixedCaseField = (?) and parent_author_id <> (?)',
                'Bill',
                1])
            ->to_a();
        $this->assertEquals('Uncle Bob', $authors[0]->name);

        $authors = Author::select('name')
            ->where(['mixedCaseField = (?)', 'Bill'])
            ->where(['parent_author_id = (?)', 1])
            ->where("author_id = '3'")
            ->where(['mixedCaseField'=>'Bill', 'name'=>'Bill Clinton'])
            ->to_a();
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]->name);
    }

    public function testWhereChained()
    {
        $model = Author::select('name')
            ->where(['name' => 'Bill Clinton'])
            ->where(['mixedCaseField' => 'Bill'])
            ->find(3);
        $this->assertEquals('Bill Clinton', $model->name);
    }

    public function testReverseOrder()
    {
        $relation = Author::where(['mixedCaseField' => 'Bill']);

        $authors = $relation->to_a();
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]->name);
        $this->assertEquals('Uncle Bob', $authors[1]->name);

        $authors = $relation->reverse_order();
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Uncle Bob', $authors[0]->name);
        $this->assertEquals('Bill Clinton', $authors[1]->name);

        $authors = $relation->reverse_order();
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Bill Clinton', $authors[0]->name);
        $this->assertEquals('Uncle Bob', $authors[1]->name);
    }

    public function testAllNoParameters()
    {
        $authors = Author::all()->to_a();
        $this->assertEquals(5, count($authors));
    }

    public function testCanIterate()
    {
        $authors = Author::all();

        foreach ($authors as $key => $author) {
            $this->assertInstanceOf(Author::class, $author);
        }

        foreach ($authors as $author) {
            $this->assertInstanceOf(Author::class, $author);
        }
    }

    public function testAllPrimaryKeys()
    {
        static::resetTableData();
        $rel = Author::all();
        $queries = $rel->find([1, 3]);
        $this->assertEquals(2, count($queries));
        $this->assertEquals('Tito', $queries[0]->name);
        $this->assertEquals('Bill Clinton', $queries[1]->name);
    }

    public function testAllAnd()
    {
        $queries = Author::all()->where(['mixedCaseField'=>'Bill', 'parent_author_id'=>1])->to_a();
        $this->assertEquals(1, count($queries));
        $this->assertEquals('Bill Clinton', $queries[0]->name);

        $authors = Author::all()->where([
            'mixedCaseField'=>'Bill',
            'parent_author_id'=>2]
        )->to_a();
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Uncle Bob', $authors[0]->name);

        $authors = Author::all()
            ->where([
                'mixedCaseField = (?) and parent_author_id <> (?)', 'Bill',
                1
            ])
            ->to_a();
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Uncle Bob', $authors[0]->name);
    }

    public function testModelToRelation(): void
    {
        $this->assertInstanceOf(Relation::class, Author::offset(0));
        $this->assertInstanceOf(Relation::class, Author::group('name'));
        $this->assertInstanceOf(Relation::class, Author::having('length(name) > 2'));
    }

    public function testToSql(): void
    {
        $this->assert_sql_includes(
            'SELECT * FROM `books` WHERE name = ? ORDER BY name',
            \test\models\Book::where('name = ?', 'The Art of Main Tanking')
                ->order('name')->to_sql()
        );
    }

    public function testGroupRequiredWhenUsingHaving()
    {
        $this->expectException(ValidationsArgumentError::class);
        Author::select('name')
            ->order('name DESC')
            ->limit(2)
            ->offset(2)
            ->having('length(name) = 2')
            ->from('books')
            ->readonly(true)
            ->to_a([3]);
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
            ->to_a([3]);
        $this->assertEquals(0, count($queries));
    }
}
