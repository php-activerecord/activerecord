<?php

use ActiveRecord\Exception\HasManyThroughAssociationException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\RelationshipException;
use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;
use test\models\AuthorAttrAccessible;
use test\models\AwesomePerson;
use test\models\Book;
use test\models\Employee;
use test\models\Event;
use test\models\Host;
use test\models\JoinBook;
use test\models\Position;
use test\models\Property;
use test\models\Venue;

class NotModel
{
}

class AuthorWithNonModelRelationship extends ActiveRecord\Model
{
    public static string $pk = 'id';
    public static string $table_name = 'authors';
    public static array $has_many = [
        'books' => [
            'class_name' => 'NotModel'
        ]
    ];
}

class RelationshipTest extends DatabaseTestCase
{
    protected $relationship_name;
    protected $relationship_names = ['HasMany', 'BelongsTo', 'HasOne'];

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);

        Event::$belongs_to = [
            'venue' => true,
            'host'=>true
        ];
        Venue::$has_many = [
            'events' => [
                'order' => 'id asc'
            ],
            'hosts' => [
                'through' => 'events',
                'order' => 'hosts.id asc'
            ]
        ];
        Venue::$has_one = [];
        Employee::$has_one = [
            'position' => []
        ];
        Host::$has_many = [
            'events' => [
                'order' => 'id asc'
            ]
        ];

        foreach ($this->relationship_names as $name) {
            if (preg_match("/$name/", $this->name(), $match)) {
                $this->relationship_name = $match[0];
            }
        }
    }

    protected function get_relationship($type=null)
    {
        if (!$type) {
            $type = $this->relationship_name;
        }

        switch ($type) {
            case 'BelongsTo':
                $ret = Event::find(5);
                break;

            case 'HasOne':
                $ret = Employee::find(1);
                break;

            case 'HasMany':
                $ret = Venue::find(2);
                break;
        }

        return $ret;
    }

    protected function assert_default_belongs_to($event, $association_name='venue')
    {
        $this->assertTrue($event->$association_name instanceof Venue);
        $this->assertEquals(5, $event->id);
        $this->assertEquals('West Chester', $event->$association_name->city);
        $this->assertEquals(6, $event->$association_name->id);
    }

    protected function assert_default_has_many($venue, $association_name='events')
    {
        $this->assertEquals(2, $venue->id);
        $this->assertTrue(count($venue->$association_name) > 1);
        $this->assertEquals('Yeah Yeah Yeahs', $venue->{$association_name}[0]->title);
    }

    protected function assert_default_has_one($employee, $association_name='position')
    {
        $this->assertTrue($employee->$association_name instanceof Position);
        $this->assertEquals('physicist', $employee->$association_name->title);
        $this->assertNotNull($employee->id, $employee->$association_name->title);
    }

    public function testHasManyBasic()
    {
        $this->assert_default_has_many($this->get_relationship());
    }

    public function testEagerLoadingThreeLevelsDeep()
    {
        /* Before fix Undefined offset: 0 */
        $venue = Venue::include([
            'events'=>[
                'host'=>[
                    'events'
                ]
            ]
        ])->find(2);

        $events = $venue->events;
        $this->assertEquals(2, count($events));
        $event_yeah_yeahs = $events[0];
        $this->assertEquals('Yeah Yeah Yeahs', $event_yeah_yeahs->title);

        $event_host = $event_yeah_yeahs->host;
        $this->assertEquals('Billy Crystal', $event_host->name);

        $bill_events = $event_host->events;

        $this->assertEquals('Yeah Yeah Yeahs', $bill_events[0]->title);
    }

    public function testJoinsOnModelViaUndeclaredAssociation()
    {
        $this->expectException(RelationshipException::class);
        JoinBook::joins(['undeclared'])->first();
    }

    public function testJoinsOnlyLoadsGivenModelAttributes()
    {
        $x = Event::joins(['venue'])->first();
        $this->assert_sql_has('SELECT events.*', Event::table()->last_sql);
        $this->assertFalse(array_key_exists('city', $x->attributes()));
    }

    public function testJoinsCombinedWithSelectLoadsAllAttributes()
    {
        $x = Event::select('events.*, venues.city as venue_city')
            ->joins(['venue'])
            ->first();
        $this->assert_sql_has('SELECT events.*, venues.city as venue_city', Event::table()->last_sql);
        $this->assertTrue(array_key_exists('venue_city', $x->attributes()));
    }

    public function testBelongsToBasic()
    {
        $this->assert_default_belongs_to($this->get_relationship());
    }

    public function testBelongsToReturnsNullWhenNoRecord()
    {
        $event = Event::find(6);
        $this->assertNull($event->venue);
    }

    public function testBelongsToReturnsNullWhenForeignKeyIsNull()
    {
        $event = Event::create(['title' => 'venueless event', 'host_id'=>1]);
        $this->assertNull($event->venue);
    }

    public function testBelongsToWithExplicitClassName()
    {
        Event::$belongs_to = [
            'explicit_class_name' => [
                'class_name' => 'Venue'
            ]
        ];
        $this->assert_default_belongs_to($this->get_relationship(), 'explicit_class_name');
    }

    public function testBelongsToWithExplicitForeignKey()
    {
        Book::$belongs_to = [
            'explicit_author' => [
                'class_name' => 'Author',
                'foreign_key' => 'secondary_author_id'
            ]
        ];

        $book = Book::find(1);
        $this->assertEquals(2, $book->secondary_author_id);
        $this->assertEquals($book->secondary_author_id, $book->explicit_author->author_id);
    }

    public function testBelongsToWithSelect()
    {
        Event::$belongs_to = [
            'venue' => [
                'select' => 'id, city'
            ]
        ];
        $event = $this->get_relationship();
        $this->assert_default_belongs_to($event);

        try {
            $event->venue->name;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'name'));
        }
    }

    public function testBelongsToWithReadonly()
    {
        Event::$belongs_to = [
            'venue' => [
                'readonly' => true
            ]
        ];
        $event = $this->get_relationship();
        $this->assert_default_belongs_to($event);

        try {
            $event->venue->save();
            $this->fail('expected exception ActiveRecord\ReadonlyException');
        } catch (ReadOnlyException $e) {
        }

        $event->venue->name = 'new name';
        $this->assertEquals($event->venue->name, 'new name');
    }

    public function testBelongsToWithPluralAttributeName()
    {
        Event::$belongs_to = [
            'venues' => [
                'class_name' => 'Venue'
            ]
        ];
        $this->assert_default_belongs_to($this->get_relationship(), 'venues');
    }

    public function testBelongsToWithConditionsAndNonQualifyingRecord()
    {
        Event::$belongs_to = [
            'venue' => [
                'conditions' => "state = 'NY'"
            ]
        ];
        $event = Event::find(5);
        $this->assertEquals(5, $event->id);
        $this->assertNull($event->venue);
    }

    public function testBelongsToWithConditionsAndQualifyingRecord()
    {
        Event::$belongs_to = [
            'venue' => [
                'conditions' => "state = 'PA'"
            ]
        ];
        $this->assert_default_belongs_to($this->get_relationship());
    }

    public function testBelongsToBuildAssociation()
    {
        $event = $this->get_relationship();
        $values = ['city' => 'Richmond', 'state' => 'VA'];
        $venue = $event->build_venue($values);
        $this->assertEquals($values, array_intersect_key($values, $venue->attributes()));
    }

    public function testHasManyBuildAssociation()
    {
        $author = Author::first();
        $this->assertEquals($author->id, $author->build_books()->author_id);
        $this->assertEquals($author->id, $author->build_book()->author_id);
    }

    public function testBelongsToCreateAssociation()
    {
        $event = $this->get_relationship();
        $values = ['city' => 'Richmond', 'state' => 'VA', 'name' => 'Club 54', 'address' => '123 street'];
        $venue = $event->create_venue($values);
        $this->assertNotNull($venue->id);
    }

    public function testBuildAssociationOverwritesGuardedForeignKeys()
    {
        $author = new AuthorAttrAccessible();
        $author->save();

        $book = $author->build_book();

        $this->assertNotNull($book->author_id);
    }

    public function testBelongsToCanBeSelfReferential()
    {
        Author::$belongs_to = [
            'parent_author' => [
                'class_name' => 'Author',
                'foreign_key' => 'parent_author_id'
            ]
        ];
        $author = Author::find(1);
        $this->assertEquals(1, $author->id);
        $this->assertEquals(3, $author->parent_author->id);
    }

    public function testBelongsToWithAnInvalidOption()
    {
        Event::$belongs_to = [
            'host' => [
                'joins' => 'venue'
            ]
        ];
        $this->assert_sql_doesnt_has('INNER JOIN venues ON(events.venue_id = venues.id)', Event::table()->last_sql);
    }

    public function testHasManyWithExplicitClassName()
    {
        Venue::$has_many = [
            'explicit_class_name' => [
                'class_name' => 'Event',
                'order' => 'id asc'
            ]
        ];
        $this->assert_default_has_many($this->get_relationship(), 'explicit_class_name');
    }

    public function testHasManyWithInvalidAssociation()
    {
        $this->expectException(RelationshipException::class);
        Venue::table()->get_relationship('non_existent_table', true);
        $this->assert_sql_has($this->connection->limit('SELECT type FROM events WHERE venue_id=? GROUP BY type', 1, 2), Event::table()->last_sql);
    }

    public function testHasManyWithSelect()
    {
        Venue::$has_many = [
            'events' => [
                'select' => 'title, type'
            ]
        ];

        $venue = $this->get_relationship();
        $this->assert_default_has_many($venue);

        try {
            $venue->events[0]->description;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'description'));
        }
    }

    public function testHasManyWithReadonly()
    {
        Venue::$has_many = [
            'events' => [
                'readonly' => true
            ]
        ];
        $venue = $this->get_relationship();
        $this->assert_default_has_many($venue);

        try {
            $venue->events[0]->save();
            $this->fail('expected exception ActiveRecord\ReadonlyException');
        } catch (ReadonlyException $e) {
        }

        $venue->events[0]->description = 'new desc';
        $this->assertEquals($venue->events[0]->description, 'new desc');
    }

    public function testHasManyWithSingularAttributeName()
    {
        Venue::$has_many = [
            'event' => [
                'class_name' => 'Event',
                'order' => 'id asc'
            ]
        ];
        $this->assert_default_has_many($this->get_relationship(), 'event');
    }

    public function testHasManyWithConditionsAndQualifyingRecord()
    {
        Venue::$has_many = [
            'events' => [
                'conditions' => "title = 'Yeah Yeah Yeahs'"
            ]
        ];
        $venue = $this->get_relationship();
        $this->assertEquals(2, $venue->id);
        $this->assertEquals($venue->events[0]->title, 'Yeah Yeah Yeahs');
    }

    public function testHasManyWithSqlClauseOptions()
    {
        Venue::$has_many = [
            'events' => [
                'select' => 'type',
                'group'  => 'type',
                'limit'  => 2,
                'offset' => 1
            ]
        ];

        Venue::first()->events;
        $this->assert_sql_has($this->connection->limit('SELECT type FROM events WHERE venue_id=? GROUP BY type', 1, 2), Event::table()->last_sql);
    }

    public function testHasManyThrough()
    {
        $hosts = Venue::find(2)->hosts;
        $this->assertEquals(2, $hosts[0]->id);
        $this->assertEquals(3, $hosts[1]->id);
    }

    public function testGh27HasManyThroughWithExplicitKeys()
    {
        $property = Property::first();

        $this->assertEquals(1, $property->amenities[0]->amenity_id);
        $this->assertEquals(2, $property->amenities[1]->amenity_id);
    }

    public function testHasManyThroughInsideALoopShouldNotCauseAnException()
    {
        $count = 0;

        foreach (Venue::all()->to_a() as $venue) {
            $count += count($venue->hosts);
        }

        $this->assertTrue($count >= 5);
    }

    public function testHasManyThroughNoAssociation()
    {
        $this->expectException(HasManyThroughAssociationException::class);
        Event::$belongs_to = [
            'host' => true
        ];
        Venue::$has_many = [
            'hosts' => [
                'through' => 'blahhhhhhh'
            ]
        ];

        $venue = $this->get_relationship();
        $n = $venue->hosts;
        $this->assertTrue(count($n) > 0);
    }

    public function testHasManyThroughWithSelect()
    {
        Event::$belongs_to = [
            'host' => true
        ];
        Venue::$has_many = [
            'hosts' => [
                'through' => 'events',
                'select' => 'hosts.*, events.*'
            ]
        ];

        $venue = $this->get_relationship();
        $this->assertTrue(count($venue->hosts) > 0);
        $this->assertNotNull($venue->hosts[0]->title);
    }

    public function testHasManyThroughWithConditions()
    {
        Event::$belongs_to = [
            'host' => true
        ];
        Venue::$has_many = [
            'hosts' => [
                'through' => 'events',
                'conditions' => [
                    'events.title != ?',
                    'Love Overboard'
                ]
            ]
        ];

        $venue = $this->get_relationship();
        $this->assertTrue(1 === count($venue->hosts));
        $this->assert_sql_has('events.title !=', ActiveRecord\Table::load(Host::class)->last_sql);
    }

    public function testHasManyThroughUsingSource()
    {
        Event::$belongs_to = [
            'host' => true
        ];

        Venue::$has_many = [
            'hostess' => [
                'through' => 'events',
                'source' => 'host'
            ]
        ];

        $venue = $this->get_relationship();
        $this->assertTrue(count($venue->hostess) > 0);
    }

    public function testHasManyThroughWithInvalidClassName()
    {
        $this->expectException(\ReflectionException::class);
        Event::$belongs_to = [
            'host' => true
        ];
        Venue::$has_one = [
            'invalid_assoc' => true
        ];
        Venue::$has_many = [
            'hosts' => [
                'through' => 'invalid_assoc'
            ]
        ];

        $this->get_relationship()->hosts;
    }

    public function testHasManyWithJoins()
    {
        $x = Venue::joins(['events'])->first();
        $this->assert_sql_has('INNER JOIN events ON(venues.id = events.venue_id)', Venue::table()->last_sql);
    }

    public function testHasManyWithExplicitKeys()
    {
        Author::$has_many = [
            'explicit_books' => [
                'class_name' => 'Book',
                'primary_key' => 'parent_author_id',
                'foreign_key' => 'secondary_author_id'
            ]
        ];
        $author = Author::find(4);

        foreach ($author->explicit_books as $book) {
            $this->assertEquals($book->secondary_author_id, $author->parent_author_id);
        }

        $this->assertTrue(false !== strpos(ActiveRecord\Table::load(Book::class)->last_sql, 'secondary_author_id'));
    }

    public function testHasOneBasic()
    {
        $this->assert_default_has_one(Employee::find(1));
    }

    public function testHasOneWithExplicitClassName()
    {
        Employee::$has_one = [
            'explicit_class_name' => [
                'class_name' => 'Position'
            ]
        ];
        $this->assert_default_has_one($this->get_relationship(), 'explicit_class_name');
    }

    public function testHasOneWithSelect()
    {
        $ho = Employee::$has_one;
        Employee::$has_one['position']['select'] = 'title';
        $employee = $this->get_relationship();
        $this->assert_default_has_one($employee);

        try {
            $employee->position->active;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'active'));
        }
    }

    public function testHasOneWithOrder()
    {
        Employee::$has_one['position']['order'] = 'title';
        $employee = $this->get_relationship();
        $this->assert_default_has_one($employee);
        $this->assert_sql_has('ORDER BY title', Position::table()->last_sql);
    }

    public function testHasOneWithConditionsAndNonQualifyingRecord()
    {
        Employee::$has_one['position']['conditions'] = "title = 'programmer'";
        $employee = $this->get_relationship();
        $this->assertEquals(1, $employee->id);
        $this->assertNull($employee->position);
    }

    public function testHasOneWithConditionsAndQualifyingRecord()
    {
        Employee::$has_one['position']['conditions'] = "title = 'physicist'";
        $this->assert_default_has_one($this->get_relationship());
    }

    public function testHasOneWithReadonly()
    {
        Employee::$has_one['position']['readonly'] = true;
        $employee = $this->get_relationship();
        $this->assert_default_has_one($employee);

        try {
            $employee->position->save();
            $this->fail('expected exception ActiveRecord\ReadonlyException');
        } catch (ReadonlyException $e) {
        }

        $employee->position->title = 'new title';
        $this->assertEquals($employee->position->title, 'new title');
    }

    public function testHasOneCanBeSelfReferential()
    {
        Author::$has_one[1] = ['parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id'];
        $author = Author::find(1);
        $this->assertEquals(1, $author->id);
        $this->assertEquals(3, $author->parent_author->id);
    }

    public function testHasOneWithJoins()
    {
        $x = Employee::joins(['position'])->first();
        $this->assert_sql_has('INNER JOIN positions ON(employees.id = positions.employee_id)', Employee::table()->last_sql);
    }

    public function testHasOneWithExplicitKeys()
    {
        Book::$has_one = [
            'explicit_author' => [
                'class_name' => 'Author',
                'foreign_key' => 'parent_author_id',
                'primary_key' => 'secondary_author_id'
            ]
        ];

        $book = Book::find(1);
        $this->assertEquals($book->secondary_author_id, $book->explicit_author->parent_author_id);
        $this->assertTrue(false !== strpos(ActiveRecord\Table::load(Author::class)->last_sql, 'parent_author_id'));
    }

    public function testDontAttemptToLoadIfAllForeignKeysAreNull()
    {
        $event = new Event();
        $event->venue;
        $this->assert_sql_doesnt_has($this->connection->last_query, 'is IS NULL');
    }

    public function testRelationshipOnTableWithUnderscores()
    {
        $this->assertEquals(1, Author::find(1)->awesome_person->is_awesome);
    }

    public function testHasOneThrough()
    {
        Venue::$has_many = [
            'events' => true,
            'hosts' => [
                'through' => 'events'
            ]
        ];
        $venue = Venue::first();
        $this->assertTrue(count($venue->hosts) > 0);
    }

    public function testThrowErrorIfRelationshipIsNotAModel()
    {
        $this->expectException(RelationshipException::class);
        AuthorWithNonModelRelationship::first()->books;
    }

    public function testGh93AndGh100EagerLoadingRespectsAssociationOptions()
    {
        Venue::$has_many = [
            'events' => [
                'class_name' => 'Event',
                'order' => 'id asc',
                'conditions' => [
                    'length(title) = ?', 14
                ]
            ]
        ];
        $venues = Venue::include('events')->find([2, 6]);

        $this->assert_sql_has('WHERE (length(title) = ?) AND (venue_id IN(?,?)) ORDER BY id asc', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assertEquals(1, count($venues[0]->events));
    }

    public function testEagerLoadingHasManyX()
    {
        $venues = Venue::include('events')->find([2, 6]);
        $this->assert_sql_has('WHERE venue_id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);

        foreach ($venues[0]->events as $event) {
            $this->assertEquals($event->venue_id, $venues[0]->id);
        }

        $this->assertEquals(2, count($venues[0]->events));
    }

    public function testEagerLoadingHasManyWithNoRelatedRows()
    {
        $venues = Venue::include('events')->find([7, 8]);

        foreach ($venues as $v) {
            $this->assertTrue(empty($v->events));
        }

        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
        $this->assert_sql_has('WHERE venue_id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
    }

    public function testEagerLoadingHasManyArrayOfIncludes()
    {
        Author::$has_many = [
            'books' => true,
            'awesome_people' => true
        ];
        $authors = Author::include(['books', 'awesome_people'])
            ->find([1, 2]);

        $assocs = ['books', 'awesome_people'];

        foreach ($assocs as $assoc) {
            $this->assertIsArray($authors[0]->$assoc);

            foreach ($authors[0]->$assoc as $a) {
                $this->assertEquals($authors[0]->author_id, $a->author_id);
            }
        }

        foreach ($assocs as $assoc) {
            $this->assertIsArray($authors[1]->$assoc);
        }

        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(Author::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(Book::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(AwesomePerson::class)->last_sql);
    }

    public function testEagerLoadingHasManyNested()
    {
        $venues = Venue::include(['events' => ['host']])
            ->find([1, 2]);

        $this->assertEquals(2, count($venues));

        foreach ($venues as $v) {
            $this->assertTrue(count($v->events) > 0);

            foreach ($v->events as $e) {
                $this->assertEquals($e->host_id, $e->host->id);
                $this->assertEquals($v->id, $e->venue_id);
            }
        }

        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
        $this->assert_sql_has('WHERE venue_id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?,?)', ActiveRecord\Table::load(Host::class)->last_sql);
    }

    public function testEagerLoadingBelongsTo()
    {
        $events = Event::include('venue')
            ->find([1, 2, 3, 5, 7]);

        foreach ($events as $event) {
            $this->assertEquals($event->venue_id, $event->venue->id);
        }

        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testEagerLoadingBelongsToArrayOfIncludes()
    {
        $events = Event::include(['venue', 'host'])->find([1, 2, 3, 5, 7]);

        foreach ($events as $event) {
            $this->assertEquals($event->venue_id, $event->venue->id);
            $this->assertEquals($event->host_id, $event->host->id);
        }

        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Host::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testEagerLoadingBelongsToNested()
    {
        Author::$has_many = [
            'awesome_people' => true,
        ];
        Book::$belongs_to = [
            'author' => true
        ];

        $books = Book::include([
            'author' => [
                'awesome_people'
            ]
        ])->find([1, 2]);

        foreach ($books as $book) {
            $this->assertEquals($book->author_id, $book->author->author_id);
            $this->assertEquals($book->author->author_id, $book->author->awesome_people[0]->author_id);
        }

        $this->assert_sql_has('WHERE book_id IN(?,?)', ActiveRecord\Table::load(Book::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(Author::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(AwesomePerson::class)->last_sql);
    }

    public function testEagerLoadingBelongsToWithNoRelatedRows()
    {
        $e1 = Event::create(['venue_id' => 200, 'host_id' => 200, 'title' => 'blah', 'type' => 'Music']);
        $e2 = Event::create(['venue_id' => 200, 'host_id' => 200, 'title' => 'blah2', 'type' => 'Music']);

        $events = Event::include('venue')
            ->find([$e1->id, $e2->id]);

        foreach ($events as $e) {
            $this->assertNull($e->venue);
        }

        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function testEagerLoadingClonesRelatedObjects()
    {
        $events = Event::include('venue')
            ->find([2, 3]);

        $venue = $events[0]->venue;
        $venue->name = 'new name';

        $this->assertEquals($venue->id, $events[1]->venue->id);
        $this->assertNotEquals($venue->name, $events[1]->venue->name);
        $this->assertNotEquals(spl_object_hash($venue), spl_object_hash($events[1]->venue));
    }

    public function testEagerLoadingClonesNestedRelatedObjects()
    {
        $venues = Venue::include(['events' => ['host']])
            ->find([1, 2, 6, 9]);

        $unchanged_host = $venues[2]->events[0]->host;
        $changed_host = $venues[3]->events[0]->host;
        $changed_host->name = 'changed';

        $this->assertEquals($changed_host->id, $unchanged_host->id);
        $this->assertNotEquals($changed_host->name, $unchanged_host->name);
        $this->assertNotEquals(spl_object_hash($changed_host), spl_object_hash($unchanged_host));
    }

    public function testGh23RelationshipsWithJoinsToSameTableShouldAliasTableName()
    {
        Book::$belongs_to = [
            'from_' => [
                'class_name' => 'Author',
                'foreign_key' => 'author_id'
            ],
            'to' => [
                'class_name' => 'Author',
                'foreign_key' => 'secondary_author_id'
            ],
            'another' => [
                'class_name' => 'Author',
                'foreign_key' => 'secondary_author_id'
            ]
        ];

        $c = ActiveRecord\Table::load(Book::class)->conn;

        $select = "books.*, authors.name as to_author_name, {$c->quote_name('from_')}.name as from_author_name, {$c->quote_name('another')}.name as another_author_name";
        $book = Book::joins(['to', 'from_', 'another'])
            ->select($select)
            ->find(2);

        $this->assertNotNull($book->from_author_name);
        $this->assertNotNull($book->to_author_name);
        $this->assertNotNull($book->another_author_name);
    }

    public function testRelationshipsWithJoinsAliasesTableNameInConditions()
    {
        $event = Event::joins(['venue'])->find(1);

        $this->assertEquals($event->id, $event->venue->id);
    }

    public function testDontAttemptEagerLoadWhenRecordDoesNotExist()
    {
        $this->expectException(RecordNotFound::class);
        Author::find(999999, ['include' => ['books']]);
    }
}
