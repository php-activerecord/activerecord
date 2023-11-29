<?php
/**
 * The base class for your models.
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ActiveRecordException;
use ActiveRecord\Exception\ReadOnlyException;
use ActiveRecord\Exception\RelationshipException;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Relationship\AbstractRelationship;
use ActiveRecord\Relationship\HasAndBelongsToMany;
use ActiveRecord\Relationship\HasMany;
use ActiveRecord\Serialize\JsonSerializer;
use ActiveRecord\Serialize\Serialization;

/**
 * The base class for your models.
 * Defining an ActiveRecord model for a table called people and orders:
 *
 * ```sql
 * CREATE TABLE people(
 *   id int primary key auto_increment,
 *   parent_id int,
 *   first_name varchar(50),
 *   last_name varchar(50)
 * );
 *
 * CREATE TABLE orders(
 *   id int primary key auto_increment,
 *   person_id int not null,
 *   cost decimal(10,2),
 *   total decimal(10,2)
 * );
 * ```
 *
 * ```php
 * class Person extends ActiveRecord\Model {
 *   static array $belongs_to = [
 *      'parent' => [
 *          'foreign_key' => 'parent_id',
 *          'class_name' => 'Person'
 *      ]
 *   ];
 *
 *   static array $has_many = [
 *     'children' => [
 *          'foreign_key' => 'parent_id',
 *          'class_name' => 'Person'
 *      ],
 *      'orders' => true
 *   ];
 *
 *   static $validates_length_of = [
 *     'first_name' => ['within' => [1,50]],
 *     'last_name' => [1,50]
 *   );
 * }
 *
 * class Order extends ActiveRecord\Model {
 *   static array $belongs_to = [
 *     'person' => true
 *   ];
 *
 *   static $validates_numericality_of = [
 *     'cost' => ['greater_than' => 0],
 *     'total' => ['greater_than' => 0]
 *   );
 *
 *   static $before_save = ['calculate_total_with_tax'];
 *
 *   public function calculate_total_with_tax() {
 *     $this->total = $this->cost * 0.045;
 *   }
 * }
 * ```
 *
 * For a more in-depth look at defining models, relationships, callbacks and many other things
 * please consult our {@link http://www.phpactiverecord.org/guides Guides}.
 *
 * @phpstan-import-type HasManyOptions from Types
 * @phpstan-import-type HasAndBelongsToManyOptions from Types
 * @phpstan-import-type BelongsToOptions from Types
 * @phpstan-import-type SerializeOptions from Serialize\Serialization
 * @phpstan-import-type ValidationOptions from Validations
 * @phpstan-import-type ValidateInclusionOptions from Validations
 * @phpstan-import-type ValidateLengthOptions from Validations
 * @phpstan-import-type ValidateFormatOptions from Validations
 * @phpstan-import-type ValidateUniquenessOptions from Validations
 * @phpstan-import-type ValidateNumericOptions from Validations
 *
 * @package ActiveRecord
 *
 * @phpstan-import-type Attributes from Types
 * @phpstan-import-type PrimaryKey from Types
 * @phpstan-import-type QueryOptions from Types
 * @phpstan-import-type DelegateOptions from Types
 * @phpstan-import-type RelationOptions from Types
 *
 * @see BelongsTo
 * @see CallBack
 * @see HasMany
 * @see HasAndBelongsToMany
 * @see Serialization
 * @see Validations
 */
class Model
{
    /**
     * An instance of {@link ValidationErrors} and will be instantiated once a write method is called.
     */
    public ValidationErrors $errors;

    /**
     * Contains model values as column_name => value
     *
     * @var Attributes
     */
    private array $attributes = [];

    /**
     * Flag whether this model's attributes have been modified since it will either be null or an array of column_names that have been modified
     *
     * @var array<string, bool>
     */
    private array $__dirty = [];

    /**
     * Flag that determines of this model can have a writer method invoked such as: save/update/insert/delete
     */
    private bool $__readonly = false;

    /**
     * Array of relationship objects as model_attribute_name => relationship
     *
     * @var array<string, Model|array<Model>|null>
     */
    private array $__relationships = [];

    /**
     * Flag that determines if a call to save() should issue an insert or an update sql statement
     */
    private bool $__new_record = true;

    /**
     * Set to the name of the connection this {@link Model} should use.
     */
    public static string $connection;

    /**
     * Set to the name of the database this Model's table is in.
     */
    public static string $db;

    /**
     * Set this to explicitly specify the model's table name if different from inferred name.
     *
     * If your table doesn't follow our table name convention you can set this to the
     * name of your table to explicitly tell ActiveRecord what your table is called.
     */
    public static string $table_name;

    /**
     * Set this to override the default primary key name if different from default name of "id".
     */
    public static string $primary_key;

    /**
     * Set this to explicitly specify the sequence name for the table.
     */
    public static string $sequence;

    /**
     * Set this to true in your subclass to use caching for this model. Note that you must also configure a cache object.
     */
    public static bool $cache = false;

    /**
     * Set this to specify an expiration period for this model. If not set, the expire value you set in your cache options will be used.
     *
     * @var int
     */
    public static $cache_expire;

    /**
     * @var ValidationOptions
     */
    public static array $validates_presence_of;

    /**
     * @var array<string, ValidateFormatOptions>
     */
    public static array $validates_format_of;

    /**
     * @var array<string,ValidateInclusionOptions>
     */
    public static array $validates_inclusion_of;

    /**
     * @var array<string,ValidateInclusionOptions>
     */
    public static array $validates_exclusion_of;

