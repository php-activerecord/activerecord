<?php

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\ValidationsArgumentError;
use test\models\Author;

class RelationTest extends DatabaseTestCase
{
    public function testFind()
    {
        $rel = Author::all();

        $author = $rel->find(1);
        $this->assertEquals('Tito', $author->name);
        $this->assertEquals(['sharks' => 'lasers'], $author->return_something());

        $author = $rel->find('1');
        $this->assertEquals('Tito', $author->name);
        $authors = $rel->find([1, 2]);
        $this->assertEquals(2, count($authors));
        $this->assertEquals('Tito', $authors[0]->name);
        $this->assertEquals('George W. Bush', $authors[1]->name);

        $authors = $rel->find([1]);
        $this->assertEquals(1, count($authors));
        $this->assertEquals('Tito', $authors[0]->name);
    }

    public function testFirst()
    {
        $rel = Author::first();
        $this->assertInstanceOf(Author::class, $rel);
        $this->assertEquals(1, $rel->author_id);
    }

    public function testFirstWithCount()
    {
        $authors = Author::first(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
    }

    public function testFirstWithCountOverridesLimit()
    {
        $authors = Author::limit(1)->first(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
    }

    public function testLast()
    {
        $authors = Author::all()->to_a();
        $author = Author::last();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals($author, $authors[count($authors)-1]);
    }

    public function testLastWithCount()
    {
        $allAuthors = Author::all()->to_a();
        $authors = Author::last(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
        $this->assertEquals($allAuthors[count($allAuthors)-1], $authors[0]);
        $this->assertEquals($allAuthors[count($allAuthors)-2], $authors[1]);
    }

    public function testLastWithCountOverridesLimit()
    {
        $authors = Author::limit(1)->last(2);
        $this->assertIsArray($authors);
        $this->assertEquals(2, count($authors));
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

    public function testWhereString()
    {
        $models = Author::where("mixedCaseField = 'Bill'")->to_a();
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

    public function testAllNoParameters()
    {
        $authors = Author::all()->to_a();
        $this->assertEquals(4, count($authors));
    }

    public function testAllPrimaryKeys()
    {
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
