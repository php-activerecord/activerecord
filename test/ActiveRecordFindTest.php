<?php

use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\ValidationsArgumentError;
use ActiveRecord\Model;
use ActiveRecord\Table;
use test\models\Author;
use test\models\HonestLawyer;
use test\models\JoinBook;
use test\models\Venue;

class ActiveRecordFindTest extends DatabaseTestCase
{
    public function testFindWithNoParams()
    {
        $this->expectException(ValidationsArgumentError::class);
        Author::find();
    }

    public function testWhereWithEmptyArray()
    {
        $authors = Author::where(['author_id' => []])->to_a();
        $this->assertEquals(0, count($authors));
    }

    public function testFindWithEmptyArray()
    {
        $this->expectException(RecordNotFound::class);
        $this->expectExceptionMessage("Couldn't find test\models\Author without an ID");
        Author::find([]);
    }

    public function testFindReturnsSingleModel()
    {
        $author = Author::select('author_id')->find(3);
        $this->assertInstanceOf(Model::class, $author);

        $author = Author::find('3');
        $this->assertInstanceOf(Model::class, $author);

        $author = Author::first();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::where(['name'=>'Bill Clinton'])->first();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::last();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::where(['name'=>'Bill Clinton'])->last();
        $this->assertInstanceOf(Author::class, $author);
    }

    public function testFindReturnContents()
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

    public function testFindReturnsArrayOfModels()
    {
        $this->assertEquals(2, count(Author::where(['name' => 'Tito'])->to_a()));

        $authors = Author::find(1, 2, 3);
        $this->assertIsArray($authors);

        $authors = Author::find([1, 2, 3]);
        $this->assertIsArray($authors);
    }

    public function testFindReturnsNull()
    {
        $lawyer = HonestLawyer::first();
        $this->assertNull($lawyer);

        $lawyer = HonestLawyer::last();
        $this->assertNull($lawyer);

        $lawyer = HonestLawyer::where(['name'=>'Abe'])->first();
        $this->assertNull($lawyer);

        $lawyer = HonestLawyer::where(['name'=>'Abe'])->last();
        $this->assertNull($lawyer);
    }

    public static function noReturnValues(): array
    {
        return [
            [
                -1,
                null
            ]
        ];
    }

    /**
     * @dataProvider noReturnValues
     */
    public function testFindDoesntReturn($badValue)
    {
        $this->expectException(RecordNotFound::class);
        Author::find($badValue);
    }

    public function testFindByPk()
    {
        $author = Author::find(3);
        $this->assertEquals(3, $author->id);
    }

    public function testFindByPknoResults()
    {
        $this->expectException(RecordNotFound::class);
        Author::find(99999999);
    }

