<?php

namespace ActiveRecord;

/**
 * Class that holds {@link Validations} errors.
 *
 * @package ActiveRecord
 *
 * @implements \IteratorAggregate<string, string>
 */
class ValidationErrors implements \IteratorAggregate
{
    private Model|null $model;

    /**
     * @var array<string, array<string>>
     */
    private array $errors;

    /**
     * @var array<string,string>
     */
    public static array $DEFAULT_ERROR_MESSAGES = [
        'inclusion' => 'is not included in the list',
        'exclusion' => 'is reserved',
        'invalid' => 'is invalid',
        'confirmation' => "doesn't match confirmation",
        'accepted' => 'must be accepted',
        'empty' => "can't be empty",
        'blank' => "can't be blank",
        'too_long' => 'is too long (maximum is %d characters)',
        'too_short' => 'is too short (minimum is %d characters)',
        'wrong_length' => 'is the wrong length (should be %d characters)',
        'taken' => 'has already been taken',
        'not_a_number' => 'is not a number',
        'greater_than' => 'must be greater than %d',
        'only_integer' => 'must be an integer',
        'equal_to' => 'must be equal to %d',
        'less_than' => 'must be less than %d',
        'odd' => 'must be odd',
        'even' => 'must be even',
        'unique' => 'must be unique',
        'less_than_or_equal_to' => 'must be less than or equal to %d',
        'greater_than_or_equal_to' => 'must be greater than or equal to %d'
    ];

    /**
     * Constructs an {@link ValidationErrors} object.
     *
     * @param Model $model The model the error is for
     *
     * @return ValidationErrors
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Nulls $model so we don't get pesky circular references. $model is only needed during the
     * validation process and so can be safely cleared once that is done.
     */
    public function clear_model(): void
    {
        $this->model = null;
    }

    /**
     * Add an error message.
     *
     * @param string $attribute Name of an attribute on the model
     * @param string $msg       The error message
     */
    public function add(string $attribute, string $msg): void
    {
        $this->errors[$attribute] ??= [];
        $this->errors[$attribute][] = $msg;
    }

    /**
     * Adds the error message only if the attribute value was null or an empty string.
     *
     * @param string $attribute Name of an attribute on the model
     * @param string $msg       The error message
     */
    public function add_on_blank(string $attribute, ?string $msg): void
    {
        if ('' === ($value = $this->model->$attribute) || null === $value) {
            $this->add($attribute, $msg ?? self::$DEFAULT_ERROR_MESSAGES['blank']);
        }
    }

    /**
     * Returns true if the specified attribute had any error messages.
     *
     * @param string $attribute Name of an attribute on the model
     *
     * @return bool
     */
    public function is_invalid($attribute)
    {
        return isset($this->errors[$attribute]);
    }

    /**
     * Returns the error message(s) for the specified attribute or null if none.
     *
     * @param string $attribute Name of an attribute on the model
     *
     * @return array<string>|null array of strings if several error occurred on this attribute
     */
    public function on(string $attribute): ?array
    {
        return $this->errors[$attribute] ?? null;
    }

    /**
     * Returns the first error message(s) for the specified attribute or null if none.
     *
     * @param string $attribute Name of an attribute on the model
     *
     * @return string|null
     */
    public function first(string $attribute)
    {
        return $this->on($attribute)[0] ?? null;
    }

    /**
     * Returns all the error messages as an array.
     *
     * ```
     * $model->errors->full_messages();
     *
     * # [
     * #  "Name can't be blank",
     * #  "State is the wrong length (should be 2 chars)"
     * # ]
     * ```
     *
     * @return array<string>
     */
    public function full_messages(): array
    {
        $full_messages = [];

        $this->to_array(function ($attribute, $message) use (&$full_messages) {
            $full_messages[] = $message;
        });

        return $full_messages;
    }

    /**
     * Returns all the error messages as an array, including error key.
     *
     * ```
     * $model->errors->errors();
     *
     * # [
     * #  "name" => ["Name can't be blank"],
     * #  "state" => ["State is the wrong length (should be 2 chars)"]
     * # ]
     * ```
     *
     * @param callable $closure closure to fetch the errors in some other format (optional)
     *                          This closure has the signature function($attribute, $message)
     *                          and is called for each available error message
     *
     * @return array<string, array<string>>
     */
    public function to_array(callable $closure = null): array
    {
        $errors = [];

        if ($this->errors) {
            foreach ($this->errors as $attribute => $messages) {
                foreach ($messages as $msg) {
                    $errors[$attribute][] = ($message = Utils::human_attribute($attribute) . ' ' . $msg);

                    if ($closure) {
                        $closure($attribute, $message);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Convert all error messages to a String.
     * This function is called implicitely if the object is casted to a string:
     *
     * ```
     * echo $error;
     *
     * # "Name can't be blank\nState is the wrong length (should be 2 chars)"
     * ```
     *
     * @return string
     */
    public function __toString()
    {
        return implode("\n", $this->full_messages());
    }

    /**
     * Returns true if there are no error messages.
     *
     * @return bool
     */
    public function is_empty()
    {
        return empty($this->errors);
    }

    /**
     * Returns an iterator to the error messages.
     *
     * This will allow you to iterate over the {@link ValidationErrors} object using foreach.
     *
     * ```
     * foreach ($model->errors as $msg)
     *   echo "$msg\n";
     * ```
     *
     * @return \ArrayIterator<string, string>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->full_messages());
    }
}
