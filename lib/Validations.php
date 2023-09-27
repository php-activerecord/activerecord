<?php
/**
 * These two classes have been <i>heavily borrowed</i> from Ruby on Rails' ActiveRecord so much that
 * this piece can be considered a straight port. The reason for this is that the vaildation process is
 * tricky due to order of operations/events. The former combined with PHP's odd typecasting means
 * that it was easier to formulate this piece base on the rails code.
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;

use ActiveRecord\Exception\ValidationsArgumentError;

/**
 * Manages validations for a {@link Model}.
 *
 * This class isn't meant to be directly used. Instead, you define
 * validators through static variables in your {@link Model}. Example:
 *
 *
 * ```
 * class Person extends ActiveRecord\Model {
 *   static $validates_length_of = [
 *     'name' => ['within' => [30,100]],
 *     'state' => ['is' => 2]
 *   ];
 * }
 *
 * $person = new Person();
 * $person->name = 'Tito';
 * $person->state = 'this is not two characters';
 *
 * if (!$person->is_valid())
 *   print_r($person->errors);
 * ```
 *
 * @package ActiveRecord
 *
 * @see ValidationErrors
 * @see http://www.phpactiverecord.org/guides/validations
 *
 * @phpstan-type ValidationOptions array{
 *  message?: string|null,
 *  on?: string,
 *  allow_null?: bool,
 *  allow_blank?: bool,
 * }
 *
 * Available options:
 * @phpstan-type ValidateNumericOptions array{
 *  message?: string|null,
 *  allow_blank: bool,
 *  allow_null:bool,
 *  only_integer?: bool,
 *  even?: bool,
 *  off?: bool,
 *  greater_than?: int|float,
 *  greater_than_or_equal_to?: bool,
 *  equal_to?: int|float,
 *  less_than?: int|float,
 *  less_than_or_equal_to:int|float,
 * }
 * @phpstan-type ValidateLengthOptions array{
 *  is?: int,
 *  in?: int,
 *  within?: int,
 *  maximum?: int,
 *  minimum?: int,
 *  message?: string|null,
 *  allow_blank?: bool,
 *  allow_null?: bool
 * }
 * @phpstan-type ValidateUniquenessOptions array{
 *  message?: string|null,
 *  allow_blank?: bool,
 *  allow_null?: bool,
 *  scope?: array<string>
 * }
 * @phpstan-type ValidateInclusionOptions array{
 *  message?: string|null,
 *  in?: list<string>,
 *  within?: list<string>
 * }
 * @phpstan-type ValidateFormatOptions array{
 *  message?: string|null,
 *  allow_blank?:bool,
 *  allow_null?: bool,
 *  with: string,
 * }
 */
class Validations
{
    /**
     * @var \ReflectionClass<Model>
     */
    private \ReflectionClass $klass;
    private Model $model;

    /**
     * @var array<string, ValidationOptions>
     */
    private array $validators = [];
    private ValidationErrors $errors;

    /**
     * @var array<string>
     */
    private static array $VALIDATION_FUNCTIONS = [
        'validates_presence_of',
        'validates_length_of',
        'validates_inclusion_of',
        'validates_exclusion_of',
        'validates_format_of',
        'validates_numericality_of',
        'validates_uniqueness_of'
    ];

    /**
     * @var list<string>
     */
    private static array $ALL_RANGE_OPTIONS = [
        'is',
        'within',
        'in',
        'minimum',
        'maximum',
    ];