    /**
     * @var array<string, ValidateUniquenessOptions>
     */
    public static array $validates_uniqueness_of;

    /**
     * @var array<string, ValidateNumericOptions>
     */
    public static array $validates_numericality_of;

    /**
     * @var ValidateLengthOptions
     */
    public static array $validates_length_of;

    /**
     * @var array<string,HasManyOptions>
     */
    public static array $has_many;

    /**
     * @var array<string, HasAndBelongsToManyOptions>
     */
    public static array $has_and_belongs_to_many;

    /**
     * @var array<string,HasManyOptions>
     */
    public static array $has_one;

    /**
     * @var array<string,BelongsToOptions>
     */
    public static array $belongs_to;

    /**
     * Allows you to create aliases for attributes.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *   static $alias_attribute = [
     *     'alias_first_name' => 'first_name',
     *     'alias_last_name' => 'last_name'
     *   ];
     * }
     *
     * $person = Person::first();
     * $person->alias_first_name = 'Tito';
     * echo $person->alias_first_name;
     * ```
     *
     * @var array<string,string>
     */
    public static array $alias_attribute = [];

    /**
     * Whitelist of attributes that are checked from mass-assignment calls such as constructing a model or using update_attributes.
     *
     * This is the opposite of {@link attr_protected $attr_protected}.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *   static $attr_accessible = [
     *     'first_name',
     *     'last_name'
     *   ];
     * }
     *
     * $person = new Person([
     *   'first_name' => 'Tito',
     *   'last_name' => 'the Grief',
     *   'id' => 11111
     * ]);
     *
     * echo $person->id; # => null
     * ```
     *
     * @var list<string>
     */
    public static array $attr_accessible = [];

    /**
     * Blacklist of attributes that cannot be mass-assigned.
     *
     * This is the opposite of {@link attr_accessible $attr_accessible} and the format
     * for defining these are exactly the same.
     *
     * If the attribute is both accessible and protected, it is treated as protected.
     *
     * @var array<string>
     */
    public static array $attr_protected = [];

    /**
     * Delegates calls to a relationship.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *  static array $belongs_to = [
     *      'venue' => true,
     *      'host' => true
     *  ];
     *  static $delegate = [
     *    [
     *      'attributes' => [
     *        'name',
     *        'state'
     *      ],
     *      'to' => 'venue'
     *    ],
     *    [
     *      'attributes' => [
     *        'name'
     *      ],
     *      'to' => 'host',
     *      'prefix' => 'woot'
     *   ];
     * ]
     * ```
     *
     * Can then do:
     *
     * ```
     * $person->state     // same as calling $person->venue->state
     * $person->name      // same as calling $person->venue->name
     * $person->woot_name // same as calling $person->host->name
     * ```
     *
     * @var array<DelegateOptions>
     */
    public static array $delegate = [];

    /**
     * Constructs a model.
     *
     * When a user instantiates a new object (e.g.: it was not ActiveRecord that instantiated via a find)
     * then @var $attributes will be mapped according to the schema's defaults. Otherwise, the given
     * $attributes will be mapped via set_attributes_via_mass_assignment.
     *
     * ```
     * new Person([
     *   'first_name' => 'Tito',
     *   'last_name' => 'the Grief'
     * ]);
     * ```
     *
     * @param Attributes $attributes             Hash containing names and values to mass assign to the model
     * @param bool       $guard_attributes       Set to true to guard protected/non-accessible attributes
     * @param bool       $instantiating_via_find Set to true if this model is being created from a find call
     * @param bool       $new_record             Set to true if this should be considered a new record
     */
    public function __construct(array $attributes = [], bool $guard_attributes = true, bool $instantiating_via_find = false, bool $new_record = true)
    {
        $this->__new_record = $new_record;

        // initialize attributes applying defaults
        if (!$instantiating_via_find) {
            foreach (static::table()->columns as $name => $meta) {
                $this->attributes[$meta->inflected_name] = $meta->default ?? null;
            }
        }

        $this->set_attributes_via_mass_assignment($attributes, $guard_attributes);

        // since all attribute assignment now goes thru assign_attributes() we want to reset
        // dirty if instantiating via find since nothing is really dirty when doing that
        if ($instantiating_via_find) {
            $this->__dirty = [];
        }

        $this->invoke_callback('after_construct', false);
    }

    /**
     * Magic method which delegates to read_attribute(). This handles firing off getter methods,
     * as they are not checked/invoked inside of read_attribute(). This circumvents the problem with
     * a getter being accessed with the same name as an actual attribute.
     *
     * You can also define customer getter methods for the model.
     *
     * EXAMPLE:
     * ```
     * class User extends ActiveRecord\Model {
     *
     *   # define custom getter methods. Note you must
     *   # prepend get_ to your method name:
     *   function get_middle_initial() {
     *     return $this->middle_name{0};
     *   }
     * }
     *
     * $user = new User();
     * echo $user->middle_name;  # will call $user->get_middle_name()
     * ```
     *
     * If you define a custom getter with the same name as an attribute then you
     * will need to use read_attribute() to get the attribute's value.
     * This is necessary due to the way __get() works.
     *
     * For example, assume 'name' is a field on the table and we're defining a
     * custom getter for 'name':
     *
     * ```
     * class User extends ActiveRecord\Model {
     *
     *   # INCORRECT way to do it
     *   # function get_name() {
     *   #   return strtoupper($this->name);
     *   # }
     *
     *   function get_name() {
     *     return strtoupper($this->read_attribute('name'));
     *   }
     * }
     *
     * $user = new User();
     * $user->name = 'bob';
     * echo $user->name; # => BOB
     * ```
     *
     * @see read_attribute()
     *
     * @param string $name Name of an attribute
     *
     * @return mixed The value of the attribute
     */
    public function &__get($name)
    {
        // check for getter
        $name = strtolower($name);
        if (method_exists($this, "get_$name")) {
            $name = "get_$name";
            $callable = [$this, $name];
            assert(is_callable($callable));
            $res = call_user_func($callable);

            return $res;
        }

        return $this->read_attribute($name);
    }

