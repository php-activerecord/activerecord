<?php
/**
 * @package ActiveRecord
 */

/*
 * Thanks to http://www.eval.ca/articles/php-pluralize (MIT license)
 *           http://dev.rubyonrails.org/browser/trunk/activesupport/lib/active_support/inflections.rb (MIT license)
 *           http://www.fortunecity.com/bally/durrus/153/gramch13.html
 *           http://www2.gsu.edu/~wwwesl/egw/crump.htm
 *
 * Changes (12/17/07)
 *   Major changes
 *   --
 *   Fixed irregular noun algorithm to use regular expressions just like the original Ruby source.
 *       (this allows for things like fireman -> firemen
 *   Fixed the order of the singular array, which was backwards.
 *
 *   Minor changes
 *   --
 *   Removed incorrect pluralization rule for /([^aeiouy]|qu)ies$/ => $1y
 *   Expanded on the list of exceptions for *o -> *oes, and removed rule for buffalo -> buffaloes
 *   Removed dangerous singularization rule for /([^f])ves$/ => $1fe
 *   Added more specific rules for singularizing lives, wives, knives, sheaves, loaves, and leaves and thieves
 *   Added exception to /(us)es$/ => $1 rule for houses => house and blouses => blouse
 *   Added excpetions for feet, geese and teeth
 *   Added rule for deer -> deer
 *
 * Changes:
 *   Removed rule for virus -> viri
 *   Added rule for potato -> potatoes
 *   Added rule for *us -> *uses
 */

namespace ActiveRecord;

function classify(string $string, bool $singular = false): string
{
    if ($singular) {
        $string = Utils::singularize($string);
    }

    $string = Inflector::camelize($string);

    $res = ucfirst($string);

    return $res;
}

/**
 * @param array<mixed> $array
 *
 * @return array<mixed>
 *
 * http://snippets.dzone.com/posts/show/4660
 */
function array_flatten(array $array): array
{
    $i = 0;

    while ($i < count($array)) {
        if (is_array($array[$i])) {
            array_splice($array, $i, 1, $array[$i]);
        } else {
            ++$i;
        }
    }

    return $array;
}

/**
 * Somewhat naive way to determine if an array is a hash.
 */
function is_hash(mixed &$array): bool
{
    return is_array($array) && !array_is_list($array);
}

/**
 * Strips a class name of any namespaces and namespace operator.
 */
function denamespace(string $class_name): string
{
    if (has_namespace($class_name)) {
        $parts = explode('\\', $class_name);

        return end($parts);
    }

    return $class_name;
}

// function get_namespaces($class_name)
// {
//    if (has_namespace($class_name)) {
//        return explode('\\', $class_name);
//    }
//
//    return null;
// }

function has_namespace(string $class_name): bool
{
    return str_contains($class_name, '\\');
}

function has_absolute_namespace(string $class_name): bool
{
    return str_starts_with($class_name, '\\');
}

/**
 * Returns true if all values in $haystack === $needle
 *
 * @param array<mixed> $haystack
 */