    /**
     * Constructs a {@link Validations} object.
     *
     * @param Model $model The model to validate
     *
     * @return Validations
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->errors = new ValidationErrors($this->model);
        $this->klass = Reflections::instance()->get(get_class($this->model));

        $this->validators = array_intersect_key($this->klass->getStaticProperties(), array_flip(self::$VALIDATION_FUNCTIONS));
    }

    public function get_errors(): ValidationErrors
    {
        return $this->errors;
    }

    /**
     * Returns validator data.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $data = [];
        foreach ($this->validators as $validate => $attrs) {
            foreach (wrap_values_in_arrays($attrs) as $field => $attr) {
                $data[$field] ??= [];
                $attr['validator'] = $validate;
                unset($attr[0]);
                array_push($data[$field], $attr);
            }
        }

        return $data;
    }

    /**
     * Runs the validators.
     *
     * @return ValidationErrors the validation errors if any
     */
    public function validate(): ValidationErrors
    {
        foreach ($this->validators as $validate => $values) {
            assert(
                in_array($validate, self::$VALIDATION_FUNCTIONS),
                new \Exception('Unknown validator'));

            $definition = wrap_values_in_arrays($values);
            switch ($validate) {
                case 'validates_presence_of':
                    $this->validates_presence_of($definition);
                    break;
                case 'validates_uniqueness_of':
                    $this->validates_uniqueness_of($definition);
                    break;
                case 'validates_length_of':
                    $this->validates_length_of($definition);
                    break;
                case 'validates_inclusion_of':
                    $this->validates_inclusion_of($definition);
                    break;
                case 'validates_exclusion_of':
                    $this->validates_exclusion_of($definition);
                    break;
                case 'validates_format_of':
                    $this->validates_format_of($definition);
                    break;
                case 'validates_numericality_of':
                    $this->validates_numericality_of($definition);
                    break;
            }
        }

        $model_reflection = Reflections::instance()->get(get_class($this->model));

        if (method_exists($this->model, 'validate') && $model_reflection->getMethod('validate')->isPublic()) {
            $this->model->validate();
        }

        $this->errors->clear_model();

        return $this->errors;
    }

    /**
     * Validates a field is not null and not blank.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *   static $validates_presence_of = [
     *     'first_name' => true,
     *     'last_name' => true
     *   ];
     * }
     * ```
     *
     * @param array<string, bool|ValidationOptions> $attrs Validation definition
     */
    public function validates_presence_of(array $attrs): void
    {
        foreach ($attrs as $attr => $options) {
            $this->errors->add_on_blank(
                $attr,
                $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES['blank']
            );
        }
    }

    /**
     * Validates that a value is included the specified array.
     *
     * ```
     * class Car extends ActiveRecord\Model {
     *   static $validates_inclusion_of = [
     *      'fuel_type' => [
     *          'in' => ['hydrogen', 'petroleum', 'electric']
     *      ]
     *   ];
     * }
     * ```
     *
     * @param array<string, ValidateInclusionOptions> $attrs Validation definition
     */
    public function validates_inclusion_of(array $attrs): void
    {
        $this->validates_inclusion_or_exclusion_of('inclusion', $attrs);
    }

    /**
     * This is the opposite of {@link validates_include_of}.
     *
     * Available options:
     *
     * <ul>
     * <li><b>in/within:</b> attribute should/shouldn't be a value within an array</li>
     * <li><b>message:</b> custom error message</li>
     * <li><b>allow_blank:</b> allow blank strings</li>
     * <li><b>allow_null:</b> allow null strings</li>
     * </ul>
     *
     * @param array<string, ValidateInclusionOptions> $attrs Validation definition
     *
     * @see validates_inclusion_of
     */
    public function validates_exclusion_of(array $attrs): void
    {
        $this->validates_inclusion_or_exclusion_of('exclusion', $attrs);
    }

    /**
     * Validates that a value is in or out of a specified list of values.
     *
     * Available options:
     *
     * <ul>
     * <li><b>in/within:</b> attribute should/shouldn't be a value within an array</li>
     * <li><b>message:</b> custom error message</li>
     * <li><b>allow_blank:</b> allow blank strings</li>
     * <li><b>allow_null:</b> allow null strings</li>
     * </ul>
     *
     * @see validates_inclusion_of
     * @see validates_exclusion_of
     *
     * @param string                                  $type  Either inclusion or exclusion
     * @param array<string, ValidateInclusionOptions> $attrs Validation definition
     */
    public function validates_inclusion_or_exclusion_of(string $type, array $attrs): void
    {
        foreach ($attrs as $attribute => $options) {
            $var = $this->model->$attribute;

            $enum = $options['in'] ?? $options['within'] ?? throw new \Exception("Must provide 'in' or 'within'");
            $message = str_replace('%s', $var ?? '', $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES[$type]);

            if ($this->is_null_with_option($var, $options) || $this->is_blank_with_option($var, $options)) {
                continue;
            }

            if (('inclusion' == $type && !in_array($var, $enum)) || ('exclusion' == $type && in_array($var, $enum))) {
                $this->errors->add($attribute, $message);
            }
        }
    }

