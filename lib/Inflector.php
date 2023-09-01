<?php
/**
 * @package ActiveRecord
 */

namespace ActiveRecord;

/**
 * @package ActiveRecord
 */
abstract class Inflector
{
    /**
     * Get an instance of the {@link Inflector} class.
     *
     * @return object
     */
    public static function instance()
    {
        return new StandardInflector();
    }

    /**
     * Turn a string into its camelized version.
     *
     * @param string $s string to convert
     *
     * @return string
     */
    public function camelize($s)
    {
        $s = preg_replace('/[_-]+/', '_', trim($s));
        $s = str_replace(' ', '_', $s);

        $camelized = '';

        for ($i = 0, $n = strlen($s); $i < $n; ++$i) {
            if ('_' == $s[$i] && $i + 1 < $n) {
                $camelized .= strtoupper($s[++$i]);
            } else {
                $camelized .= $s[$i];
            }
        }

        $camelized = trim($camelized, ' _');

        if (strlen($camelized) > 0) {
            $camelized[0] = strtolower($camelized[0]);
        }

        return $camelized;
    }

    /**
     * Determines if a string contains all uppercase characters.
     *
     * @param string $s string to check
     *
     * @return bool
     */
    public static function is_upper($s)
    {
        return strtoupper($s) === $s;
    }

    /**
     * Convert a camelized string to a lowercase, underscored string.
     */
    public function uncamelize(string $s): string
    {
        $normalized = '';

        for ($i = 0, $n = strlen($s); $i < $n; ++$i) {
            if (ctype_alpha($s[$i]) && self::is_upper($s[$i])) {
                $normalized .= '_' . strtolower($s[$i]);
            } else {
                $normalized .= $s[$i];
            }
        }

        return trim($normalized, ' _');
    }

    /**
     * Convert a string with space into a underscored equivalent.
     */
    public function underscorify(string $s): string
    {
        $res = preg_replace(['/[_\- ]+/', '/([a-z])([A-Z])/'], ['_', '\\1_\\2'], trim($s));
        assert(is_string($res));

        return $res;
    }

    public function keyify(string $class_name): string
    {
        return strtolower($this->underscorify(denamespace($class_name))) . '_id';
    }

    abstract public function variablize(string $s): string;
}

/**
 * @package ActiveRecord
 */
class StandardInflector extends Inflector
{
    public function tableize(string $s): string
    {
        return Utils::pluralize(strtolower($this->underscorify($s)));
    }

    public function variablize(string $s): string
    {
        return str_replace(['-', ' '], ['_', '_'], strtolower(trim($s)));
    }
}
