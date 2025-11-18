<?php
// Centralized input validation and output encoding helpers

if (!function_exists('validateString')) {
    function validateString($value, $minLen = 0, $maxLen = 255) {
        if (!is_string($value)) return false;
        $len = mb_strlen($value, 'UTF-8');
        return $len >= $minLen && $len <= $maxLen;
    }
}

if (!function_exists('validateInt')) {
    function validateInt($value, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
        if (!is_numeric($value)) return false;
        $intVal = filter_var($value, FILTER_VALIDATE_INT);
        if ($intVal === false) return false;
        return $intVal >= $min && $intVal <= $max;
    }
}

if (!function_exists('validateEmailStrict')) {
    function validateEmailStrict($email) {
        if (!is_string($email)) return false;
        if (mb_strlen($email, 'UTF-8') > 254) return false;
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validateEnum')) {
    function validateEnum($value, $allowed) {
        return in_array($value, $allowed, true);
    }
}

if (!function_exists('getPostParam')) {
    function getPostParam($key, $type = 'string', $options = []) {
        $value = $_POST[$key] ?? null;
        return sanitizeAndValidate($value, $type, $options);
    }
}

if (!function_exists('getQueryParam')) {
    function getQueryParam($key, $type = 'string', $options = []) {
        $value = $_GET[$key] ?? null;
        return sanitizeAndValidate($value, $type, $options);
    }
}

if (!function_exists('sanitizeAndValidate')) {
    function sanitizeAndValidate($value, $type, $options) {
        if ($value === null) return null;
        switch ($type) {
            case 'int':
                if (!validateInt($value, $options['min'] ?? PHP_INT_MIN, $options['max'] ?? PHP_INT_MAX)) return null;
                return (int)$value;
            case 'email':
                $value = trim($value);
                if (!validateEmailStrict($value)) return null;
                return $value;
            case 'string':
            default:
                $value = trim($value);
                if (!validateString($value, $options['min'] ?? 0, $options['max'] ?? 255)) return null;
                return $value;
        }
    }
}

// Output encoding helpers
if (!function_exists('outputHtml')) {
    function outputHtml($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('outputAttr')) {
    function outputAttr($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('outputUrl')) {
    function outputUrl($value) {
        $value = (string)$value;
        // Disallow dangerous protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $value)) {
            return '#';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
?>