    /**
     * Validates that a value is numeric.
     *
     * ```
     *  class Person extends ActiveRecord\Model {
     *      static $validates_numericality_of = [
     *          'salary' => [
     *              'greater_than' => 19.99,
     *              'less_than' => 99.99
     *          ]
     *      ];
     *  ]
     * ```
     *
     * @param array<string, ValidateNumericOptions> $attrs Validation definition
     */
    public function validates_numericality_of(array $attrs): void
    {
        // Notice that for fixnum and float columns empty strings are converted to nil.
        // Validates whether the value of the specified attribute is numeric by trying to convert it to a float with Kernel.Float
        // (if only_integer is false) or applying it to the regular expression /\A[+\-]?\d+\Z/ (if only_integer is set to true).
        foreach ($attrs as $attribute => $options) {
            $var = $this->model->$attribute;

            if ($this->is_null_with_option($var, $options)) {
                continue;
            }

            $not_a_number_message = $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES['not_a_number'];

            if (($options['only_integer'] ?? false) && !is_integer($var)) {
                if (!preg_match('/\A[+-]?\d+\Z/', (string) $var)) {
                    $this->errors->add($attribute, $not_a_number_message);
                    continue;
                }
            } else {
                if (!is_numeric($var)) {
                    $this->errors->add($attribute, $not_a_number_message);
                    continue;
                }

                $var = (float) $var;
            }

            foreach ($options as $option => $check) {
                $message = $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES[$option];

                if ('odd' != $option && 'even' != $option) {
                    $option_value = (float) $options[$option];

                    assert(is_numeric($option_value), new ValidationsArgumentError("$option must be a number"));
                    $message = str_replace('%d', $options[$option], $message);

                    if ('greater_than' == $option && !($var > $option_value)) {
                        $this->errors->add($attribute, $message);
                    } elseif ('greater_than_or_equal_to' == $option && !($var >= $option_value)) {
                        $this->errors->add($attribute, $message);
                    } elseif ('equal_to' == $option && !($var == $option_value)) {
                        $this->errors->add($attribute, $message);
                    } elseif ('less_than' == $option && !($var < $option_value)) {
                        $this->errors->add($attribute, $message);
                    } elseif ('less_than_or_equal_to' == $option && !($var <= $option_value)) {
                        $this->errors->add($attribute, $message);
                    }
                } else {
                    if (('odd' == $option && !Utils::is_odd($var)) || ('even' == $option && Utils::is_odd($var))) {
                        $this->errors->add($attribute, $message);
                    }
                }
            }
        }
    }

    /**
     * Validates that a value is matches a regex.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *   static $validates_format_of = [
     *     'email' => [
     *          'with' => '/^.*?@.*$/'
     *      ]
     *   ];
     * }
     * ```
     *
     * @param array<string, ValidateFormatOptions> $attrs Validation definition
     */
    public function validates_format_of(array $attrs): void
    {
        foreach ($attrs as $attribute => $options) {
            $var = $this->model->$attribute;
            $expression = $options['with'];

            if ($this->is_null_with_option($var, $options) || $this->is_blank_with_option($var, $options)) {
                continue;
            }

            if (!@preg_match($expression, $var)) {
                $this->errors->add($attribute, $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES['invalid']);
            }
        }
    }

    /**
     * @param array<int> $var
     */
    public static function is_range(array $var): bool
    {
        return is_array($var) && (int) $var[0] < (int) $var[1];
    }

