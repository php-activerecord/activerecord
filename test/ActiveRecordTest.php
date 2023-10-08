<?php

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\RelationshipException;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Table;
use test\models\Author;
use test\models\AwesomePerson;
use test\models\Book;
use test\models\BookAttrAccessible;
use test\models\BookAttrProtected;
use test\models\Event;
use test\models\RmBldg;
use test\models\Venue;

class ActiveRecordTest extends DatabaseTestCase
{
    public function testInvalidAttribute()
    {
        $this->expectException(UndefinedPropertyException::class);
        $author = Author::where('author_id=1')->first();
        $author->some_invalid_field_name;
    }

    public function testInvalidAttributes()
    {
        $book = Book::find(1);
        try {
            $book->update_attributes(['name' => 'new name', 'invalid_attribute' => true, 'another_invalid_attribute' => 'something']);
        } catch (UndefinedPropertyException $e) {
            $exceptions = explode("\r\n", $e->getMessage());
        }

        $this->assertEquals(1, substr_count($exceptions[0], 'invalid_attribute'));
        $this->assertEquals(1, substr_count($exceptions[1], 'another_invalid_attribute'));
    }

    public function testGetterUndefinedPropertyExceptionIncludesModelName()
    {
        $this->assert_exception_message_contains('Author->this_better_not_exist', function () {
            $author = new Author();
            $author->this_better_not_exist;
        });
    }

    public function testUnknownRelationship()
    {
        $this->expectException(RelationshipException::class);
        $author = new Author();
        $author->set_relationship_from_eager_load(null, 'unknown');
    }

    public function testMassAssignmentUndefinedPropertyExceptionIncludesModelName()
    {
        $this->assert_exception_message_contains('Author->this_better_not_exist', function () {
            new Author(['this_better_not_exist' => 'hi']);
        });
    }

    public function testSetterUndefinedPropertyExceptionIncludesModelName()
    {
        $this->assert_exception_message_contains('Author->this_better_not_exist', function () {
            $author = new Author();
            $author->this_better_not_exist = 'hi';
        });
    }

    public function testGetValuesFor()
    {
        $books = Book::find_by_name('Ancient Art of Main Tanking');
        $ret = $books->get_values_for(['book_id', 'author_id']);
        $this->assertEquals(['book_id', 'author_id'], array_keys($ret));
        $this->assertEquals([1, 1], array_values($ret));
    }

    public function testHyphenatedColumnNamesToUnderscore()
    {
        $keys = array_keys(RmBldg::first()->attributes());
        $this->assertTrue(in_array('rm_name', $keys));
    }

    public function testColumnNamesWithSpaces()
    {
        $keys = array_keys(RmBldg::first()->attributes());
        $this->assertTrue(in_array('space_out', $keys));
    }

    public function testMixedCaseColumnName()
    {
        $keys = array_keys(Author::first()->attributes());
        $this->assertTrue(in_array('mixedcasefield', $keys));
    }

    public function testMixedCasePrimaryKeySave()
    {
        $venue = Venue::find(1);
        $venue->name = 'should not throw exception';
        $venue->save();
        $this->assertEquals($venue->name, Venue::find(1)->name);
    }

    public function testReload()
    {
        $venue = Venue::find(1);
        $this->assertEquals('NY', $venue->state);
        $venue->state = 'VA';
        $this->assertEquals('VA', $venue->state);
        $venue->reload();
        $this->assertEquals('NY', $venue->state);
    }

    public function testReloadProtectedAttribute()
    {
        $book = BookAttrAccessible::find(1);

        $book->name = 'Should not stay';
        $book->reload();
        $this->assertNotEquals('Should not stay', $book->name);
    }

    public function testNamespaceGetsStrippedFromTableName()
    {
        new \test\models\namespacetest\Book();
        $this->assertEquals('books', Table::load(\test\models\namespacetest\Book::class)->table);
    }

    public function testNamespaceGetsStrippedFromInferredForeignKey()
    {
        $model = new \test\models\namespacetest\Book();
        $table = ActiveRecord\Table::load(get_class($model));

        $this->assertEquals($table->get_relationship('parent_book')->foreign_key[0], 'book_id');
        $this->assertEquals($table->get_relationship('parent_book_2')->foreign_key[0], 'book_id');
        $this->assertEquals($table->get_relationship('parent_book_3')->foreign_key[0], 'book_id');
    }

