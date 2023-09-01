<?php

use ActiveRecord\DateTime;
use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;
use test\models\Venue;
use test\models\Book;

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

    public function test_save()
    {
        $this->expectNotToPerformAssertions();
        $venue = new Venue(['name' => 'Tito']);
        $venue->save();
    }

    public function test_insert()
    {
        $author = new Author(['name' => 'Blah Blah']);
        $author->save();
        $this->assertNotNull(Author::find($author->id));
    }

    public function test_insert_with_no_sequence_defined()
    {
        $this->expectException(DatabaseException::class);
        if (!$this->connection->supports_sequences()) {
            throw new DatabaseException('');
        }
        AuthorWithoutSequence::create(['name' => 'Bob!']);
    }

    public function test_insert_should_quote_keys()
    {
        $author = new Author(['name' => 'Blah Blah']);
        $author->save();
        $this->assertTrue(false !== strpos($author->connection()->last_query, $author->connection()->quote_name('updated_at')));
    }

    public function test_save_auto_increment_id()
    {
        $venue = new Venue(['name' => 'Bob']);
        $venue->save();
        $this->assertTrue($venue->id > 0);
    }

    public function test_sequence_was_set()
    {
        if ($this->connection->supports_sequences()) {
            $this->assertEquals($this->connection->get_sequence_name('authors', 'author_id'), Author::table()->sequence);
        } else {
            $this->assertNull(Author::table()->sequence);
        }
    }

    public function test_sequence_was_explicitly_set()
    {
        if ($this->connection->supports_sequences()) {
            $this->assertEquals(AuthorExplicitSequence::$sequence, AuthorExplicitSequence::table()->sequence);
        } else {
            $this->assertNull(Author::table()->sequence);
        }
    }

    public function test_delete()
    {
        $author = Author::find(1);
        $author->delete();

        $this->assertFalse(Author::exists(1));
    }

    public function test_delete_by_find_all()
    {
        $books = Book::all();

        foreach ($books as $model) {
            $model->delete();
        }

        $res = Book::all();
        $this->assertEquals(0, count($res));
    }

    public function test_update()
    {
        $book = Book::find(1);
        $new_name = 'new name';
        $book->name = $new_name;
        $book->save();

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function test_update_should_quote_keys()
    {
        $book = Book::find(1);
        $book->name = 'new name';
        $book->save();
        $this->assertTrue(false !== strpos($book->connection()->last_query, $book->connection()->quote_name('name')));
    }

    public function test_update_attributes()
    {
        $book = Book::find(1);
        $new_name = 'How to lose friends and alienate people'; // jax i'm worried about you
        $attrs = ['name' => $new_name];
        $book->update_attributes($attrs);

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function test_update_attributes_undefined_property()
    {
        $this->expectException(UndefinedPropertyException::class);
        $book = Book::find(1);
        $book->update_attributes(['name' => 'new name', 'invalid_attribute' => true, 'another_invalid_attribute' => 'blah']);
    }

    public function test_update_attribute()
    {
        $book = Book::find(1);
        $new_name = 'some stupid self-help book';
        $book->update_attribute('name', $new_name);

        $this->assertSame($new_name, $book->name);
        $this->assertSame($new_name, $book->name, Book::find(1)->name);
    }

    public function test_update_attribute_undefined_property()
    {
        $this->expectException(UndefinedPropertyException::class);
        $book = Book::find(1);
        $book->update_attribute('invalid_attribute', true);
    }

    public function test_save_null_value()
    {
        $book = Book::first();
        $book->name = null;
        $book->save();
        $this->assertSame(null, Book::find($book->id)->name);
    }

    public function test_save_blank_value()
    {
        $book = Book::find(1);
        $book->name = '';
        $book->save();
        $this->assertSame('', Book::find(1)->name);
    }

    public function test_dirty_attributes()
    {
        $book = $this->make_new_book_and(false);
        $this->assertEquals(['name', 'special'], array_keys($book->dirty_attributes()));
    }

    public function test_id_type()
    {
        $book = new Book();
        $book->save();

        $bookFromFind = Book::find($book->id);

        // both should be ints
        $this->assertSame($book->id, $bookFromFind->id);
    }

    public function test_dirty_attributes_cleared_after_saving()
    {
        $book = $this->make_new_book_and();
        $this->assertTrue(false !== strpos($book->table()->last_sql, 'name'));
        $this->assertTrue(false !== strpos($book->table()->last_sql, 'special'));
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function test_dirty_attributes_cleared_after_inserting()
    {
        $book = $this->make_new_book_and();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function test_no_dirty_attributes_but_still_insert_record()
    {
        $book = new Book();
        $this->assertEquals([], $book->dirty_attributes());
        $book->save();
        $this->assertEquals([], $book->dirty_attributes());
        $this->assertNotNull($book->id);
    }

    public function test_dirty_attributes_cleared_after_updating()
    {
        $book = Book::first();
        $book->name = 'rivers cuomo';
        $book->save();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function test_dirty_attributes_after_reloading()
    {
        $book = Book::first();
        $book->name = 'rivers cuomo';
        $book->reload();
        $this->assertEquals([], $book->dirty_attributes());
    }

    public function test_dirty_attributes_with_mass_assignment()
    {
        $book = Book::first();
        $book->set_attributes(['name' => 'rivers cuomo']);
        $this->assertEquals(['name'], array_keys($book->dirty_attributes()));
    }

    public function test_timestamps_set_before_save()
    {
        $author = new Author();
        $author->save();
        $this->assertNotNull($author->created_at, $author->updated_at);

        $author->reload();
        $this->assertNotNull($author->created_at, $author->updated_at);
    }

    public function test_timestamps_updated_at_only_set_before_update()
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

    public function test_create()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $this->assertNotNull(Author::find($author->id));
    }

    public function test_create_should_set_created_at()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $this->assertNotNull($author->created_at);
    }

    public function test_update_with_no_primary_key_defined()
    {
        $this->expectException(ActiveRecordException::class);
        Author::table()->pk = [];
        $author = Author::first();
        $author->name = 'blahhhhhhhhhh';
        $author->save();
    }

    public function test_delete_with_no_primary_key_defined()
    {
        $this->expectException(ActiveRecordException::class);
        Author::table()->pk = [];
        $author = author::first();
        $author->delete();
    }

    public function test_inserting_with_explicit_pk()
    {
        $author = Author::create(['author_id' => 9999, 'name' => 'blah']);
        $this->assertEquals(9999, $author->author_id);
    }

    public function test_readonly()
    {
        $this->expectException(ReadOnlyException::class);
        $author = Author::first(['readonly' => true]);
        $author->save();
    }

    public function test_modified_attributes_in_before_handlers_get_saved()
    {
        $author = DirtyAuthor::first();
        $author->encrypted_password = 'coco';
        $author->save();
        $this->assertEquals('i saved', DirtyAuthor::find($author->id)->name);
        $this->assertEquals('coco', DirtyAuthor::find($author->id)->encrypted_password);
    }

    public function test_is_dirty()
    {
        $author = Author::first();
        $this->assertEquals(false, $author->is_dirty());

        $author->name = 'coco';
        $this->assertEquals(true, $author->is_dirty());
    }

    public function test_set_date_flags_dirty()
    {
        $author = Author::create(['some_date' => new DateTime()]);
        $author = Author::find($author->id);
        $author->some_date->setDate(2010, 1, 1);
        $this->assertArrayHasKey('some_date', $author->dirty_attributes());
    }

    public function test_set_date_flags_dirty_with_php_datetime()
    {
        $author = Author::create(['some_date' => new \DateTime()]);
        $author = Author::find($author->id);
        $author->some_date->setDate(2010, 1, 1);
        $this->assertArrayHasKey('some_date', $author->dirty_attributes());
    }

    public function test_delete_all_with_conditions_as_string()
    {
        $num_affected = Author::delete_all(['conditions' => 'parent_author_id = 2']);
        $this->assertEquals(2, $num_affected);
    }

    public function test_delete_all_with_conditions_as_hash()
    {
        $num_affected = Author::delete_all(['conditions' => ['parent_author_id' => 2]]);
        $this->assertEquals(2, $num_affected);
    }

    public function test_delete_all_with_conditions_as_array()
    {
        $num_affected = Author::delete_all(['conditions' => ['parent_author_id = ?', 2]]);
        $this->assertEquals(2, $num_affected);
    }

    public function test_delete_all_with_limit_and_order()
    {
        if (!$this->connection->accepts_limit_and_order_for_update_and_delete()) {
            $this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE clause');
        }

        $num_affected = Author::delete_all(['conditions' => ['parent_author_id = ?', 2], 'limit' => 1, 'order' => 'name asc']);
        $this->assertEquals(1, $num_affected);
        $this->assertTrue(false !== strpos(Author::table()->last_sql, 'ORDER BY name asc LIMIT 1'));
    }

    public function test_update_all_with_set_as_string()
    {
        $num_affected = Author::update_all(['set' => 'parent_author_id = 2']);
        $this->assertEquals(2, $num_affected);
        $this->assertEquals(4, Author::count_by_parent_author_id(2));
    }

    public function test_update_all_with_set_as_hash()
    {
        $num_affected = Author::update_all(['set' => ['parent_author_id' => 2]]);
        $this->assertEquals(2, $num_affected);
    }

    /**
     * TODO: not implemented
     */
    public function test_update_all_with_conditions_as_string()
    {
        $num_affected = Author::update_all(['set' => 'parent_author_id = 2', 'conditions' => 'name = "Tito"']);
        $this->assertEquals(1, $num_affected);
    }

    public function test_update_all_with_conditions_as_hash()
    {
        $num_affected = Author::update_all(['set' => 'parent_author_id = 2', 'conditions' => ['name' => 'Tito']]);
        $this->assertEquals(1, $num_affected);
    }

    public function test_update_all_with_conditions_as_array()
    {
        $num_affected = Author::update_all(['set' => 'parent_author_id = 2', 'conditions' => ['name = ?', 'Tito']]);
        $this->assertEquals(1, $num_affected);
    }

    public function test_update_all_with_limit_and_order()
    {
        if (!$this->connection->accepts_limit_and_order_for_update_and_delete()) {
            $this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE clause');
        }

        $num_affected = Author::update_all(['set' => 'parent_author_id = 2', 'limit' => 1, 'order' => 'name asc']);
        $this->assertEquals(1, $num_affected);
        $this->assertTrue(false !== strpos(Author::table()->last_sql, 'ORDER BY name asc LIMIT 1'));
    }

    public function test_update_native_datetime()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $native_datetime = new \DateTime('1983-12-05');
        $author->some_date = $native_datetime;
        $this->assertFalse($native_datetime === $author->some_date);
    }

    public function test_update_our_datetime()
    {
        $author = Author::create(['name' => 'Blah Blah']);
        $our_datetime = new DateTime('1983-12-05');
        $author->some_date = $our_datetime;
        $this->assertTrue($our_datetime === $author->some_date);
    }
}
