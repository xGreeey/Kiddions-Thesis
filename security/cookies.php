<?php
// Helpers for setting and deleting cookies with secure defaults

if (!function_exists('cookieGetDefaults')) {
    function cookieGetDefaults() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Detect environment
        $isProduction = (getenv('APP_ENV') === 'production') || (!in_array($host, ['localhost', '127.0.0.1', 'localhost:8000']));
        // Detect HTTPS accurately
        $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (($_SERVER['SERVER_PORT'] ?? 80) == 443) ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        // Derive a shared cookie domain for subdomain sharing in production
        $cookieDomain = '';
        if ($isProduction) {
            $h = $host;
            // Strip port
            $h = preg_replace('/:\\d+$/', '', $h);
            // Normalize www
            if (strpos($h, 'www.') === 0) { $h = substr($h, 4); }
            // Only set domain if it looks like a hostname and not an IP
            if (preg_match('/^[a-z0-9.-]+$/i', $h) && !filter_var($h, FILTER_VALIDATE_IP)) {
                $cookieDomain = '.' . $h; // leading dot to include subdomains
            }
        }

        $defaults = [
            'expires' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $isHTTPS,
            'httponly' => true,
            'samesite' => $isProduction ? 'Strict' : 'Lax',
        ];
        return $defaults;
    }
}

if (!function_exists('setSecureCookie')) {
    function setSecureCookie($name, $value, $ttlSeconds = 0, $overrides = []) {
        $opts = cookieGetDefaults();
        if ($ttlSeconds > 0) {
            $opts['expires'] = time() + $ttlSeconds;
        }
        foreach ($overrides as $k => $v) {
            $opts[$k] = $v;
        }
        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, $value, $opts);
        }
        return setcookie($name, $value, $opts['expires'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']);
    }
}

if (!function_exists('deleteSecureCookie')) {
    function deleteSecureCookie($name, $overrides = []) {
        $opts = cookieGetDefaults();
        foreach ($overrides as $k => $v) {
            $opts[$k] = $v;
        }
        $opts['expires'] = time() - 42000;
        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, '', $opts);
        }
        return setcookie($name, '', $opts['expires'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']);
    }
}
?>