    /**
     * Determines if an attribute exists for this {@link Model}.
     *
     * @param string $attribute_name
     *
     * @return bool
     */
    public function __isset($attribute_name)
    {
        // check for aliased attribute
        if (array_key_exists($attribute_name, static::$alias_attribute)) {
            return true;
        }

        // check for attribute
        if (array_key_exists($attribute_name, $this->attributes)) {
            return true;
        }

        // check for getters
        if (method_exists($this, "get_{$attribute_name}")) {
            return true;
        }

        // check for relationships
        if (static::table()->has_relationship($attribute_name)) {
            return true;
        }

        return false;
    }

    /**
     * Magic allows un-defined attributes to set via $attributes.
     *
     * You can also define customer setter methods for the model.
     *
     * EXAMPLE:
     * ```
     * class User extends ActiveRecord\Model {
     *
     *   # define custom setter methods. Note you must
     *   # prepend set_ to your method name:
     *   function set_password($plaintext) {
     *     $this->encrypted_password = md5($plaintext);
     *   }
     * }
     *
     * $user = new User();
     * $user->password = 'plaintext';  # will call $user->set_password('plaintext')
     * ```
     *
     * If you define a custom setter with the same name as an attribute then you
     * will need to use assign_attribute() to assign the value to the attribute.
     * This is necessary due to the way __set() works.
     *
     * For example, assume 'name' is a field on the table and we're defining a
     * custom setter for 'name':
     *
     * ```
     * class User extends ActiveRecord\Model {
     *
     *   // INCORRECT way to do it
     *   // function set_name($name) {
     *   //   $this->name = strtoupper($name);
     *   // }
     *
     *   function set_name($name) {
     *     $this->assign_attribute('name',strtoupper($name));
     *   }
     * }
     *
     * $user = new User();
     * $user->name = 'bob';
     * echo $user->name; # => BOB
     * ```
     *
     * @param $name  string Name of attribute, relationship or other to set
     * @param $value mixed The value
     *
     * @throws UndefinedPropertyException if $name does not exist
     */
    public function __set(string $name, mixed $value): void
    {
        $name = strtolower($name);
        if (array_key_exists($name, static::$alias_attribute)) {
            $name = static::$alias_attribute[$name];
        } elseif (method_exists($this, "set_$name")) {
            $name = "set_$name";
            $this->$name($value);

            return;
        }

        if (array_key_exists($name, $this->attributes)) {
            $this->assign_attribute($name, $value);

            return;
        }

        if ('id' == $name) {
            $this->assign_attribute($this->get_primary_key(), $value);

            return;
        }

        foreach (static::$delegate as $key => $item) {
            if ($delegated_name = $this->is_delegated($name, $item)) {
                $this->{$item['to']}->$delegated_name = $value;

                return;
            }
        }

        throw new UndefinedPropertyException(get_called_class(), $name);
    }

    /**
     * Magic method; this gets called by PHP itself when your model is unserialized.
     */
    public function __wakeup()
    {
        // make sure the models Table instance gets initialized when waking up
        static::table();
    }

    /**
     * Assign a value to an attribute.
     *
     * @param string $name  Name of the attribute
     * @param mixed  $value Value of the attribute
     *
     * @return mixed the attribute value
     */
    public function assign_attribute($name, $value)
    {
        $table = static::table();
        if (!is_object($value)) {
            if (array_key_exists($name, $table->columns)) {
                $value = $table->columns[$name]->cast($value, static::connection());
            } else {
                $col = $table->get_column_by_inflected_name($name);
                if (!is_null($col)) {
                    $value = $col->cast($value, static::connection());
                }
            }
        }

        // convert php's \DateTime to ours
        if ($value instanceof \DateTime) {
            $date_class = Config::instance()->get_date_class();
            if (!($value instanceof $date_class)) {
                $value = $date_class::createFromFormat(
                    Connection::DATETIME_TRANSLATE_FORMAT,
                    $value->format(Connection::DATETIME_TRANSLATE_FORMAT),
                    $value->getTimezone()
                );
            }
        }

        if ($value instanceof DateTimeInterface) {
            // Tell the Date object that it's associated with this model and attribute. This is so it
            // has the ability to flag this model as dirty if a field in the Date object changes.
            $value->attribute_of($this, $name);
        }

        $this->attributes[$name] = $value;
        $this->flag_dirty($name);

        return $value;
    }