function all(mixed $needle, array $haystack): bool
{
    foreach ($haystack as $value) {
        if ($value !== $needle) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<mixed> $enumerable
 *
 * @return array<mixed>
 */
function collect(array &$enumerable, string|\Closure $name_or_closure): array
{
    $ret = [];
    foreach ($enumerable as $value) {
        if (is_string($name_or_closure)) {
            $ret[] = is_array($value) ? $value[$name_or_closure] : $value->$name_or_closure;
        } elseif ($name_or_closure instanceof \Closure) {
            $ret[] = $name_or_closure($value);
        }
    }

    return $ret;
}

/**
 * Wrap string definitions (if any) into arrays.
 *
 * @param string|array<mixed> $strings
 *
 * @return array<mixed>
 */
function wrap_values_in_arrays(mixed &$strings): array
{
    if (!is_array($strings)) {
        $strings = [[$strings]];
    } else {
        foreach ($strings as &$str) {
            if (!is_array($str)) {
                $str = [$str];
            }
        }
    }

    return $strings;
}

/**
 * Some internal utility functions.
 *
 * @package ActiveRecord
 */
class Utils
{
    public static function human_attribute(string $attr): string
    {
        $inflected = Inflector::variablize($attr);
        $normal = Inflector::uncamelize($inflected);

        return ucfirst(str_replace('_', ' ', $normal));
    }

    public static function is_odd(int|float $number): bool
    {
        return (bool) ((int) $number & 1);
    }

    public static function is_blank(string|null $var): bool
    {
        return 0 === strlen($var ?? '');
    }

    /**
     * @var array<string>
     */
    private static array $plural = [
        '/(quiz)$/i'               => '$1zes',
        '/^(ox)$/i'                => '$1en',
        '/([m|l])ouse$/i'          => '$1ice',
        '/(matr|vert|ind)ix|ex$/i' => '$1ices',
        '/(x|ch|ss|sh)$/i'         => '$1es',
        '/([^aeiouy]|qu)y$/i'      => '$1ies',
        '/(hive)$/i'               => '$1s',
        '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
        '/(shea|lea|loa|thie)f$/i' => '$1ves',
        '/sis$/i'                  => 'ses',
        '/([ti])um$/i'             => '$1a',
        '/(tomat|potat|ech|her|vet)o$/i' => '$1oes',
        '/(bu)s$/i'                => '$1ses',
        '/(alias)$/i'              => '$1es',
        '/(octop)us$/i'            => '$1i',
        '/(cris|ax|test)is$/i'     => '$1es',
        '/(us)$/i'                 => '$1es',
        '/s$/i'                    => 's',
        '/$/'                      => 's'
    ];

    /**
     * @var array<string,string>
     */
    private static array $singular = [
        '/(quiz)zes$/i'             => '$1',
        '/(matr)ices$/i'            => '$1ix',
        '/(vert|ind)ices$/i'        => '$1ex',
        '/^(ox)en$/i'               => '$1',
        '/(alias)es$/i'             => '$1',
        '/(octop|vir)i$/i'          => '$1us',
        '/(cris|ax|test)es$/i'      => '$1is',
        '/(shoe)s$/i'               => '$1',
        '/(o)es$/i'                 => '$1',
        '/(bus)es$/i'               => '$1',
        '/([m|l])ice$/i'            => '$1ouse',
        '/(x|ch|ss|sh)es$/i'        => '$1',
        '/(m)ovies$/i'              => '$1ovie',
        '/(s)eries$/i'              => '$1eries',
        '/([^aeiouy]|qu)ies$/i'     => '$1y',
        '/([lr])ves$/i'             => '$1f',
        '/(tive)s$/i'               => '$1',
        '/(hive)s$/i'               => '$1',
        '/(li|wi|kni)ves$/i'        => '$1fe',
        '/(shea|loa|lea|thie)ves$/i' => '$1f',
        '/(^analy)ses$/i'           => '$1sis',
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => '$1$2sis',
        '/([ti])a$/i'               => '$1um',
        '/(n)ews$/i'                => '$1ews',
        '/(h|bl)ouses$/i'           => '$1ouse',
        '/(corpse)s$/i'             => '$1',
        '/(us)es$/i'                => '$1',
        '/(us|ss)$/i'               => '$1',
        '/s$/i'                     => ''
    ];

    /**
     * @var array<string, string>
     */
    private static array $irregular = [
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'tooth'  => 'teeth',
        'person' => 'people'
    ];

    /**
     * @var array<string>
     */
    private static array $uncountable = [
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment'
    ];

    public static function pluralize(string $string): string
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), self::$uncountable)) {
            return $string;
        }

        // check for irregular singular forms
        foreach (self::$irregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string)) {
                $res = preg_replace($pattern, $result, $string);
                assert(is_string($res));

                return $res;
            }
        }

        $res = '';
        // check for matches using regular expressions
        foreach (self::$plural as $pattern => $result) {
            if (preg_match($pattern, $string)) {
                $res = preg_replace($pattern, $result, $string);
                assert(is_string($res));

                break;
            }
        }

        return $res;
    }

    public static function singularize(string $string): string
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), self::$uncountable)) {
            return $string;
        }

        // check for irregular plural forms
        foreach (self::$irregular as $result => $pattern) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string)) {
                $res = preg_replace($pattern, $result, $string);
                assert(is_string($res));

                return $res;
            }
        }

        // check for matches using regular expressions
        foreach (self::$singular as $pattern => $result) {
            if (preg_match($pattern, $string)) {
                $res = preg_replace($pattern, $result, $string);
                assert(is_string($res));

                return $res;
            }
        }

        return $string;
    }

    public static function squeeze(string $char, string $string): string
    {
        $res = preg_replace("/$char+/", $char, $string);
        assert(is_string($res));

        return $res;
    }
}
