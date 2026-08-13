<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved.
*
* Optimized version for PHP 8.0 with backward compatibility preserved.
*/

class Str
{
    public static function ucfirst($str)
    {
        // Prefer mbstring if available
        if (function_exists('mb_strlen')) {
            return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($str, 1, mb_strlen($str, 'UTF-8'), 'UTF-8');
        }

        // Windows-1251 fallback via iconv (kept for backward compatibility)
        if (function_exists('iconv')) {
            return iconv('windows-1251', 'utf-8',
                ucfirst(iconv('utf-8', 'windows-1251', $str))
            );
        }

        return ucfirst($str);
    }

    public static function lower($str)             // Added required parameter $str
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($str, 'UTF-8');
        }

        if (function_exists('iconv')) {
            return iconv('windows-1251', 'utf-8',
                strtolower(iconv('utf-8', 'windows-1251', $str))
            );
        }

        return strtolower($str);
    }

    public static function upper($str)             // Added required parameter $str
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($str, 'UTF-8');
        }

        if (function_exists('iconv')) {
            return iconv('windows-1251', 'utf-8',
                strtoupper(iconv('utf-8', 'windows-1251', $str))
            );
        }

        return strtoupper($str);
    }

    public static function length($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, 'UTF-8');
        }

        if (function_exists('iconv')) {
            return strlen(iconv('utf-8', 'windows-1251', $str));
        }

        return strlen($str);
    }
    
    public static function strstr($haystack, $needle, $part = false)
    {
        return function_exists('mb_strstr')
            ? mb_strstr($haystack, $needle, $part, 'UTF-8')
            : strstr($haystack, $needle, $part);
    }

    public static function contains($haystack, $needle): bool
    {
        return self::strstr($haystack, $needle) !== false;
    }

    public static function startsWith($haystack, $needle): bool
    {
        return function_exists('mb_strpos')
            ? mb_strpos($haystack, $needle, 0, 'UTF-8') === 0
            : str_starts_with($haystack, $needle);
    }

    public static function endsWith($haystack, $needle): bool
    {
        return function_exists('mb_strlen')
            ? mb_substr($haystack, -mb_strlen($needle, 'UTF-8'), null, 'UTF-8') === $needle
            : str_ends_with($haystack, $needle);
    }

    /**
     * Truncates the string to the nearest space without exceeding the given length.
     */
    public static function crop($string, $length = 80)      // $length is now an explicit parameter
    {
        $string = strip_tags($string);

        if (function_exists('mb_strlen')) {
            $len = (mb_strlen($string, 'UTF-8') > $length)
                ? mb_strripos(mb_substr($string, 0, $length, 'UTF-8'), ' ', 0, 'UTF-8')
                : $length;

            if ($len === false) {          // if no space is found in the substring
                $len = $length;
            }

            $result = mb_substr($string, 0, $len, 'UTF-8');

            return (mb_strlen($string, 'UTF-8') > $length) ? $result . '...' : $result;
        }

        if (function_exists('iconv')) {
            $converted   = iconv('utf-8', 'windows-1251', $string);
            $spacePos    = strripos(substr($converted, 0, $length), ' ');
            $result1251  = ($spacePos !== false) ? substr($converted, 0, $spacePos) : substr($converted, 0, $length);

            return iconv('windows-1251', 'utf-8', $result1251);
        }

        // Pure single-byte fallback
        $len = (strlen($string) > $length)
            ? strripos(substr($string, 0, $length), ' ')
            : $length;

        if ($len === false) {
            $len = $length;
        }

        $result = substr($string, 0, $len);

        return (strlen($string) > $length) ? $result . '...' : $result;
    }

    public static function truncate($string, $length = 80, $etc = '...', $break_words = false, $middle = false)
    {
        if ($length == 0) {
            return '';
        }

        // mbstring
        if (function_exists('mb_substr')) {
            if (mb_strlen($string, 'UTF-8') > $length) {
                $length -= min($length, mb_strlen($etc, 'UTF-8'));

                if (!$break_words && !$middle) {
                    $string = preg_replace('/\s+?(\S+)?$/u', '', mb_substr($string, 0, $length + 1, 'UTF-8'));
                }

                if (!$middle) {
                    return mb_substr($string, 0, $length, 'UTF-8') . $etc;
                }

                return mb_substr($string, 0, (int)($length / 2), 'UTF-8')
                    . $etc
                    . mb_substr($string, -(int)($length / 2), $length, 'UTF-8');
            }

            return $string;
        }

        // Single-byte fallback
        if (isset($string[$length])) {
            $length -= min($length, strlen($etc));

            if (!$break_words && !$middle) {
                $string = preg_replace('/\s+?(\S+)?$/', '', substr($string, 0, $length + 1));
            }

            if (!$middle) {
                return substr($string, 0, $length) . $etc;
            }

            return substr($string, 0, (int)($length / 2))
                . $etc
                . substr($string, -(int)($length / 2));
        }

        return $string;
    }
}