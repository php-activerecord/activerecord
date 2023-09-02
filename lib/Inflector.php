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
     * Turn a string into its camelized version.
     */
    static public function camelize(string $s): string
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
     */
    static public function is_upper(string $s): bool
    {
        return strtoupper($s) === $s;
    }

    /**
     * Convert a camelized string to a lowercase, underscored string.
     */
    static public function uncamelize(string $s): string
    {
        $normalized = '';

        for ($i = 0, $n = strlen($s); $i < $n; ++$i) {
            if (ctype_alpha($s[$i]) && static::is_upper($s[$i])) {
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
    static public function underscorify(string $s): string
    {
        $res = preg_replace(['/[_\- ]+/', '/([a-z])([A-Z])/'], ['_', '\\1_\\2'], trim($s));
        assert(is_string($res));

        return $res;
    }

    static public function keyify(string $class_name): string
    {
        return strtolower(static::underscorify(denamespace($class_name))) . '_id';
    }

    static public function tableize(string $s): string
    {
        return Utils::pluralize(strtolower(static::underscorify($s)));
    }

    static public function variablize(string $s): string
    {
        return str_replace(['-', ' '], ['_', '_'], strtolower(trim($s)));
    }
}


