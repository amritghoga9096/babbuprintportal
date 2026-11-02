<?php
// ====== PHP 7.4 Compatibility Polyfills (PHP 8.0+ functions) ======
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        $lenNeedle = strlen($needle);
        return substr($haystack, -$lenNeedle) === $needle;
    }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $array) {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}
// ==================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

