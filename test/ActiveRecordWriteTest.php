<?php

use ActiveRecord\ConnectionManager;
use ActiveRecord\DateTime;
use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Table;
use test\models\Author;
use test\models\Book;
use test\models\Course;
use test\models\Venue;

class DirtyAuthor extends ActiveRecord\Model
{
    public static $table = 'authors';
    public static $before_save = 'before_save';

    public function before_save()
    {
        $this->assign_attribute('name', 'i saved');
    }
}

class AuthorWithoutSequence extends ActiveRecord\Model
{
    public static string $table = 'authors';
    public static string $sequence = 'invalid_seq';
}

class AuthorExplicitSequence extends ActiveRecord\Model
{
    public static string $sequence = 'blah_seq';
}

class ActiveRecordWriteTest extends DatabaseTestCase
{
    public function setUp(string $connection_name=null): void
    {
        parent::setUp($connection_name);
        static::resetTableData();
    }

    private function make_new_book_and($save=true)
    {
        $book = new Book();
        $book->name = 'rivers cuomo';
        $book->special = 1;

        if ($save) {
            $book->save();
        }

        return $book;
    }

    public function testSave()
    {
        $this->expectNotToPerformAssertions();
        $venue = new Venue(['name' => 'Tito']);
        $venue->save();
    }

    public function testInsert()
    {
        $author = new Author(['name' => 'Blah Blah']);
        $author->save();
        $this->assertNotNull(Author::find($author->id));
    }

    public function testInsertWithNoSequenceDefined()
    {
        $this->expectException(DatabaseException::class);
        if (!ConnectionManager::get_connection()->supports_sequences()) {
            throw new DatabaseException('');
        }
        AuthorWithoutSequence::create(['name' => 'Bob!']);
    }

    public function testInsertShouldQuoteKeys()
    {
        $author = new Author(['name' => 'Blah Blah']);
        $author->save();
        $this->assertTrue(false !== strpos($author->connection()->last_query, $author->connection()->quote_name('updated_at')));
    }

    public function testSaveAutoIncrementId()
    {
        $venue = new Venue(['name' => 'Bob']);
        $venue->save();
        $this->assertTrue($venue->id > 0);
    }

    public function testFullyQualifiedNameWithExplicitDbName()
    {
        Course::$db = 'test';
        $name = Table::load(Course::class)
            ->get_fully_qualified_table_name();
        $this->assert_sql_includes('`test`.`courses`', $name);
        Course::$db = '';
    }

    public function testSequenceWasExplicitlySet()
    {
        if (ConnectionManager::get_connection()->supports_sequences()) {
            $this->assertEquals(AuthorExplicitSequence::$sequence, Table::load(AuthorExplicitSequence::class)->sequence);
        } else {
            $this->assertNull(Table::load(Author::class)->sequence);
        }
    }

    public function testDelete()
    {
        $author = Author::find(1);
        $author->delete();

        $this->assertFalse(Author::exists(1));
    }

    public function testDeleteByFindAll()
    {
        $books = Book::all()->to_a();

        foreach ($books as $model) {
            $model->delete();
        }

        $res = Book::all()->to_a();
        $this->assertEquals(0, count($res));
    }

