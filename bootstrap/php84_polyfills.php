<?php

/**
 * PHP 8.4 polyfills for running on PHP 8.2/8.3.
 *
 * These are native PHP 8.4 array functions used by laravel/framework ^12.19.
 * They are registered globally so the framework vendor code works on PHP 8.2/8.3.
 */

if (! function_exists('array_first')) {
    /**
     * Return the first element of an array.
     * PHP 8.4 native: array_first(array $array): mixed
     */
    function array_first(array $array): mixed
    {
        if (empty($array)) {
            return false;
        }
        return reset($array);
    }
}

if (! function_exists('array_last')) {
    /**
     * Return the last element of an array.
     * PHP 8.4 native: array_last(array $array): mixed
     */
    function array_last(array $array): mixed
    {
        if (empty($array)) {
            return false;
        }
        return end($array);
    }
}

if (! function_exists('array_find')) {
    /**
     * Return the first element for which the callback returns true.
     * PHP 8.4 native: array_find(array $array, callable $callback): mixed
     */
    function array_find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}

if (! function_exists('array_find_key')) {
    /**
     * Return the key of the first element for which the callback returns true.
     * PHP 8.4 native: array_find_key(array $array, callable $callback): int|string|null
     */
    function array_find_key(array $array, callable $callback): int|string|null
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $key;
            }
        }
        return null;
    }
}

if (! function_exists('array_any')) {
    /**
     * Return true if the callback returns true for at least one element.
     * PHP 8.4 native: array_any(array $array, callable $callback): bool
     */
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('array_all')) {
    /**
     * Return true if the callback returns true for all elements (or the array is empty).
     * PHP 8.4 native: array_all(array $array, callable $callback): bool
     */
    function array_all(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (! $callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}
