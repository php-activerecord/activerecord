<?php

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Exception\ValidationsArgumentError;
use ActiveRecord\Model;
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

    public function testFindReturnsSingleModel()
    {
        $author = Author::select('author_id')->find(3);
        $this->assertInstanceOf(Model::class, $author);

        $author = Author::find('3');
        $this->assertInstanceOf(Model::class, $author);

        $authors = Author::where(['name'=>'Bill Clinton'])->to_a();
        $this->assertIsArray($authors);

        $author = Author::find_by_name('Bill Clinton');
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::first();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::where(['name'=>'Bill Clinton'])->first();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::last();
        $this->assertInstanceOf(Author::class, $author);

        $author = Author::where(['name'=>'Bill Clinton'])->last();
        $this->assertInstanceOf(Author::class, $author);
    }

    public function testFindReturnsArrayOfModels()
    {
        $authors = Author::all()->to_a();
        $this->assertIsArray($authors);

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
        $this->assertTrue(false !== strpos(Author::table()->last_sql, 'ORDER BY name'));
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
        $this->assertTrue(false !== strpos(Author::table()->last_sql, 'ORDER BY name'));
    }

    public function testFindNothingWithSqlInString()
    {
        $this->expectException(TypeError::class);
        Author::first('name = 123123123');
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

    public function testFindAllWithEmptyArrayBindValueThrowsException()
    {
        $this->expectException(DatabaseException::class);
        Author::where(['author_id IN(?)', []])->to_a();
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
        $venues = Venue::where(['marquee' => null])->to_a();
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

    public function testFindFirst()
    {
        $author = Author::where(['author_id IN(?)', [1, 2, 3]])->first();
        $this->assertEquals(1, $author->author_id);
        $this->assertEquals('Tito', $author->name);
    }

    public function testFindFirstNoResults()
    {
        $this->assertNull(Author::where('author_id=1111111')->first());
    }

    public function testFindFirstWithConditionsAsString()
    {
        $author = Author::where('author_id=3')->first();
        $this->assertEquals(3, $author->author_id);
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

    public function testFindWithConditions()
    {
        $author = Author::where(['author_id=? and name=?', 1, 'Tito'])->first();
        $this->assertEquals(1, $author->author_id);
    }

    public function testFindLast()
    {
        $author = Author::last();
        $this->assertEquals(4, $author->author_id);
        $this->assertEquals('Uncle Bob', $author->name);
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

    public function testFindWithSelect()
    {
        $author = Author::select('name, 123 as bubba')
            ->order('name desc')
            ->first();
        $this->assertEquals('Uncle Bob', $author->name);
        $this->assertEquals(123, $author->bubba);
    }

    public function testFindWithSelectNonSelectedFieldsShouldNotHaveAttributes()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::select('name, 123 as bubba')->first();
        $author->id;
        $this->fail('expected ActiveRecord\UndefinedPropertyExecption');
    }

    public function testJoinsOnModelWithAssociationAndExplicitJoins()
    {
        JoinBook::$belongs_to = [
            'author'=>true
        ];
        JoinBook::joins(['author', 'LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)'])->first();
        $this->assert_sql_has('INNER JOIN authors ON(books.author_id = authors.author_id)', JoinBook::table()->last_sql);
        $this->assert_sql_has('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)', JoinBook::table()->last_sql);
    }

    public function testJoinsOnModelWithExplicitJoins()
    {
        JoinBook::joins(['LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)'])->first();
        $this->assert_sql_has('LEFT JOIN authors a ON(books.secondary_author_id=a.author_id)', JoinBook::table()->last_sql);
    }

    public function testGroup()
    {
        $venues = Venue::select('state')->group('state')->to_a();
        $this->assertTrue(count($venues) > 0);
        $this->assert_sql_has('GROUP BY state', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testGroupWithOrderAndLimitAndHaving()
    {
        $venues = Venue::select('state')
            ->group('state')
            ->having('length(state) = 2')
            ->order('state')
            ->limit(2)
            ->to_a();
        $this->assertTrue(count($venues) > 0);
        $this->assert_sql_has($this->connection->limit(
            'SELECT state FROM venues GROUP BY state HAVING length(state) = 2 ORDER BY state', 0, 2), Venue::table()->last_sql);
    }

    public function testFrom()
    {
        $author = Author::from('books')
            ->order('author_id asc')
            ->first();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertNotNull($author->book_id);

        $author = Author::from('authors')
            ->order('author_id asc')
            ->first();
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals(1, $author->id);
    }

    public function testHaving()
    {
        Author::select('date(created_at) as created_at')
           ->group('date(created_at)')
           ->having("date(created_at) > '2009-01-01'")
            ->first();
        $this->assert_sql_has("GROUP BY date(created_at) HAVING date(created_at) > '2009-01-01'", Author::table()->last_sql);
    }

    public function testFromWithInvalidTable()
    {
        $this->expectException(DatabaseException::class);
        Author::from('wrong_authors_table')->first();
    }

    public function testFindWithHash()
    {
        $this->assertNotNull(Author::where(['name' => 'Tito'])->first());
        $this->assertNull(Author::where(['name' => 'Mortimer'])->first());
        $this->assertEquals(1, count(Author::where(['name' => 'Tito'])->to_a()));
    }

    public function testFindOrCreateByOnExistingRecord()
    {
        $this->assertNotNull(Author::find_or_create_by_name('Tito'));
    }

    public function testFindOrCreateByCreatesNewRecord()
    {
        $author = Author::find_or_create_by_name_and_encrypted_password('New Guy', 'pencil');
        $this->assertTrue($author->author_id > 0);
        $this->assertEquals('pencil', $author->encrypted_password);
    }

    public function testFindOrCreateByThrowsExceptionWhenUsingOr()
    {
        $this->expectException(ActiveRecordException::class);
        Author::find_or_create_by_name_or_encrypted_password('New Guy', 'pencil');
    }

    public function testFindByZero()
    {
        $this->expectException(RecordNotFound::class);
        Author::find(0);
    }

    public function testFindByNull()
    {
        $this->expectException(TypeError::class);
        Author::find(null);
    }

    public function testFindByPkShouldNotUseLimit()
    {
        Author::find(1);
        $this->assert_sql_has('SELECT * FROM authors WHERE author_id=?', Author::table()->last_sql);
    }

    public function testFindByDatetime()
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
