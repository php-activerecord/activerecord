<?php

namespace test;

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use ActiveRecord\Table;
use test\models\Author;
use test\models\Venue;

class ActiveRecordFindByTest extends \DatabaseTestCase
{
    public function testEscapeQuotes()
    {
        Author::find_by_name("Tito's");
        $this->assertNotEquals("Tito's", Table::load(Author::class)->last_sql);
    }

    public function testFindByWithInvalidFieldName()
    {
        $this->expectException(ActiveRecordException::class);
        Author::find_by_some_invalid_field_name('Tito');
    }

    public function testFindBy()
    {
        $author = Author::find_by_name('Tito');
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals('Tito', Author::find_by_name('Tito')->name);

        $this->assertEquals('Tito', Author::find_by_author_id_and_name(1, 'Tito')->name);
        $this->assertEquals('George W. Bush', Author::find_by_author_id_or_name(2, 'Barney')->name);
        $this->assertEquals('Tito', Author::find_by_name(['Tito', 'George W. Bush'])->name);
    }

    public function testFindByChained()
    {
        $author = Author::select('author_id')->find_by_name('Tito');
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals(1, $author->author_id);

        $author = Author::select('author_id')->order('author_id DESC')->find_by_name('Tito');
        $this->assertEquals(5, $author->author_id);

        $this->expectException(ActiveRecordException::class);
        $author->name;
    }

    public function testFindByDoesNotClobberOldLimit()
    {
        $rel = Author::where(['mixedCaseField' => 'Bill'])->limit(2);
        $authors = $rel->to_a();
        $this->assertEquals(2, count($authors));

        $author = $rel->find_by_mixedCaseField('Bill');
        $this->assertInstanceOf(Author::class, $author);

        $this->assertEquals($authors, $rel->to_a());

        $author = $rel->find_by_name('Bill Clinton');
        $this->assertInstanceOf(Author::class, $author);

        $this->assertEquals($authors, $rel->to_a());
    }

    public function testFindByNoResults()
    {
        $this->assertNull(Author::find_by_name('SHARKS WIT LASERZ'));
        $this->assertNull(Author::find_by_name_or_author_id());
    }

    public function testFindByInvalidColumnName()
    {
        $this->expectException(DatabaseException::class);
        Author::find_by_sharks();
    }

    public function testDynamicFinderUsingAlias()
    {
        $this->assertNotNull(Venue::find_by_marquee('Warner Theatre'));
    }

    public function testFindOrCreateByOnExistingRecord()
    {
        $this->assertNotNull(Author::find_or_create_by_name('Tito'));
    }

    public function testFindOrCreateByCreatesNewRecord()
    {
        $author = Author::find_or_create_by_name_and_encrypted_password('New Guy', 'pencil');
        $this->assertEquals(Author::last()->author_id, $author->author_id);
        $this->assertEquals('pencil', $author->encrypted_password);
    }

    public function testFindOrCreateByThrowsExceptionWhenUsingOr()
    {
        $this->expectException(ActiveRecordException::class);
        Author::find_or_create_by_name_or_encrypted_password('New Guy', 'pencil');
    }
}