    /**
     * Validates the length of a value.
     *
     * ```
     * class Person extends ActiveRecord\Model {
     *   static $validates_length_of = [
     *     'name' => ['within' => [1,50]]
     *   ];
     * }
     * ```
     *
     * @param array<string, ValidateLengthOptions> $attrs Validation definition
     */
    public function validates_length_of(array $attrs): void
    {
        $defaultMessages = [
            'too_long'     => ValidationErrors::$DEFAULT_ERROR_MESSAGES['too_long'],
            'too_short'    => ValidationErrors::$DEFAULT_ERROR_MESSAGES['too_short'],
            'wrong_length' => ValidationErrors::$DEFAULT_ERROR_MESSAGES['wrong_length']
        ];

        foreach ($attrs as $attribute => $options) {
            $range_options = array_intersect(self::$ALL_RANGE_OPTIONS, array_keys($options));
            sort($range_options);

            switch (sizeof($range_options)) {
                case 0:
                    throw new ValidationsArgumentError('Range unspecified.  Specify the [within], [maximum], or [is] option.');
                case 1:
                    break;

                default:
                    throw new ValidationsArgumentError('Too many range options specified.  Choose only one.');
            }

            $var = $this->model->$attribute;
            if ($this->is_null_with_option($var, $options) || $this->is_blank_with_option($var, $options)) {
                continue;
            }
            if ('within' == $range_options[0] || 'in' == $range_options[0]) {
                $range = $options[$range_options[0]];

                assert(
                    $this->is_range($range),
                    new ValidationsArgumentError("$range_options[0] must be an array composing a range of numbers with key [0] being less than key [1]")
                );
                $range_options = ['minimum', 'maximum'];
                $options['minimum'] = $range[0];
                $options['maximum'] = $range[1];
            }
            foreach ($range_options as $range_option) {
                $value = $options[$range_option];

                if ((int) $value <= 0) {
                    throw new ValidationsArgumentError("$range_option value cannot use a signed integer.");
                }

                if (!('maximum' == $range_option && is_null($this->model->$attribute))) {
                    $messageOptions = [
                        'is' => 'wrong_length',
                        'minimum' => 'too_short',
                        'maximum' => 'too_long'
                    ];
                    $message = $options['message'] ?? $defaultMessages[$messageOptions[$range_option]];
                    $message = str_replace('%d', $value, $message);
                    $attribute_value = $this->model->$attribute;
                    $len = strlen($attribute_value ?? '');

                    if ('maximum' == $range_option && $len > $value) {
                        $this->errors->add($attribute, $message);
                    }

                    if ('minimum' == $range_option && $len < $value) {
                        $this->errors->add($attribute, $message);
                    }

                    if ('is' == $range_option && $len !== $value) {
                        $this->errors->add($attribute, $message);
                    }
                }
            }
        }
    }

    /**
     * Validates the uniqueness of a value.
     *
     * ```
     *  class Person extends ActiveRecord\Model {
     *      static $validates_uniqueness_of = [
     *          'name' => [
     *              'message' => 'blech'
     *          ]
     *      ];
     * }
     * ```
     *
     * @param array<string, bool|ValidateUniquenessOptions> $attrs Validation definition
     */
    public function validates_uniqueness_of($attrs): void
    {
        // Retrieve connection from model for quote_name method
        $connection = $this->klass->getMethod('connection')->invoke(null);

        foreach ($attrs as $attr => $options) {
            $pk = $this->model->get_primary_key();
            $pk_value = $this->model->{$pk};

            $fields = array_merge([$attr], $options['scope'] ?? []);
            $add_record = join('_and_', $fields);

            $conditions = [''];
            $pk_quoted = $connection->quote_name($pk);
            if (null === $pk_value) {
                $sql = "{$pk_quoted} IS NOT NULL";
            } else {
                $sql = "{$pk_quoted} != ?";
                array_push($conditions, $pk_value);
            }

            foreach ($fields as $field) {
                $field = $this->model->get_real_attribute_name($field);
                $quoted_field = $connection->quote_name($field);
                $sql .= " AND {$quoted_field}=?";
                array_push($conditions, $this->model->$field);
            }

            $conditions[0] = $sql;

            if ($this->model->exists($conditions)) {
                $this->errors->add($add_record, $options['message'] ?? ValidationErrors::$DEFAULT_ERROR_MESSAGES['unique']);
            }
        }
    }

    /**
     * @param ValidationOptions $options
     */
    private function is_null_with_option(mixed $var, array &$options): bool
    {
        return is_null($var) && ($options['allow_null'] ?? false);
    }

    /**
     * @param ValidationOptions $options
     */
    private function is_blank_with_option(mixed $var, array &$options): bool
    {
        return Utils::is_blank($var) && ($options['allow_blank'] ?? false);
    }
}
