<?php

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Exception\ValidationsArgumentError;
use ActiveRecord\Relation;
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
        $author = Author::last();
        $this->assertInstanceOf(Author::class, $author);

        $author2 = Author::last(1);
        $this->assertIsArray($author2);
        $this->assertEquals($author2[0], $author);

        $author3 = Author::all()->last;
        $this->assertInstanceOf(Author::class, $author3);
        $this->assertEquals($author3, $author);

        $authors = Author::all()->to_a();
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

    public function testLastNull()
    {
        $query = Author::where(['mixedCaseField' => 'Does not exist'])->last();
        $this->assertEquals(null, $query);
    }

    public function testMagicGetters()
    {
        $author = Author::first();
        $author2 = Author::all()->first;
        $this->assertEquals($author, $author2);

        $author = Author::last();
        $author2 = Author::all()->last;
        $this->assertEquals($author, $author2);

        $author = Author::take();
        $author2 = Author::all()->take;
        $this->assertEquals($author, $author2);
    }

    public function testMagicGettersUndefinedFunction()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::all()->functionDoesNotExist;
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

    public function testSelect()
    {
        $relation = Author::select('name')->where("mixedCaseField = 'Bill'")->last;
        $this->assertEquals('Uncle Bob', $relation->name);

        $this->expectException(UndefinedPropertyException::class);
        $columnNotInSelectList = $relation->parent_author_id;
    }

    public function testSelectChainedSelects()
    {
        $relation = Author::select('name,name')->select('author_id');
        $options = $this->getPrivateVariable($relation, 'options');
        $this->assertEquals(['name', 'author_id'], $options['select']);

        $relation = Author::select('name')->select('*');
        $options = $this->getPrivateVariable($relation, 'options');
        $this->assertEquals(['*'], $options['select']);
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

    public function testModelToRelation()
    {
        $this->assertInstanceOf(Relation::class, Author::offset(0));
        $this->assertInstanceOf(Relation::class, Author::group('name'));
        $this->assertInstanceOf(Relation::class, Author::having('length(name) > 2'));
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