    public function testUpdate()
    {
        $book = Book::find(1);
        $new_name = 'new name';
        $book->name = $new_name;
        $book->save();

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function testUpdateShouldQuoteKeys()
    {
        $book = Book::find(1);
        $book->name = 'new name';
        $book->save();
        $this->assertTrue(false !== strpos($book->connection()->last_query, $book->connection()->quote_name('name')));
    }

    public function testUpdateAttributes()
    {
        $book = Book::find(1);
        $new_name = 'How to lose friends and alienate people'; // jax i'm worried about you
        $attrs = ['name' => $new_name];
        $book->update_attributes($attrs);

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function testUpdateAttributesUndefinedProperty()
    {
        $this->expectException(UndefinedPropertyException::class);
        $book = Book::find(1);
        $book->update_attributes(['name' => 'new name', 'invalid_attribute' => true, 'another_invalid_attribute' => 'blah']);
    }

    public function testUpdateAttribute()
    {
        $book = Book::find(1);
        $new_name = 'some stupid self-help book';
        $book->update_attribute('name', $new_name);

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function testUpdateAttributeUndefinedProperty()
    {
        $this->expectException(UndefinedPropertyException::class);
        $book = Book::find(1);
        $book->update_attribute('invalid_attribute', true);
    }

    public function testSaveNullValue()
    {
        $book = Book::first();
        $book->name = null;
        $book->save();
        $this->assertSame(null, Book::find($book->id)->name);
    }

    public function testSaveBlankValue()
    {
        $book = Book::find(1);
        $book->name = '';
        $book->save();
        $this->assertSame('', Book::find(1)->name);
    }

    public function testDirtyAttributes()
    {
        $book = $this->make_new_book_and(false);
        $this->assertEquals(['name', 'special'], array_keys($book->dirty_attributes()));
    }

    public function testIdType()
    {
        $book = new Book();
        $book->save();

        $bookFromFind = Book::find($book->id);

        // both should be ints
        $this->assertSame($book->id, $bookFromFind->id);
    }

    public function testDirtyAttributesClearedAfterSaving()
    {
        $book = $this->make_new_book_and();
        $this->assertTrue(false !== strpos(Table::load(Book::class)->last_sql, 'name'));
        $this->assertTrue(false !== strpos(Table::load(Book::class)->last_sql, 'special'));
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function testDirtyAttributesClearedAfterInserting()
    {
        $book = $this->make_new_book_and();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function testNoDirtyAttributesButStillInsertRecord()
    {
        $book = new Book();
        $this->assertEquals([], $book->dirty_attributes());
        $book->save();
        $this->assertEquals([], $book->dirty_attributes());
        $this->assertNotNull($book->id);
    }

    public function testDirtyAttributesClearedAfterUpdating()
    {
        $book = Book::first();
        $book->name = 'rivers cuomo';
        $book->save();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function testDirtyAttributesAfterReloading()
    {
        $book = Book::first();
        $book->name = 'rivers cuomo';
        $book->reload();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function testDirtyAttributesWithMassAssignment()
    {
        $book = Book::first();
        $book->set_attributes(['name' => 'rivers cuomo']);
        $this->assertEquals(['name'], array_keys($book->dirty_attributes()));
    }

    public function testTimestampsSetBeforeSave()
    {
        $author = new Author();
        $author->save();
        $this->assertNotNull($author->created_at, $author->updated_at);

        $author->reload();
        $this->assertNotNull($author->created_at, $author->updated_at);
    }

    public function testTimestampsUpdatedAtOnlySetBeforeUpdate()
    {
        $author = new Author();
        $author->save();
        $created_at = $author->created_at;
        $updated_at = $author->updated_at;
        sleep(1);

        $author->name = 'test';
        $author->save();

        $this->assertNotNull($author->updated_at);
        $this->assertSame($created_at, $author->created_at);
        $this->assertNotEquals($updated_at, $author->updated_at);
    }

    public function testCreate()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $this->assertNotNull(Author::find($author->id));
    }

    public function testCreateShouldSetCreatedAt()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $this->assertNotNull($author->created_at);
    }

    public function testUpdateWithNoPrimaryKeyDefined()
    {
        $this->expectException(ActiveRecordException::class);
        Table::load(Author::class)->pk = [];
        $author = Author::first();
        $author->name = 'blahhhhhhhhhh';
        $author->save();
    }

    public function testDeleteWithNoPrimaryKeyDefined()
    {
        $this->expectException(ActiveRecordException::class);
        Table::load(Author::class)->pk = [];
        $author = author::first();
        $author->delete();
    }

    public function testInsertingWithExplicitPk()
    {
        $author = Author::create(['author_id' => 9999, 'name' => 'blah']);
        $this->assertEquals(9999, $author->author_id);
    }

    public function testReadonly()
    {
        $this->expectException(ReadOnlyException::class);
        $author = Author::readonly(true)->first();
        $author->save();
    }

    public function testModifiedAttributesInBeforeHandlersGetSaved()
    {
        $author = DirtyAuthor::first();
        $author->encrypted_password = 'coco';
        $author->save();
        $this->assertEquals('i saved', DirtyAuthor::find($author->id)->name);
        $this->assertEquals('coco', DirtyAuthor::find($author->id)->encrypted_password);
    }

    public function testIsDirty()
    {
        $author = Author::first();
        $this->assertEquals(false, $author->is_dirty());

        $author->name = 'coco';
        $this->assertEquals(true, $author->is_dirty());
    }

    public function testSetDateFlagsDirty()
    {
        $author = Author::create(['some_date' => new DateTime()]);
        $author = Author::find($author->id);
        $author->some_date->setDate(2010, 1, 1);
        $this->assertArrayHasKey('some_date', $author->dirty_attributes());
    }

    public function testSetDateFlagsDirtyWithPhpDatetime()
    {
        $author = Author::create(['some_date' => new \DateTime()]);
        $author = Author::find($author->id);
        $author->some_date->setDate(2010, 1, 1);
        $this->assertArrayHasKey('some_date', $author->dirty_attributes());
    }

    public function testDeleteAllWhere()
    {
        $num_affected = Author::where('parent_author_id = 2')->delete_all();
        $this->assertEquals(2, $num_affected);
    }

    public function testDeleteAllWhereWithLimitAndOrder()
    {
        $num_affected = Author::where('parent_author_id = 2')
            ->limit(2)
            ->order('author_id asc')
            ->delete_all();
        $this->assertEquals(2, $num_affected);
    }

    public function testDeleteAll()
    {
        $num_affected = Author::delete_all();
        $this->assertEquals(5, $num_affected);
    }

    public function testDeleteAllWithDistinct()
    {
        $this->expectException(ActiveRecordException::class);
        Author::distinct()->where('parent_author_id = 2')->delete_all();
    }

    public function testUpdateAllWithString()
    {
        Author::update_all('parent_author_id = 2');
        $ids = Author::pluck('parent_author_id');
        foreach ($ids as $id) {
            $this->assertEquals(2, $id);
        }
    }

    public function testUpdateAllWithHash()
    {
        Author::update_all(['parent_author_id' => 2]);
        $ids = Author::pluck('parent_author_id');
        foreach ($ids as $id) {
            $this->assertEquals(2, $id);
        }
    }

    /**
     * TODO: not implemented
     */
    public function testUpdateAllWithConditionsAsString()
    {
        $num_affected = Author::where(['name = ?', 'Tito'])->update_all('parent_author_id = 2');
        $this->assertEquals(2, $num_affected);
    }

    public function testUpdateAllWithConditionsAsHash()
    {
        $num_affected = Author::where([
            'name' => 'Tito'
        ])->update_all('parent_author_id = 2');
        $this->assertEquals(2, $num_affected);
    }

    public function testUpdateAllWithConditionsAsArray()
    {
        $num_affected = Author::where(['name = ?', 'Tito'])->update_all('parent_author_id = 2');
        $this->assertEquals(2, $num_affected);
    }

    public function testUpdateAllWithLimitAndOrder()
    {
        if (!ConnectionManager::get_connection()->accepts_limit_and_order_for_update_and_delete()) {
            $this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE clause');
        }

        $num_affected = Author::limit(1)
            ->order('name asc')
            ->update_all('parent_author_id = 2');

        $this->assertEquals(1, $num_affected);
        $this->assertTrue(false !== strpos(Table::load(Author::class)->last_sql, 'ORDER BY name asc LIMIT 1'));
    }

    public function testUpdateNativeDatetime()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $native_datetime = new \DateTime('1983-12-05');
        $author->some_date = $native_datetime;
        $this->assertFalse($native_datetime === $author->some_date);
    }

    public function testUpdateOurDatetime()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $our_datetime = new DateTime('1983-12-05');
        $author->some_date = $our_datetime;
        $this->assertTrue($our_datetime === $author->some_date);
    }
}