    public function testFindByMultiplePkWithPartialMatch()
    {
        try {
            Author::find(1, 999999999);
            $this->fail();
        } catch (RecordNotFound $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'found 1, but was looking for 2'));
        }
    }

    public function testFindByPkWithOptions()
    {
        $author = Author::order('name')->find(3);
        $this->assertEquals(3, $author->id);
        $this->assertTrue(false !== strpos(Table::load(Author::class)->last_sql, 'ORDER BY name'));
    }

    public function testFindByPkArray()
    {
        $authors = Author::find(1, '2');
        $this->assertEquals(2, count($authors));
        $this->assertEquals(1, $authors[0]->id);
        $this->assertEquals(2, $authors[1]->id);
    }

    public function testFindByPkArrayWithOptions()
    {
        $authors = Author::order('name')->find(1, '2');
        $this->assertEquals(2, count($authors));
        $this->assertTrue(false !== strpos(Table::load(Author::class)->last_sql, 'ORDER BY name'));
    }

    public function testFindAll()
    {
        $authors = Author::where(['author_id IN(?)', [1, 2, 3]])->to_a();
        $this->assertTrue(count($authors) >= 3);
    }

    public function testFindAllWithNoBindValues()
    {
        $authors = Author::where('author_id IN(1,2,3)')->to_a();
        $this->assertEquals(1, $authors[0]->author_id);
    }

    public function testFindHashUsingAlias()
    {
        $venues = Venue::where(['marquee' => 'Warner Theatre', 'city' => [
            'Washington',
            'New York'
        ]])->to_a();
        $this->assertTrue(count($venues) >= 1);
    }

    public function testFindHashUsingAliasWithNull()
    {
        $venues = Venue::where(['name' => null])->to_a();
        $this->assertEquals(0, count($venues));
    }

    public function testFindAllHash()
    {
        $books = \test\models\Book::all()->where(['author_id' => 1])->to_a();
        $this->assertTrue(count($books) > 0);
    }

    public function testFindAllHashWithOrder()
    {
        $books = \test\models\Book::order('name DESC')->where(['author_id' => 1])->to_a();
        $this->assertTrue(count($books) > 0);
    }

    public function testFindAllNoArgs()
    {
        $authors = Author::all()->to_a();
        $this->assertTrue(count($authors) > 1);
    }

    public function testFindAllNoResults()
    {
        $authors = Author::where('author_id IN(11111111111,22222222222,333333333333)')->to_a();
        $this->assertEquals([], $authors);
    }

    public function testFindAllWithConditionsAsString()
    {
        $author = Author::all()->where('author_id in(2,3)')->to_a();
        $this->assertEquals(2, count($author));
    }

    public function testFindBySql()
    {
        $author = Author::find_by_sql('SELECT * FROM authors WHERE author_id in(1,2)');
        $this->assertEquals(1, $author[0]->author_id);
        $this->assertEquals(2, count($author));
    }

    public function testFindBySqltakesValuesArray()
    {
        $author = Author::find_by_sql('SELECT * FROM authors WHERE author_id=?', [1]);
        $this->assertNotNull($author);
    }

    public function testFindLast()
    {
        static::resetTableData();

        $author = Author::last();
        $this->assertEquals(5, $author->author_id);
        $this->assertEquals('Tito', $author->name);
    }

    public function testFindLastUsingStringCondition()
    {
        $author = Author::where('author_id IN(1,2,3,4)')->last();
        $this->assertEquals(4, $author->author_id);
        $this->assertEquals('Uncle Bob', $author->name);
    }

    public function testLimitBeforeOrder()
    {
        $authors = Author::limit(2)->order('author_id desc')->where(['author_id in(1,2)'])->to_a();
        $this->assertEquals(2, $authors[0]->author_id);
        $this->assertEquals(1, $authors[1]->author_id);
    }

    public function testForEach()
    {
        $i = 0;
        $res = Author::all()->to_a();

        foreach ($res as $author) {
            $this->assertTrue($author instanceof ActiveRecord\Model);
            ++$i;
        }
        $this->assertTrue($i > 0);
    }

    public function testFetchAll()
    {
        $i = 0;

        foreach (Author::all()->to_a() as $author) {
            $this->assertTrue($author instanceof ActiveRecord\Model);
            ++$i;
        }
        $this->assertTrue($i > 0);
    }

    public function testJoinsOnModelWithAssociationAndExplicitJoins()
    {
        JoinBook::$belongs_to = [
            'author'=>true
        ];
        JoinBook::joins(['author', 'LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)'])->first();
        $this->assert_sql_includes('INNER JOIN authors ON(books.author_id = authors.author_id)', Table::load(JoinBook::class)->last_sql);
        $this->assert_sql_includes('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)', Table::load(JoinBook::class)->last_sql);
    }

    public function testJoinsOnModelWithExplicitJoins()
    {
        JoinBook::joins(['LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)'])->first();
        $this->assert_sql_includes('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)', Table::load(JoinBook::class)->last_sql);
    }

    public function testFindNonExistentPrimaryKey()
    {
        $this->expectException(RecordNotFound::class);
        Author::find(0);
    }

    public function testFindWrongType()
    {
        $this->expectException(TypeError::class);
        Author::select('name')->find('not a number');
    }

    public function testFindByNull()
    {
        $this->expectException(TypeError::class);
        Author::find(null);
    }

    public function testFindByPkShouldNotUseLimit()
    {
        Author::find(1);
        $this->assert_sql_includes('SELECT * FROM authors WHERE author_id = ?', Table::load(Author::class)->last_sql);
    }

    public function testFindsDatetime()
    {
        $now = new DateTime();
        $arnow = new ActiveRecord\DateTime();
        $arnow->setTimestamp($now->getTimestamp());

        $author = Author::find(1);
        $author->update_attribute('created_at', $now);
        $this->assertNotNull(Author::find_by_created_at($now));
        $this->assertNotNull(Author::find_by_created_at($arnow));
    }
}
