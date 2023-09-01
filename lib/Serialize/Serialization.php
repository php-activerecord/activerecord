<?php

namespace ActiveRecord\Serialize;

use ActiveRecord\Config;
use ActiveRecord\Exception\UndefinedPropertyException;
use ActiveRecord\Model;
use ActiveRecord\Types;

/**
 * Base class for Model serializers.
 *
 * All serializers support the following options:
 *
 * @phpstan-type SerializeOptions array{
 *      only?:  string|string[],
 *      except?: string|string[],
 *      methods?: string|string[],
 *      include?: array<mixed>,
 *      only_method?: string,
 *      only_header?: string,
 *      skip_instruct? :bool,
 *      include_root?: bool
 *  }
 *
 * Example usage:
 *
 * ```
 * # include the attributes id and name
 * # run $model->encoded_description() and include its return value
 * # include the comments association
 * # include posts association with its own options (nested)
 * $model->to_json([
 *   'only' => ['id','name', 'encoded_description'],
 *   'methods' => ['encoded_description'],
 *   'include' => ['comments', 'posts' => ['only' => 'id']]
 * ]);
 *
 * # except the password field from being included
 * $model->to_xml(['except' => 'password']);
 * ```
 *
 * @phpstan-import-type Attributes from Types
 *
 * @see http://www.phpactiverecord.org/guides/utilities#topic-serialization
 */
abstract class Serialization
{
    protected Model $model;
    /**
     * @var SerializeOptions
     */
    protected array $options;

    /**
     * @var Attributes
     */
    protected array $attributes = [];

    /**
     * The default format to serialize DateTime objects to.
     *
     * @see DateTime
     */
    public static string $DATETIME_FORMAT = 'iso8601';

    /**
     * Set this to true if the serializer needs to create a nested array keyed
     * on the name of the included classes such as for xml serialization.
     *
     * Setting this to true will produce the following attributes array when
     * the include option was used:
     *
     * ```
     * $user = [
     *  'id' => 1,
     *  'name' => 'Tito',
     *  'permissions' => [
     *     'permission' => [
     *       ['id' => 100, 'name' => 'admin'],
     *       ['id' => 101, 'name' => 'normal']
     *     ]
     *   ]
     * ];
     * ```
     *
     * Setting to false will produce this:
     *
     * ```
     * $user = array('id' => 1, 'name' => 'Tito',
     *   'permissions' => array(
     *     array('id' => 100, 'name' => 'admin'),
     *     array('id' => 101, 'name' => 'normal')
     *   )
     * );
     * ```
     */
    protected bool $includes_with_class_name_element = false;

    /**
     * Constructs a {@link Serialization} object.
     *
     * @param Model            $model   The model to serialize
     * @param SerializeOptions $options Options for serialization
     */
    public function __construct(Model $model, $options)
    {
        $this->model = $model;
        $this->options = $options;
        $this->attributes = $model->attributes();
        $this->parse_options();
    }

    private function parse_options(): void
    {
        $this->check_only();
        $this->check_except();
        $this->check_methods();
        $this->check_include();
        $this->check_only_method();
    }

    private function check_only(): void
    {
        if (isset($this->options['only'])) {
            $this->options_to_a('only');

            $exclude = array_diff(array_keys($this->attributes), $this->options['only']);
            $this->attributes = array_diff_key($this->attributes, array_flip($exclude));
        }
    }

    private function check_except(): void
    {
        if (isset($this->options['except']) && !isset($this->options['only'])) {
            $this->options_to_a('except');
            $this->attributes = array_diff_key($this->attributes, array_flip($this->options['except']));
        }
    }

    private function check_methods(): void
    {
        if (isset($this->options['methods'])) {
            $this->options_to_a('methods');

            foreach ($this->options['methods'] as $method) {
                if (method_exists($this->model, $method)) {
                    $this->attributes[$method] = $this->model->$method();
                }
            }
        }
    }

    private function check_only_method(): void
    {
        if (isset($this->options['only_method'])) {
            $method = $this->options['only_method'];
            if (method_exists($this->model, $method)) {
                $this->attributes = $this->model->$method();
            }
        }
    }

    private function check_include(): void
    {
        if (isset($this->options['include'])) {
            $this->options_to_a('include');

            $serializer_class = get_class($this);

            foreach ($this->options['include'] as $association => $options) {
                if (!is_array($options)) {
                    $association = $options;
                    $options = [];
                }

                try {
                    $assoc = $this->model->$association;

                    if (null === $assoc) {
                        $this->attributes[$association] = null;
                    } elseif (!is_array($assoc)) {
                        $serialized = new $serializer_class($assoc, $options);
                        $this->attributes[$association] = $serialized->to_a();
                    } else {
                        $includes = [];

                        foreach ($assoc as $a) {
                            $serialized = new $serializer_class($a, $options);

                            if ($this->includes_with_class_name_element) {
                                $includes[strtolower(get_class($a))][] = $serialized->to_a();
                            } else {
                                $includes[] = $serialized->to_a();
                            }
                        }

                        $this->attributes[$association] = $includes;
                    }
                } catch (UndefinedPropertyException $e) {
                    // move along
                }
            }
        }
    }

    final protected function options_to_a(string $key): void
    {
        if (!is_array($this->options[$key])) {
            $this->options[$key] = [$this->options[$key]];
        }
    }

    /**
     * Returns the attributes array.
     *
     * @return Attributes
     */
    final public function to_a(): array
    {
        $date_class = Config::instance()->get_date_class();
        foreach ($this->attributes as &$value) {
            if ($value instanceof $date_class) {
                $value = $value->format(self::$DATETIME_FORMAT);
            }
        }

        return $this->attributes;
    }

    /**
     * Returns the serialized object as a string.
     *
     * @see to_s
     */
    final public function __toString(): string
    {
        return $this->to_s();
    }

    /**
     * Performs the serialization.
     */
    abstract public function to_s(): string;
}
