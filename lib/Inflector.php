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
    public static function camelize(string $s): string
    {
        $s = preg_replace('/[_-]+/', '_', trim($s));
        assert(is_string($s));
        $s = str_replace(' ', '_', $s);
        assert(is_string($s));

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
    public static function is_upper(string $s): bool
    {
        return strtoupper($s) === $s;
    }

    /**
     * Convert a camelized string to a lowercase, underscored string.
     */
    public static function uncamelize(string $s): string
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
    public static function underscorify(string $s): string
    {
        $res = preg_replace(['/[_\- ]+/', '/([a-z])([A-Z])/'], ['_', '\\1_\\2'], trim($s));
        assert(is_string($res));

        return $res;
    }

    public static function keyify(string $class_name): string
    {
        return strtolower(static::underscorify(denamespace($class_name))) . '_id';
    }

    public static function tableize(string $s): string
    {
        return Utils::pluralize(strtolower(static::underscorify($s)));
    }

    public static function variablize(string $s): string
    {
        return str_replace(['-', ' '], ['_', '_'], strtolower(trim($s)));
    }
}