    public function testNamespacedRelationshipAssociatesCorrectly()
    {
        $model = new \test\models\namespacetest\Book();
        $table = ActiveRecord\Table::load(get_class($model));

        $this->assertNotNull($table->get_relationship('parent_book'));
        $this->assertNotNull($table->get_relationship('parent_book_2'));
        $this->assertNotNull($table->get_relationship('parent_book_3'));

        $this->assertNotNull($table->get_relationship('pages'));
        $this->assertNotNull($table->get_relationship('pages_2'));

        $this->assertNull($table->get_relationship('parent_book_4'));
        $this->assertNull($table->get_relationship('pages_3'));

        // Should refer to the same class
        $this->assertSame(
            ltrim($table->get_relationship('parent_book')->class_name, '\\'),
            ltrim($table->get_relationship('parent_book_2')->class_name, '\\')
        );

        // Should refer to different classes
        $this->assertNotSame(
            ltrim($table->get_relationship('parent_book_2')->class_name, '\\'),
            ltrim($table->get_relationship('parent_book_3')->class_name, '\\')
        );

        // Should refer to the same class
        $this->assertSame(
            ltrim($table->get_relationship('pages')->class_name, '\\'),
            ltrim($table->get_relationship('pages_2')->class_name, '\\')
        );
    }

    public function testShouldHaveAllColumnAttributesWhenInitializingWithArray()
    {
        $author = new Author(['name' => 'Tito']);
        $this->assertTrue(count(array_keys($author->attributes())) >= 9);
    }

    public function testDefaults()
    {
        $author = new Author();
        $this->assertEquals('default_name', $author->name);
    }

    public function testAliasAttributeGetter()
    {
        $venue = Venue::find(1);
        $this->assertEquals($venue->marquee, $venue->name);
        $this->assertEquals($venue->mycity, $venue->city);
    }

    public function testAliasAttributeSetter()
    {
        $venue = Venue::find(1);
        $venue->marquee = 'new name';
        $this->assertEquals($venue->marquee, 'new name');
        $this->assertEquals($venue->marquee, $venue->name);

        $venue->name = 'another name';
        $this->assertEquals($venue->name, 'another name');
        $this->assertEquals($venue->marquee, $venue->name);
    }

    public function testAliasFromMassAttributes()
    {
        $venue = new Venue(['marquee' => 'meme', 'id' => 123]);
        $this->assertEquals('meme', $venue->name);
        $this->assertEquals($venue->marquee, $venue->name);
    }

    public function testGh18IssetOnAliasedAttribute()
    {
        $this->assertTrue(isset(Venue::first()->marquee));
    }

    public function testAttrAccessible()
    {
        $book = new BookAttrAccessible(['name' => 'should not be set', 'author_id' => 1]);
        $this->assertNull($book->name);
        $this->assertEquals(1, $book->author_id);
        $book->name = 'test';
        $this->assertEquals('test', $book->name);
    }

    public function testAttrProtected()
    {
        $book = new BookAttrProtected(['book_id' => 999]);
        $this->assertNull($book->book_id);
        $book->book_id = 999;
        $this->assertEquals(999, $book->book_id);
    }

    public function testIsset()
    {
        $book = new Book();
        $this->assertTrue(isset($book->name));
        $this->assertFalse(isset($book->sharks));

        $venue = new Venue();
        $this->assertTrue(isset($venue->customState));

        $author = Author::find(1);
        $this->assertTrue(isset($author->awesome_person));
    }

    public function testReadonlyOnlyHaltOnWriteMethod()
    {
        $book = Book::readonly(true)->first();
        $this->assertTrue($book->is_readonly());

        try {
            $book->save();
            $this->fail('expected exception ActiveRecord\ReadonlyException');
        } catch (ReadOnlyException $e) {
        }

        $book->name = 'some new name';
        $this->assertEquals($book->name, 'some new name');
    }

    public function testCastWhenUsingSetter()
    {
        $book = new Book();
        $book->book_id = '1';
        $this->assertSame(1, $book->book_id);
    }

    public function testCastWhenLoading()
    {
        $book = \test\models\Book::find(1);
        $this->assertSame(1, $book->book_id);
        $this->assertSame('Ancient Art of Main Tanking', $book->name);
    }

    public function testCastDefaults()
    {
        $book = new Book();
        $this->assertSame(0.0, $book->special);
    }

    public function testTransactionCommitted()
    {
        $original = Author::count();
        $ret = Author::transaction(function () { Author::create(['name' => 'blah']); });
        $this->assertEquals($original+1, Author::count());
        $this->assertTrue($ret);
    }

    public function testTransactionCommittedWhenReturningTrue()
    {
        $original = Author::count();
        $ret = Author::transaction(function () {
            Author::create(['name' => 'blah']);

            return true;
        });
        $this->assertEquals($original+1, Author::count());
        $this->assertTrue($ret);
    }

    public function testTransactionRolledbackByReturningFalse()
    {
        $original = Author::count();

        $ret = Author::transaction(function () {
            Author::create(['name' => 'blah']);

            return false;
        });

        $this->assertEquals($original, Author::count());
        $this->assertFalse($ret);
    }

