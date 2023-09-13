<?php

namespace test;

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\DatabaseException;
use test\models\Author;
use test\models\Venue;

class ActiveRecordFindByTest extends \DatabaseTestCase
{
    public function testEscapeQuotes()
    {
        $author = Author::find_by_name("Tito's");
        $this->assertNotEquals("Tito's", Author::table()->last_sql);
    }

    public function testFindByWithInvalidFieldName()
    {
        $this->expectException(ActiveRecordException::class);
        Author::find_by_some_invalid_field_name('Tito');
    }

    public function testFindBy()
    {
        $this->assertEquals('Tito', Author::find_by_name('Tito')->name);
        $this->assertEquals('Tito', Author::find_by_author_id_and_name(1, 'Tito')->name);
        $this->assertEquals('George W. Bush', Author::find_by_author_id_or_name(2, 'Barney')->name);
        $this->assertEquals('Tito', Author::find_by_name(['Tito', 'George W. Bush'])->name);
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
}
