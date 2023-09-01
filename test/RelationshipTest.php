<?php

use ActiveRecord\Exception\HasManyThroughAssociationException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\RecordNotFound;
use ActiveRecord\Exception\RelationshipException;
use ActiveRecord\Exception\UndefinedPropertyException;
use test\models\Author;
use test\models\AuthorAttrAccessible;
use test\models\AwesomePerson;
use test\models\Employee;
use test\models\Event;
use test\models\Host;
use test\models\JoinBook;
use test\models\Position;
use test\models\Venue;
use test\models\Book;
use test\models\Property;

class NotModel
{
}

class AuthorWithNonModelRelationship extends ActiveRecord\Model
{
    public static string $pk = 'id';
    public static string $table_name = 'authors';
    public static array $has_many = [['books', 'class_name' => 'NotModel']];
}

class RelationshipTest extends DatabaseTestCase
{
    protected $relationship_name;
    protected $relationship_names = ['has_many', 'belongs_to', 'has_one'];

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);

        Event::$belongs_to = [['venue'], ['host']];
        Venue::$has_many = [['events', 'order' => 'id asc'], ['hosts', 'through' => 'events', 'order' => 'hosts.id asc']];
        Venue::$has_one = [];
        Employee::$has_one = [['position']];
        Host::$has_many = [['events', 'order' => 'id asc']];

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
            case 'belongs_to':
                $ret = Event::find(5);
                break;

            case 'has_one':
                $ret = Employee::find(1);
                break;

            case 'has_many':
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

    public function test_has_many_basic()
    {
        $this->assert_default_has_many($this->get_relationship());
    }

    public function test_gh_256_eager_loading_three_levels_deep()
    {
        /* Before fix Undefined offset: 0 */
        $conditions['include'] = ['events'=>['host'=>['events']]];
        $venue = Venue::find(2, $conditions);

        $events = $venue->events;
        $this->assertEquals(2, count($events));
        $event_yeah_yeahs = $events[0];
        $this->assertEquals('Yeah Yeah Yeahs', $event_yeah_yeahs->title);

        $event_host = $event_yeah_yeahs->host;
        $this->assertEquals('Billy Crystal', $event_host->name);

        $bill_events = $event_host->events;

        $this->assertEquals('Yeah Yeah Yeahs', $bill_events[0]->title);
    }

    public function test_joins_on_model_via_undeclared_association()
    {
        $this->expectException(RelationshipException::class);
        JoinBook::first(['joins' => ['undeclared']]);
    }

    public function test_joins_only_loads_given_model_attributes()
    {
        $x = Event::first(['joins' => ['venue']]);
        $this->assert_sql_has('SELECT events.*', Event::table()->last_sql);
        $this->assertFalse(array_key_exists('city', $x->attributes()));
    }

    public function test_joins_combined_with_select_loads_all_attributes()
    {
        $x = Event::first(['select' => 'events.*, venues.city as venue_city', 'joins' => ['venue']]);
        $this->assert_sql_has('SELECT events.*, venues.city as venue_city', Event::table()->last_sql);
        $this->assertTrue(array_key_exists('venue_city', $x->attributes()));
    }

    public function test_belongs_to_basic()
    {
        $this->assert_default_belongs_to($this->get_relationship());
    }

    public function test_belongs_to_returns_null_when_no_record()
    {
        $event = Event::find(6);
        $this->assertNull($event->venue);
    }

    public function test_belongs_to_returns_null_when_foreign_key_is_null()
    {
        $event = Event::create(['title' => 'venueless event', 'host_id'=>1]);
        $this->assertNull($event->venue);
    }

    public function test_belongs_to_with_explicit_class_name()
    {
        Event::$belongs_to = [['explicit_class_name', 'class_name' => 'Venue']];
        $this->assert_default_belongs_to($this->get_relationship(), 'explicit_class_name');
    }

    public function test_belongs_to_with_explicit_foreign_key()
    {
        $old = Book::$belongs_to;
        Book::$belongs_to = [['explicit_author', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id']];

        $book = Book::find(1);
        $this->assertEquals(2, $book->secondary_author_id);
        $this->assertEquals($book->secondary_author_id, $book->explicit_author->author_id);

        Book::$belongs_to = $old;
    }

    public function test_belongs_to_with_select()
    {
        Event::$belongs_to[0]['select'] = 'id, city';
        $event = $this->get_relationship();
        $this->assert_default_belongs_to($event);

        try {
            $event->venue->name;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'name'));
        }
    }

    public function test_belongs_to_with_readonly()
    {
        Event::$belongs_to[0]['readonly'] = true;
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

    public function test_belongs_to_with_plural_attribute_name()
    {
        Event::$belongs_to = [['venues', 'class_name' => 'Venue']];
        $this->assert_default_belongs_to($this->get_relationship(), 'venues');
    }

    public function test_belongs_to_with_conditions_and_non_qualifying_record()
    {
        Event::$belongs_to[0]['conditions'] = "state = 'NY'";
        $event = $this->get_relationship();
        $this->assertEquals(5, $event->id);
        $this->assertNull($event->venue);
    }

    public function test_belongs_to_with_conditions_and_qualifying_record()
    {
        Event::$belongs_to[0]['conditions'] = "state = 'PA'";
        $this->assert_default_belongs_to($this->get_relationship());
    }

    public function test_belongs_to_build_association()
    {
        $event = $this->get_relationship();
        $values = ['city' => 'Richmond', 'state' => 'VA'];
        $venue = $event->build_venue($values);
        $this->assertEquals($values, array_intersect_key($values, $venue->attributes()));
    }

    public function test_has_many_build_association()
    {
        $author = Author::first();
        $this->assertEquals($author->id, $author->build_books()->author_id);
        $this->assertEquals($author->id, $author->build_book()->author_id);
    }

    public function test_belongs_to_create_association()
    {
        $event = $this->get_relationship();
        $values = ['city' => 'Richmond', 'state' => 'VA', 'name' => 'Club 54', 'address' => '123 street'];
        $venue = $event->create_venue($values);
        $this->assertNotNull($venue->id);
    }

    public function test_build_association_overwrites_guarded_foreign_keys()
    {
        $author = new AuthorAttrAccessible();
        $author->save();

        $book = $author->build_book();

        $this->assertNotNull($book->author_id);
    }

    public function test_belongs_to_can_be_self_referential()
    {
        Author::$belongs_to = [['parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id']];
        $author = Author::find(1);
        $this->assertEquals(1, $author->id);
        $this->assertEquals(3, $author->parent_author->id);
    }

    public function test_belongs_to_with_an_invalid_option()
    {
        Event::$belongs_to[0]['joins'] = 'venue';
        $event = Event::first()->venue;
        $this->assert_sql_doesnt_has('INNER JOIN venues ON(events.venue_id = venues.id)', Event::table()->last_sql);
    }

    public function test_has_many_with_explicit_class_name()
    {
        Venue::$has_many = [['explicit_class_name', 'class_name' => 'Event', 'order' => 'id asc']];
        $this->assert_default_has_many($this->get_relationship(), 'explicit_class_name');
    }

    public function test_has_many_with_select()
    {
        Venue::$has_many[0]['select'] = 'title, type';
        $venue = $this->get_relationship();
        $this->assert_default_has_many($venue);

        try {
            $venue->events[0]->description;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'description'));
        }
    }

    public function test_has_many_with_readonly()
    {
        Venue::$has_many[0]['readonly'] = true;
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

    public function test_has_many_with_singular_attribute_name()
    {
        Venue::$has_many = [['event', 'class_name' => 'Event', 'order' => 'id asc']];
        $this->assert_default_has_many($this->get_relationship(), 'event');
    }

    public function test_has_many_with_conditions_and_non_qualifying_record()
    {
        Venue::$has_many[0]['conditions'] = "title = 'pr0n @ railsconf'";
        $venue = $this->get_relationship();
        $this->assertEquals(2, $venue->id);
        $this->assertTrue(empty($venue->events), is_array($venue->events));
    }

    public function test_has_many_with_conditions_and_qualifying_record()
    {
        Venue::$has_many[0]['conditions'] = "title = 'Yeah Yeah Yeahs'";
        $venue = $this->get_relationship();
        $this->assertEquals(2, $venue->id);
        $this->assertEquals($venue->events[0]->title, 'Yeah Yeah Yeahs');
    }

    public function test_has_many_with_sql_clause_options()
    {
        Venue::$has_many[0] = ['events',
            'select' => 'type',
            'group'  => 'type',
            'limit'  => 2,
            'offset' => 1];
        Venue::first()->events;
        $this->assert_sql_has($this->connection->limit('SELECT type FROM events WHERE venue_id=? GROUP BY type', 1, 2), Event::table()->last_sql);
    }

    public function test_has_many_through()
    {
        $hosts = Venue::find(2)->hosts;
        $this->assertEquals(2, $hosts[0]->id);
        $this->assertEquals(3, $hosts[1]->id);
    }

    public function test_gh27_has_many_through_with_explicit_keys()
    {
        $property = Property::first();

        $this->assertEquals(1, $property->amenities[0]->amenity_id);
        $this->assertEquals(2, $property->amenities[1]->amenity_id);
    }

    public function test_gh16_has_many_through_inside_a_loop_should_not_cause_an_exception()
    {
        $count = 0;

        foreach (Venue::all() as $venue) {
            $count += count($venue->hosts);
        }

        $this->assertTrue($count >= 5);
    }

    public function test_has_many_through_no_association()
    {
        $this->expectException(HasManyThroughAssociationException::class);
        Event::$belongs_to = [['host']];
        Venue::$has_many[1] = ['hosts', 'through' => 'blahhhhhhh'];

        $venue = $this->get_relationship();
        $n = $venue->hosts;
        $this->assertTrue(count($n) > 0);
    }

    public function test_has_many_through_with_select()
    {
        Event::$belongs_to = [['host']];
        Venue::$has_many[1] = ['hosts', 'through' => 'events', 'select' => 'hosts.*, events.*'];

        $venue = $this->get_relationship();
        $this->assertTrue(count($venue->hosts) > 0);
        $this->assertNotNull($venue->hosts[0]->title);
    }

    public function test_has_many_through_with_conditions()
    {
        Event::$belongs_to = [['host']];
        Venue::$has_many[1] = ['hosts', 'through' => 'events', 'conditions' => ['events.title != ?', 'Love Overboard']];

        $venue = $this->get_relationship();
        $this->assertTrue(1 === count($venue->hosts));
        $this->assert_sql_has('events.title !=', ActiveRecord\Table::load(Host::class)->last_sql);
    }

    public function test_has_many_through_using_source()
    {
        Event::$belongs_to = [['host']];
        Venue::$has_many[1] = ['hostess', 'through' => 'events', 'source' => 'host'];

        $venue = $this->get_relationship();
        $this->assertTrue(count($venue->hostess) > 0);
    }

    public function test_has_many_through_with_invalid_class_name()
    {
        $this->expectException(\ReflectionException::class);
        Event::$belongs_to = [['host']];
        Venue::$has_one = [['invalid_assoc']];
        Venue::$has_many[1] = ['hosts', 'through' => 'invalid_assoc'];

        $this->get_relationship()->hosts;
    }

    public function test_has_many_with_joins()
    {
        $x = Venue::first(['joins' => ['events']]);
        $this->assert_sql_has('INNER JOIN events ON(venues.id = events.venue_id)', Venue::table()->last_sql);
    }

    public function test_has_many_with_explicit_keys()
    {
        $old = Author::$has_many;
        Author::$has_many = [['explicit_books', 'class_name' => 'Book', 'primary_key' => 'parent_author_id', 'foreign_key' => 'secondary_author_id']];
        $author = Author::find(4);

        foreach ($author->explicit_books as $book) {
            $this->assertEquals($book->secondary_author_id, $author->parent_author_id);
        }

        $this->assertTrue(false !== strpos(ActiveRecord\Table::load(Book::class)->last_sql, 'secondary_author_id'));
        Author::$has_many = $old;
    }

    public function test_has_one_basic()
    {
        $this->assert_default_has_one($this->get_relationship());
    }

    public function test_has_one_with_explicit_class_name()
    {
        Employee::$has_one = [['explicit_class_name', 'class_name' => 'Position']];
        $this->assert_default_has_one($this->get_relationship(), 'explicit_class_name');
    }

    public function test_has_one_with_select()
    {
        Employee::$has_one[0]['select'] = 'title';
        $employee = $this->get_relationship();
        $this->assert_default_has_one($employee);

        try {
            $employee->position->active;
            $this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
        } catch (UndefinedPropertyException $e) {
            $this->assertTrue(false !== strpos($e->getMessage(), 'active'));
        }
    }

    public function test_has_one_with_order()
    {
        Employee::$has_one[0]['order'] = 'title';
        $employee = $this->get_relationship();
        $this->assert_default_has_one($employee);
        $this->assert_sql_has('ORDER BY title', Position::table()->last_sql);
    }

    public function test_has_one_with_conditions_and_non_qualifying_record()
    {
        Employee::$has_one[0]['conditions'] = "title = 'programmer'";
        $employee = $this->get_relationship();
        $this->assertEquals(1, $employee->id);
        $this->assertNull($employee->position);
    }

    public function test_has_one_with_conditions_and_qualifying_record()
    {
        Employee::$has_one[0]['conditions'] = "title = 'physicist'";
        $this->assert_default_has_one($this->get_relationship());
    }

    public function test_has_one_with_readonly()
    {
        Employee::$has_one[0]['readonly'] = true;
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

    public function test_has_one_can_be_self_referential()
    {
        Author::$has_one[1] = ['parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id'];
        $author = Author::find(1);
        $this->assertEquals(1, $author->id);
        $this->assertEquals(3, $author->parent_author->id);
    }

    public function test_has_one_with_joins()
    {
        $x = Employee::first(['joins' => ['position']]);
        $this->assert_sql_has('INNER JOIN positions ON(employees.id = positions.employee_id)', Employee::table()->last_sql);
    }

    public function test_has_one_with_explicit_keys()
    {
        Book::$has_one = [['explicit_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id', 'primary_key' => 'secondary_author_id']];

        $book = Book::find(1);
        $this->assertEquals($book->secondary_author_id, $book->explicit_author->parent_author_id);
        $this->assertTrue(false !== strpos(ActiveRecord\Table::load(Author::class)->last_sql, 'parent_author_id'));
    }

    public function test_dont_attempt_to_load_if_all_foreign_keys_are_null()
    {
        $event = new Event();
        $event->venue;
        $this->assert_sql_doesnt_has($this->connection->last_query, 'is IS NULL');
    }

    public function test_relationship_on_table_with_underscores()
    {
        $this->assertEquals(1, Author::find(1)->awesome_person->is_awesome);
    }

    public function test_has_one_through()
    {
        Venue::$has_many = [['events'], ['hosts', 'through' => 'events']];
        $venue = Venue::first();
        $this->assertTrue(count($venue->hosts) > 0);
    }

    public function test_throw_error_if_relationship_is_not_a_model()
    {
        $this->expectException(RelationshipException::class);
        AuthorWithNonModelRelationship::first()->books;
    }

    public function test_gh93_and_gh100_eager_loading_respects_association_options()
    {
        Venue::$has_many = [['events', 'class_name' => 'Event', 'order' => 'id asc', 'conditions' => ['length(title) = ?', 14]]];
        $venues = Venue::find([2, 6], ['include' => 'events']);

        $this->assert_sql_has('WHERE length(title) = ? AND venue_id IN(?,?) ORDER BY id asc', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assertEquals(1, count($venues[0]->events));
    }

    public function test_eager_loading_has_many_x()
    {
        $venues = Venue::find([2, 6], ['include' => 'events']);
        $this->assert_sql_has('WHERE venue_id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);

        foreach ($venues[0]->events as $event) {
            $this->assertEquals($event->venue_id, $venues[0]->id);
        }

        $this->assertEquals(2, count($venues[0]->events));
    }

    public function test_eager_loading_has_many_with_no_related_rows()
    {
        $venues = Venue::find([7, 8], ['include' => 'events']);

        foreach ($venues as $v) {
            $this->assertTrue(empty($v->events));
        }

        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
        $this->assert_sql_has('WHERE venue_id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
    }

    public function test_eager_loading_has_many_array_of_includes()
    {
        Author::$has_many = [['books'], ['awesome_people']];
        $authors = Author::find([1, 2], ['include' => ['books', 'awesome_people']]);

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

    public function test_eager_loading_has_many_nested()
    {
        $venues = Venue::find([1, 2], ['include' => ['events' => ['host']]]);

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

    public function test_eager_loading_belongs_to()
    {
        $events = Event::find([1, 2, 3, 5, 7], ['include' => 'venue']);

        foreach ($events as $event) {
            $this->assertEquals($event->venue_id, $event->venue->id);
        }

        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function test_eager_loading_belongs_to_array_of_includes()
    {
        $events = Event::find([1, 2, 3, 5, 7], ['include' => ['venue', 'host']]);

        foreach ($events as $event) {
            $this->assertEquals($event->venue_id, $event->venue->id);
            $this->assertEquals($event->host_id, $event->host->id);
        }

        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Host::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?,?,?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function test_eager_loading_belongs_to_nested()
    {
        Author::$has_many = [['awesome_people']];

        $books = Book::find([1, 2], ['include' => ['author' => ['awesome_people']]]);

        foreach ($books as $book) {
            $this->assertEquals($book->author_id, $book->author->author_id);
            $this->assertEquals($book->author->author_id, $book->author->awesome_people[0]->author_id);
        }

        $this->assert_sql_has('WHERE book_id IN(?,?)', ActiveRecord\Table::load(Book::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(Author::class)->last_sql);
        $this->assert_sql_has('WHERE author_id IN(?,?)', ActiveRecord\Table::load(AwesomePerson::class)->last_sql);
    }

    public function test_eager_loading_belongs_to_with_no_related_rows()
    {
        $e1 = Event::create(['venue_id' => 200, 'host_id' => 200, 'title' => 'blah', 'type' => 'Music']);
        $e2 = Event::create(['venue_id' => 200, 'host_id' => 200, 'title' => 'blah2', 'type' => 'Music']);

        $events = Event::find([$e1->id, $e2->id], ['include' => 'venue']);

        foreach ($events as $e) {
            $this->assertNull($e->venue);
        }

        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Event::class)->last_sql);
        $this->assert_sql_has('WHERE id IN(?,?)', ActiveRecord\Table::load(Venue::class)->last_sql);
    }

    public function test_eager_loading_clones_related_objects()
    {
        $events = Event::find([2, 3], ['include' => ['venue']]);

        $venue = $events[0]->venue;
        $venue->name = 'new name';

        $this->assertEquals($venue->id, $events[1]->venue->id);
        $this->assertNotEquals($venue->name, $events[1]->venue->name);
        $this->assertNotEquals(spl_object_hash($venue), spl_object_hash($events[1]->venue));
    }

    public function test_eager_loading_clones_nested_related_objects()
    {
        $venues = Venue::find([1, 2, 6, 9], ['include' => ['events' => ['host']]]);

        $unchanged_host = $venues[2]->events[0]->host;
        $changed_host = $venues[3]->events[0]->host;
        $changed_host->name = 'changed';

        $this->assertEquals($changed_host->id, $unchanged_host->id);
        $this->assertNotEquals($changed_host->name, $unchanged_host->name);
        $this->assertNotEquals(spl_object_hash($changed_host), spl_object_hash($unchanged_host));
    }

    public function test_gh_23_relationships_with_joins_to_same_table_should_alias_table_name()
    {
        $old = Book::$belongs_to;
        Book::$belongs_to = [
            ['from_', 'class_name' => 'Author', 'foreign_key' => 'author_id'],
            ['to', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id'],
            ['another', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id']
        ];

        $c = ActiveRecord\Table::load(Book::class)->conn;

        $select = "books.*, authors.name as to_author_name, {$c->quote_name('from_')}.name as from_author_name, {$c->quote_name('another')}.name as another_author_name";
        $book = Book::find(2, ['joins' => ['to', 'from_', 'another'],
            'select' => $select]);

        $this->assertNotNull($book->from_author_name);
        $this->assertNotNull($book->to_author_name);
        $this->assertNotNull($book->another_author_name);
        Book::$belongs_to = $old;
    }

    public function test_gh_40_relationships_with_joins_aliases_table_name_in_conditions()
    {
        $event = Event::find(1, ['joins' => ['venue']]);

        $this->assertEquals($event->id, $event->venue->id);
    }

    public function test_dont_attempt_eager_load_when_record_does_not_exist()
    {
        $this->expectException(RecordNotFound::class);
        Author::find(999999, ['include' => ['books']]);
    }
}