    public function testTransactionRolledbackByThrowingException()
    {
        $original = Author::count();
        $exception = null;

        try {
            Author::transaction(function () {
                Author::create(['name' => 'blah']);
                throw new Exception('blah');
            });
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertEquals($original, Author::count());
    }

    public function testTableNameWithUnderscores()
    {
        $this->assertNotNull(AwesomePerson::first());
    }

    public function testModelShouldDefaultAsNewRecord()
    {
        $author = new Author();
        $this->assertTrue($author->is_new_record());
    }

    public function testSetter()
    {
        $author = new Author();
        $author->password = 'plaintext';
        $this->assertEquals(md5('plaintext'), $author->encrypted_password);
    }

    public function testCaseInsensitivity()
    {
        $author = new Author();
        $author->mixedCaseField = 'Peter';
        $this->assertEquals('Peter', $author->mixedcasefield);

        $author->MIXEDCASEFIELD = 'Paul';
        $this->assertEquals('Paul', $author->mixedCaseFIELD);

        $author->mixedcasefield = 'Simon';
        $this->assertEquals('Simon', $author->MIXEDCASEFIELD);
    }

    public function testSetterWithSameNameAsAnAttribute()
    {
        $author = new Author();
        $author->name = 'bob';
        $this->assertEquals('BOB', $author->name);
    }

    public function testGetter()
    {
        $book = Book::first();
        $this->assertEquals('ANCIENT ART OF MAIN TANKING', $book->upper_name);
    }

    public function testGetterWithSameNameAsAnAttribute()
    {
        $book = new Book();
        $book->publisher = 'Random House';
        $this->assertEquals('RANDOM HOUSE', $book->publisher);
    }

    public function testSettingInvalidDateShouldSetDateToNull()
    {
        $author = new Author();
        $author->created_at = 'CURRENT_TIMESTAMP';
        $this->assertNull($author->created_at);
    }

    public function testTableName()
    {
        $this->assertEquals('authors', Author::table_name());
    }

    public function testUndefinedInstanceMethod()
    {
        $this->expectException(ActiveRecordException::class);
        Author::first()->find_by_name('sdf');
    }

    public function testClearCacheForSpecificClass()
    {
        $book_table1 = ActiveRecord\Table::load(Book::class);
        $book_table2 = ActiveRecord\Table::load(Book::class);
        ActiveRecord\Table::clear_cache('Book');
        $book_table3 = ActiveRecord\Table::load(Book::class);

        $this->assertTrue($book_table1 === $book_table2);
        $this->assertTrue($book_table1 !== $book_table3);
    }

    public function testFlagDirty()
    {
        $author = new Author();
        $author->flag_dirty('some_date');
        $this->assertArrayHasKey('some_date', $author->dirty_attributes());
        $this->assertTrue($author->attribute_is_dirty('some_date'));
        $author->save();
        $this->assertFalse($author->attribute_is_dirty('some_date'));
    }

    public function testFlagDirtyAttributeWhichDoesNotExit()
    {
        $author = new Author();
        $author->flag_dirty('some_inexistant_property');
        $this->assertEquals([], $author->dirty_attributes());
        $this->assertFalse($author->attribute_is_dirty('some_inexistant_property'));
    }

    public function testGh245DirtyAttributeShouldNotRaisePhpNoticeIfNotDirty()
    {
        $event = new Event(['title' => 'Fun']);
        $this->assertFalse($event->attribute_is_dirty('description'));
        $this->assertTrue($event->attribute_is_dirty('title'));
    }

    public function testAssigningPhpDatetimeGetsConvertedToDateClassWithDefaults()
    {
        $author = new Author();
        $author->created_at = $now = new \DateTime();
        $this->assertInstanceOf('ActiveRecord\\DateTime', $author->created_at);
        $this->assertEquals($now->format(DateTime::ATOM), $author->created_at->format(DateTime::ATOM));
    }

    public function testAssigningPhpDatetimeGetsConvertedToDateClassWithCustomDateClass()
    {
        ActiveRecord\Config::instance()->set_date_class('\\DateTime'); // use PHP built-in DateTime
        $author = new Author();
        $author->created_at = $now = new \DateTime();
        $this->assertInstanceOf('DateTime', $author->created_at);
        $this->assertEquals($now->format(DateTime::ATOM), $author->created_at->format(DateTime::ATOM));
    }

    public function testAssigningFromMassAssignmentPhpDatetimeGetsConvertedToArDatetime()
    {
        $author = new Author(['created_at' => new \DateTime()]);
        $this->assertInstanceOf('ActiveRecord\\DateTime', $author->created_at);
    }

    public function testGetRealAttributeName()
    {
        $venue = new Venue();
        $this->assertEquals('name', $venue->get_real_attribute_name('name'));
        $this->assertEquals('name', $venue->get_real_attribute_name('marquee'));
        $this->assertEquals(null, $venue->get_real_attribute_name('invalid_field'));
    }

    public function testIdSetterWorksWithTableWithoutPkNamedAttribute()
    {
        $author = new Author(['id' => 123]);
        $this->assertEquals(123, $author->author_id);
    }

    public function testQuery()
    {
        $row = Author::query('SELECT COUNT(*) AS n FROM authors')->fetch();
        $this->assertTrue($row['n'] > 1);

        $row = Author::query('SELECT COUNT(*) AS n FROM authors WHERE name=?', ['Tito'])->fetch();
        $this->assertEquals(['n' => 2], $row);
    }
}