    /**
     * Retrieves an attribute's value or a relationship object based on the name passed. If the attribute
     * accessed is 'id' then it will return the model's primary key no matter what the actual attribute name is
     * for the primary key.
     *
     * @param $name Name of an attribute
     *
     * @throws UndefinedPropertyException if name could not be resolved to an attribute, relationship, ...
     *
     * @return mixed The value of the attribute
     */
    public function &read_attribute(string $name)
    {
        // check for aliased attribute
        if (array_key_exists($name, static::$alias_attribute)) {
            $name = static::$alias_attribute[$name];
        }

        // check for attribute
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        // check relationships if no attribute
        if (array_key_exists($name, $this->__relationships)) {
            return $this->__relationships[$name];
        }

        $table = static::table();

        // this may be first access to the relationship so check Table
        if ($table->get_relationship($name)) {
            $res = $this->initRelationships($name);

            return $res;
        }

        if ('id' == $name) {
            $pk = $this->get_primary_key();
            if (isset($this->attributes[$pk])) {
                return $this->attributes[$pk];
            }
        }

        // do not remove - have to return null by reference in strict mode
        $null = null;

        foreach (static::$delegate as $delegateName => $item) {
            if ($delegated_name = $this->is_delegated($name, $item)) {
                $to = $item['to'];
                if ($this->$to) {
                    $val = &$this->$to->__get($delegated_name);

                    return $val;
                }

                return $null;
            }
        }

        throw new UndefinedPropertyException(get_called_class(), $name);
    }

    /**
     * @throws RelationshipException
     *
     * @return Model|array<Model>|null
     */
    protected function initRelationships(string $name): Model|array|null
    {
        $table = static::table();
        $relationship = $table->get_relationship($name);
        if (null !== $relationship) {
            $this->__relationships[$name] = $relationship->load($this);
        }

        return $this->__relationships[$name] ?? null;
    }

    /**
     * Flags an attribute as dirty.
     */
    public function flag_dirty(string $attribute): void
    {
        $this->__dirty[$attribute] = true;
    }

    /**
     * Returns hash of attributes that have been modified since loading the model.
     *
     * @return Attributes
     */
    public function dirty_attributes(): array
    {
        if (count($this->__dirty) <= 0) {
            return [];
        }

        return array_intersect_key($this->attributes, $this->__dirty);
    }

    /**
     * Check if a particular attribute has been modified since loading the model.
     *
     * @param string $attribute Name of the attribute
     *
     * @return bool tRUE if it has been modified
     */
    public function attribute_is_dirty($attribute)
    {
        return $this->__dirty && isset($this->__dirty[$attribute]) && array_key_exists($attribute, $this->attributes);
    }

    /**
     * Returns a copy of the model's attributes hash.
     *
     * @return Attributes A copy of the model's attribute data
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function get_primary_key(): string
    {
        $pk = static::table()->pk;

        return $pk[0];
    }

    /**
     * Returns the actual attribute name if $name is aliased.
     */
    public function get_real_attribute_name(string $name): ?string
    {
        if (array_key_exists($name, $this->attributes)) {
            return $name;
        }

        if (array_key_exists($name, static::$alias_attribute)) {
            return static::$alias_attribute[$name];
        }

        return null;
    }

    /**
     * Returns array of validator data for this Model.
     *
     * Will return an array looking like:
     *
     * ```
     * [
     *   'name' => [
     *     [
     *       'validator' => 'validates_presence_of'
     *     ],
     *     [
     *       'validator' => 'validates_inclusion_of',
     *       'in' => ['Bob','Joe','John']
     *     ]
     *   ],
     *   'password' => [
     *     [
     *       'validator' => 'validates_length_of',
     *       'minimum' => 6
     *     ]
     *   ]
     * ];
     * ```
     *
     * @return array<string, array<mixed>> an array containing validator data for this model
     */
    public function get_validation_rules(): array
    {
        $validator = new Validations($this);

        return $validator->rules();
    }

    /**
     * Returns an associative array containing values for all the attributes in $attributes
     *
     * @param list<string> $attributes Array containing attribute names
     *
     * @return Attributes A hash containing $name => $value
     */
    public function get_values_for(array $attributes): array
    {
        $ret = [];

        foreach ($attributes as $name) {
            if (array_key_exists($name, $this->attributes)) {
                $ret[$name] = $this->attributes[$name];
            }
        }

        return $ret;
    }

    /**
     * Retrieves the name of the table for this Model.
     *
     * @return string
     */
    public static function table_name()
    {
        return static::table()->table;
    }

