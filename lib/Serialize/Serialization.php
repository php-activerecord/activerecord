<?php

namespace ActiveRecord\Serialize;

use ActiveRecord\DateTimeInterface;
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
     * $user = [
     *   'id' => 1,
     *   'name' => 'Tito',
     *   'permissions' => [
     *     [
     *       'id' => 100,
     *       'name' => 'admin'
     *     ],
     *     [
     *       'id' => 101,
     *       'name' => 'normal'
     *     ]
     *   ]
     * ];
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
            $exclude = array_diff(
                array_keys($this->attributes),
                $this->value_to_a($this->options['only'])
            );
            $this->attributes = array_diff_key($this->attributes, array_flip($exclude));
        }
    }

    private function check_except(): void
    {
        if (isset($this->options['except']) && !isset($this->options['only'])) {
            $this->attributes = array_diff_key(
                $this->attributes,
                array_flip($this->value_to_a($this->options['except']))
            );
        }
    }

    private function check_methods(): void
    {
        if (isset($this->options['methods'])) {
            foreach ($this->value_to_a($this->options['methods']) as $method) {
                if (method_exists($this->model, $method)) {
                    /*
                     * PHPStan complains about this, and I don't know why. Skipping, for now.
                     *
                     * @phpstan-ignore-next-line
                     */
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
            $serializer_class = get_class($this);

            foreach ($this->value_to_a($this->options['include']) as $association => $options) {
                if (!is_array($options)) {
                    $association = $options;
                    $options = [];
                }
                assert(is_string($association));

                $assoc = $this->model->$association;

                if (!is_array($assoc)) {
                    $serialized = new $serializer_class($assoc, $options);
                    $this->attributes[$association] = $serialized->to_a();
                } else {
                    $includes = [];

                    foreach ($assoc as $a) {
                        $serialized = new $serializer_class($a, $options);

                        if ($this->includes_with_class_name_element) {
                            $className = get_class($a);
                            assert(is_string($className));
                            $includes[strtolower($className)][] = $serialized->to_a();
                        } else {
                            $includes[] = $serialized->to_a();
                        }
                    }

                    $this->attributes[$association] = $includes;
                }
            }
        }
    }

    /**
     * @return array<mixed>
     */
    final protected function value_to_a(mixed $value): array
    {
        if (!is_array($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * Returns the attributes array.
     *
     * @return Attributes
     */
    final public function to_a(): array
    {
        foreach ($this->attributes as &$value) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format(self::$DATETIME_FORMAT);
            }
        }

        return $this->attributes;
    }

    /**
     * Performs the serialization.
     */
    abstract public function to_s(): string;
}