    /**
     * Returns the attribute name on the delegated relationship if $name is
     * delegated or null if not delegated.
     *
     * @param string          $name     Name of an attribute
     * @param DelegateOptions $delegate An array containing delegate data
     */
    private function is_delegated(string $name, array $delegate): string|null
    {
        if (is_array($delegate)) {
            if (!empty($delegate['prefix'])) {
                $name = substr($name, strlen($delegate['prefix']) + 1);
            }

            if (in_array($name, $delegate['delegate'])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Determine if the model is in read-only mode.
     */
    public function is_readonly(): bool
    {
        return $this->__readonly;
    }

    /**
     * Determine if the model is a new record.
     */
    public function is_new_record(): bool
    {
        return $this->__new_record;
    }

    /**
     * Throws an exception if this model is set to readonly.
     *
     * @param string $method_name Name of method that was invoked on model for exception message
     *
     * @throws ReadOnlyException
     */
    private function verify_not_readonly(string $method_name): void
    {
        if ($this->is_readonly()) {
            throw new ReadOnlyException(get_class($this), $method_name);
        }
    }

    /**
     * Retrieve the connection for this model.
     *
     * @return Connection
     */
    public static function connection()
    {
        return static::table()->conn;
    }

    /**
     * Re-establishes the database connection with a new connection.
     *
     * @return Connection
     */
    public static function reestablish_connection()
    {
        return static::table()->reestablish_connection();
    }

    /**
     * Returns the {@link Table} object for this model.
     *
     * Be sure to call in static scoping: static::table()
     *
     * @return Table
     */
    protected static function table()
    {
        $table = Table::load(get_called_class());

        return $table;
    }

    /**
     * Creates a model and saves it to the database.
     *
     * @param Attributes $attributes       Array of the models attributes
     * @param bool       $validate         True if the validators should be run
     * @param bool       $guard_attributes Set to true to guard protected/non-accessible attributes
     */
    public static function create(array $attributes, bool $validate = true, bool $guard_attributes = true): static
    {
        $class_name = get_called_class();
        $model = new $class_name($attributes, $guard_attributes);
        $model->save($validate);

        return $model;
    }

    /**
     * Save the model to the database.
     *
     * This function will automatically determine if an INSERT or UPDATE needs to occur.
     * If a validation or a callback for this model returns false, then the model will
     * not be saved and this will return false.
     *
     * If saving an existing model only data that has changed will be saved.
     *
     * @param bool $validate Set to true or false depending on if you want the validators to run or not
     *
     * @return bool True if the model was saved to the database otherwise false
     */
    public function save($validate = true)
    {
        $this->verify_not_readonly('save');

        return $this->is_new_record() ? $this->insert($validate) : $this->update($validate);
    }

    /**
     * Issue an INSERT sql statement for this model's attribute.
     *
     * @see save
     *
     * @param bool $validate Set to true or false depending on if you want the validators to run or not
     *
     * @return bool True if the model was saved to the database otherwise false
     */
    private function insert($validate = true)
    {
        $this->verify_not_readonly('insert');

        if ($validate && !$this->_validate() || !$this->invoke_callback('before_create', false)) {
            return false;
        }

        $table = static::table();

        if (!($attributes = $this->dirty_attributes())) {
            $attributes = $this->attributes;
        }

        $pk = $this->get_primary_key();
        $use_sequence = false;

        if (!empty($table->sequence) && !isset($attributes[$pk])) {
            // unset pk that was set to null
            if (array_key_exists($pk, $attributes)) {
                unset($attributes[$pk]);
            }

            $table->insert($attributes, $pk, $table->sequence);
            $use_sequence = true;
        } else {
            $table->insert($attributes);
        }

        $pk = strtolower($pk);
        $column = $table->get_column_by_inflected_name($pk);

        if (isset($column) && ($column->auto_increment || $use_sequence)) {
            $this->attributes[$pk] = $column->cast(static::connection()->insert_id($table->sequence ?? null), static::connection());
        }

        $this->__new_record = false;
        $this->invoke_callback('after_create', false);

        $this->update_cache();

        return true;
    }

    /**
     * Issue an UPDATE sql statement for this model's dirty attributes.
     *
     * @see save
     *
     * @param bool $validate Set to true or false depending on if you want the validators to run or not
     *
     * @return bool True if the model was saved to the database otherwise false
     */
    private function update(bool $validate = true)
    {
        $this->verify_not_readonly('update');

        if ($validate && !$this->_validate()) {
            return false;
        }

        if ($this->is_dirty()) {
            $pk = $this->values_for_pk();

            if (empty($pk)) {
                throw new ActiveRecordException('Cannot update, no primary key defined for: ' . get_called_class());
            }
            if (!$this->invoke_callback('before_update', false)) {
                return false;
            }

            $attributes = $this->dirty_attributes();

            $options = [
                'conditions' => [
                    new WhereClause([$this->table()->pk[0] => $pk], [])
                ]
            ];

            static::table()->update($attributes, $options);
            $this->invoke_callback('after_update', false);
            $this->update_cache();
        }

        return true;
    }

    /**
     * If individual caching is enabled, this method will re-cache the current state of this model instance.
     *
     * @protected
     */
    protected function update_cache(): void
    {
        $table = static::table();
        if (!empty($table->cache_individual_model)) {
            Cache::set($this->cache_key(), $this, $table->cache_model_expire);
        }
    }

    /**
     * Generates a unique key for caching.
     *
     * @protected
     *
     * @return string
     */
    protected function cache_key()
    {
        $table = static::table();

        return $table->cache_key_for_model($this->values_for_pk());
    }

    /**
     * @see Relation::delete_all()
     */
    public static function delete_all(): int
    {
        return static::Relation()->delete_all();
    }

    /**
     * @param string|Attributes $attributes
     *
     * @see Relation::update_all()
     */
    public static function update_all(string|array $attributes): int
    {
        return static::Relation()->update_all($attributes);
    }

    /**
     * Deletes this model from the database and returns true if successful.
     *
     * @return bool
     */
    public function delete()
    {
        $this->verify_not_readonly('delete');

        $pk = $this->values_for_pk();

        if (empty($pk)) {
            throw new ActiveRecordException('Cannot delete, no primary key defined for: ' . get_called_class());
        }
        if (!$this->invoke_callback('before_destroy', false)) {
            return false;
        }

        $options = [
            'conditions' => [
                new WhereClause($pk, [])
            ]
        ];

        static::table()->delete($options);
        $this->invoke_callback('after_destroy', false);
        $this->remove_from_cache();

        return true;
    }

    /**
     * Removes this individual from cache.
     */
    public function remove_from_cache(): void
    {
        $table = static::table();
        if ($table->cache_individual_model) {
            Cache::delete($this->cache_key());
        }
    }

    /**
     * Helper that creates an array of values for the primary key(s).
     *
     * @return Attributes
     */
    public function values_for_pk(): array
    {
        return $this->values_for(static::table()->pk);
    }

    /**
     * Helper to return a hash of values for the specified attributes.
     *
     * @param array<string> $attribute_names Array of attribute names
     *
     * @return Attributes An array in the form [name => value, ...]
     */
    public function values_for(array $attribute_names): array
    {
        $filter = [];

        foreach ($attribute_names as $name) {
            $filter[$name] = $this->$name;
        }

        return $filter;
    }

    /**
     * Validates the model.
     */
    private function _validate(): bool
    {
        $validator = new Validations($this);
        $validation_on = 'validation_on_' . ($this->is_new_record() ? 'create' : 'update');

        foreach (['before_validation', "before_$validation_on"] as $callback) {
            if (!$this->invoke_callback($callback, false)) {
                return false;
            }
        }

        // need to store reference b4 validating so that custom validators have access to add errors
        $this->errors = $validator->get_errors();
        $validator->validate();

        foreach (['after_validation', "after_$validation_on"] as $callback) {
            $this->invoke_callback($callback, false);
        }

        if (!$this->errors->is_empty()) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the model has been modified.
     */
    public function is_dirty(): bool
    {
        return !empty($this->__dirty);
    }

    /**
     * Run validations on model and returns whether model passed validation.
     *
     * @see is_invalid
     */
    public function is_valid(): bool
    {
        return $this->_validate();
    }

    /**
     * Runs validations and returns true if invalid.
     *
     * @see is_valid
     */
    public function is_invalid(): bool
    {
        return !$this->_validate();
    }

    /**
     * Updates a model's timestamps.
     */
    public function set_timestamps(): void
    {
        $now = date('Y-m-d H:i:s');

        if (isset($this->updated_at)) {
            $this->updated_at = $now;
        }

        if (isset($this->created_at) && $this->is_new_record()) {
            $this->created_at = $now;
        }
    }

    /**
     * Mass update the model with an array of attribute data and saves to the database.
     *
     * @param Attributes $attributes An attribute data array in the form [name => value, ...]
     *
     * @return bool True if successfully updated and saved otherwise false
     */
    public function update_attributes(array $attributes): bool
    {
        $this->set_attributes($attributes);

        return $this->save();
    }

    /**
     * Updates a single attribute and saves the record without going through the normal validation procedure.
     *
     * @param string $name  Name of attribute
     * @param mixed  $value Value of the attribute
     *
     * @return bool True if successful otherwise false
     */
    public function update_attribute($name, $value)
    {
        $this->__set($name, $value);

        return $this->update(false);
    }

    /**
     * Mass update the model with data from an attributes hash.
     *
     * Unlike update_attributes() this method only stores the model's data in memory
     * but DOES NOT save it to the database.
     *
     * @see update_attributes
     *
     * @param Attributes $attributes An array containing data to update in the form of [name => value, ...]
     */
    public function set_attributes(array $attributes): void
    {
        $this->set_attributes_via_mass_assignment($attributes, true);
    }

    /**
     * Passing $guard_attributes as true will throw an exception if an attribute does not exist.
     *
     * @param Attributes $attributes       An array in the form [name => value, ...]
     * @param bool       $guard_attributes Whether protected/non-accessible attributes should be guarded
     *
     * @throws UndefinedPropertyException
     */
    private function set_attributes_via_mass_assignment(array &$attributes, bool $guard_attributes): void
    {
        // access uninflected columns since that is what we would have in result set
        $table = static::table();
        $exceptions = [];
        $use_attr_accessible = !empty(static::$attr_accessible);
        $use_attr_protected = !empty(static::$attr_protected);
        $connection = static::connection();

        foreach ($attributes as $name => $value) {
            // is a normal field on the table
            if (array_key_exists($name, $table->columns)) {
                $value = $table->columns[$name]->cast($value, $connection);
                $name = $table->columns[$name]->inflected_name;
            }

            if ($guard_attributes) {
                if ($use_attr_accessible && !in_array($name, static::$attr_accessible)) {
                    continue;
                }

                if ($use_attr_protected && in_array($name, static::$attr_protected)) {
                    continue;
                }

                // set valid table data
                try {
                    $this->$name = $value;
                } catch (UndefinedPropertyException $e) {
                    $exceptions[] = $e->getMessage();
                }
            } else {
                // set arbitrary data
                $this->assign_attribute($name, $value);
            }
        }

        if (!empty($exceptions)) {
            throw new UndefinedPropertyException(get_called_class(), $exceptions);
        }
    }

    /**
     * Add a model to the given named ($name) relationship.
     *
     * @internal This should <strong>only</strong> be used by eager load
     */
    public function set_relationship_from_eager_load(?Model $model, string $name): void
    {
        $table = static::table();

        if ($rel = $table->get_relationship($name)) {
            if ($rel->is_poly()) {
                // if the related model is null and a poly then we should have an empty array
                if (is_null($model)) {
                    $this->__relationships[$name] = [];

                    return;
                }
                $this->__relationships[$name] ??= [];
                assert(is_array($this->__relationships[$name]));
                array_push($this->__relationships[$name], $model);

                return;
            }

            $this->__relationships[$name] = $model;

            return;
        }

        throw new RelationshipException("Relationship named $name has not been declared for class: {$table->class->getName()}");
    }

    /**
     * Reloads the attributes and relationships of this object from the database.
     *
     * @return Model
     */
    public function reload()
    {
        $this->remove_from_cache();

        $this->__relationships = [];
        $model = $this->find($this->{static::table()->pk[0]});
        assert($model instanceof static);
        $this->set_attributes_via_mass_assignment($model->attributes, false);
        $this->reset_dirty();

        return $this;
    }

    /**
     * Magic Method. Called when cloning a model.
     */
    public function __clone(): void
    {
        $this->__relationships = [];
        $this->reset_dirty();
    }

    /**
     * Resets the dirty array.
     *
     * @see dirty_attributes
     */
    public function reset_dirty(): void
    {
        $this->__dirty = [];
    }

    /**
     * Enables the use of dynamic finders.
     *
     * @see Relation::__call()
     */
    public static function __callStatic(string $method, mixed $args): static|null
    {
        return static::Relation()->$method(...$args);
    }

    /**
     * Enables the use of build|create for associations.
     *
     * @return mixed An instance of a given {@link AbstractRelationship}
     */
    public function __call(string $method, mixed $args)
    {
        // check for build|create_association methods
        if (preg_match('/(build|create)_/', $method)) {
            if (!empty($args)) {
                $args = $args[0];
            }

            $association_name = str_replace(['build_', 'create_'], '', $method);
            $method = str_replace($association_name, 'association', $method);
            $table = static::table();

            if (($association = $table->get_relationship($association_name))
                || ($association = $table->get_relationship($association_name = Utils::pluralize($association_name)))) {
                // access association to ensure that the relationship has been loaded
                // so that we do not double-up on records if we append a newly created
                $this->initRelationships($association_name);

                return match ($method) {
                    'build_association' => $association->build_association($this, $args),
                    default => $association->create_association($this, $args),
                };
            }
        }

        throw new ActiveRecordException("Call to undefined method: $method");
    }

    /**
     * @return Relation<static>
     *
     *@see Relation::select()
     */
    public static function select(): Relation
    {
        return static::Relation()->select(...func_get_args());
    }

    /**
     * @return Relation<static>
     *
     *@see Relation::distinct()
     */
    public static function distinct(bool $distinct = true): Relation
    {
        return static::Relation()->distinct($distinct);
    }

    /**
     * @return Relation<static>
     *
     *@see Relation::reselect()
     */
    public static function reselect(): Relation
    {
        return static::Relation()->reselect(...func_get_args());
    }

    /**
     * @see Relation::readonly()
     *
     * @return Relation<static>
     */
    public static function readonly(bool $readonly = true): Relation
    {
        return static::Relation()->readonly($readonly);
    }

    public function set_read_only(bool $readonly = true): void
    {
        $this->__readonly = $readonly;
    }

    /**
     * @see Relation::joins()
     *
     * @param string|array<string> $joins
     *
     * @return Relation<static>
     */
    public static function joins(string|array $joins): Relation
    {
        return static::Relation()->joins($joins);
    }

    /**
     * @see Relation::order()
     *
     * @return Relation<static>
     */
    public static function order(string $order): Relation
    {
        return static::Relation()->order($order);
    }

    /**
     * @see Relation::group()
     *
     * @return Relation<static>
     */
    public static function group(): Relation
    {
        return static::Relation()->group(...func_get_args());
    }

    /**
     * @see Relation::limit()
     *
     * @return Relation<static>
     */
    public static function limit(int $limit): Relation
    {
        return static::Relation()->limit($limit);
    }

    /**
     * @see Relation::having()
     *
     * @return Relation<static>
     */
    public static function offset(int $offset): Relation
    {
        return static::Relation()->offset($offset);
    }

    /**
     * @see Relation::having()
     *
     * @return Relation<static>
     */
    public static function having(string $having): Relation
    {
        return static::Relation()->having($having);
    }

    /**
     * @see Relation::having()
     *
     * @return Relation<static>
     */
    public static function from(string $from): Relation
    {
        return static::Relation()->from($from);
    }

    /**
     * @return Relation<static>
     */
    public static function includes(): Relation
    {
        return static::Relation()->includes(...func_get_args());
    }

    /**
     * @see Relation::where()
     *
     * @return Relation<static>
     */
    public static function where(): Relation
    {
        return static::Relation()->where(...func_get_args());
    }

    /**
     * @see Relation::not()
     *
     * @return Relation<static>
     */
    public static function not(): Relation
    {
        return static::Relation()->not(...func_get_args());
    }

    /**
     * @see Relation::all()
     *
     * @return Relation<static>
     */
    public static function all(): Relation
    {
        return static::Relation()->all();
    }

    /**
     * @see Relation::none()
     *
     * @return Relation<static>
     */
    public static function none(): Relation
    {
        return static::Relation()->none();
    }

    /**
     * @param RelationOptions $options
     *
     * @return Relation<static>
     */
    protected static function Relation(array $options = []): Relation
    {
        /**
         * @var Relation<static> $rel
         */
        $rel = new Relation(get_called_class(), static::$alias_attribute, $options);

        return $rel;
    }

    /**
     * Get a count of qualifying records.
     *
     * ```
     * YourModel::count('amount > 3.14159265');
     * YourModel::count(['name' => 'Tito', 'author_id' => 1]));
     * YourModel::count();
     * ```
     *
     * @see find
     *
     * @return int Number of records that matched the query
     */
    public static function count(): int
    {
        return static::Relation()->count();
    }

    /**
     * Determine if a record exists.
     *
     * @see Relation::exists()
     */
    public static function exists(mixed $conditions = []): bool
    {
        return static::Relation()->exists($conditions);
    }

    /**
     * @see Relation::pluck()
     *
     * @return array<static>
     */
    public static function pluck(): array
    {
        return static::Relation()->pluck(...func_get_args());
    }

    /**
     * @see Relation::ids()
     *
     * @return array<mixed>
     */
    public static function ids(): array
    {
        return static::Relation()->ids();
    }

    /**
     * @see Relation::take()
     *
     * @return static|array<static>|null
     */
    public static function take(int $limit = null): static|array|null
    {
        return static::Relation()->take($limit);
    }

    /**
     * @see Relation::first()
     *
     * @return static|array<static>|null
     */
    public static function first(int $limit = null): static|array|null
    {
        return static::Relation()->first($limit);
    }

    /**
     * @see Relation::last()
     *
     * @return static|array<static>|null
     */
    public static function last(int $limit = null): static|array|null
    {
        return static::Relation()->last($limit);
    }

    /**
     * @see Relation::find()
     *
     * @return static|array<static>
     */
    public static function find(/* $pk */): Model|array
    {
        return static::Relation()->find(...func_get_args());
    }

    /**
     * Find using a raw SELECT query.
     *
     * ```
     * YourModel::find_by_sql("SELECT * FROM people WHERE name=?",['Tito']);
     * YourModel::find_by_sql("SELECT * FROM people WHERE name='Tito'");
     * ```
     *
     * @param string       $sql    The raw SELECT query
     * @param array<mixed> $values An array of values for any parameters that needs to be bound
     *
     * @return array<static> An array of models
     */
    public static function find_by_sql(string $sql, array $values = []): array
    {
        /**
         * @var array<static> $items
         */
        $items = iterator_to_array(static::table()->find_by_sql($sql, $values, true));

        return $items;
    }

    /**
     * Helper method to run arbitrary queries against the model's database connection.
     *
     * @param string       $sql    SQL to execute
     * @param array<mixed> $values Bind values, if any, for the query
     */
    public static function query(string $sql, array $values = []): \PDOStatement
    {
        return static::connection()->query($sql, $values);
    }

    /**
     * Returns a JSON representation of this model.
     *
     * @param SerializeOptions $options
     *
     * @return string JSON representation of the model
     */
    public function to_json(array $options = [])
    {
        return $this->serialize('Json', $options);
    }

    /**
     * Returns an XML representation of this model.
     *
     * @see Serialization
     *
     * @param SerializeOptions $options An array containing options for xml serialization (see {@link Serialization} for valid options)
     *
     * @return string XML representation of the model
     */
    public function to_xml(array $options = [])
    {
        return $this->serialize('Xml', $options);
    }

    /**
     * Returns an CSV representation of this model.
     * Can take optional delimiter and enclosure
     * (defaults are , and double quotes)
     *
     * Ex:
     * ```
     * ActiveRecord\CsvSerializer::$delimiter=';';
     * ActiveRecord\CsvSerializer::$enclosure='';
     * YourModel::find('first')->to_csv(['only'=>['name','level']]);
     * returns: Joe,2
     *
     * YourModel::find('first')->to_csv(['only_header'=>true,'only' => ['name','level']]);
     * returns: name,level
     * ```
     *
     * @param SerializeOptions $options an array containing options for csv serialization
     *
     * @return string CSV representation of the model
     */
    public function to_csv(array $options = []): string
    {
        return $this->serialize('Csv', $options);
    }

    /**
     * Returns an Array representation of this model.
     *
     * @see Serialization
     *
     * @param SerializeOptions $options
     *
     * @return Attributes|array<class-string|Attributes> Array representation of the model
     */
    public function to_array(array $options = []): array
    {
        $serializer = new JsonSerializer($this, $options);

        return !empty($options['include_root']) ? [strtolower(get_class($this)) => $serializer->to_a()] : $serializer->to_a();
    }

    /**
     * Creates a serializer based on pre-defined to_serializer()
     *
     * @param string           $type    Either Xml, Json, Csv or Array
     * @param SerializeOptions $options Options array for the serializer
     */
    private function serialize(string $type, array $options): string
    {
        $class = 'ActiveRecord\\Serialize\\' . $type . 'Serializer';
        $serializer = new $class($this, $options);

        assert($serializer instanceof Serialization);

        return $serializer->to_s();
    }

    /**
     * Invokes the specified callback on this model.
     *
     * @param string $method_name name of the call back to run
     * @param bool   $must_exist  set to true to raise an exception if the callback does not exist
     *
     * @throws ActiveRecordException
     *
     * @return bool True if invoked or null if not
     */
    private function invoke_callback(string $method_name, bool $must_exist = true)
    {
        return static::table()->callback->invoke($this, $method_name, $must_exist);
    }

    /**
     * Executes a block of code inside a database transaction.
     *
     * ```
     * YourModel::transaction(function()
     * {
     *   YourModel::create(["name" => "blah"]);
     * });
     * ```
     *
     * If an exception is thrown inside the closure the transaction will
     * automatically be rolled back. You can also return false from your
     * closure to cause a rollback:
     *
     * ```
     * YourModel::transaction(function()
     * {
     *   YourModel::create(["name" => "blah"]);
     *   throw new Exception("rollback!");
     * });
     *
     * YourModel::transaction(function()
     * {
     *   YourModel::create(["name" => "blah"]);
     *   return false; # rollback!
     * });
     * ```
     *
     * @param callable $closure The closure to execute. To cause a rollback have your closure return false or throw an exception.
     *
     * @return bool true if the transaction was committed, False if rolled back
     */
    public static function transaction($closure)
    {
        $connection = static::connection();

        try {
            $connection->transaction();

            if (false === $closure()) {
                $connection->rollback();

                return false;
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }

        return true;
    }
}
