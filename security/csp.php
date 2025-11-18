<?php
// Content Security Policy setup (Report-Only initially to avoid breaking pages)

if (!function_exists('getCspNonce')) {
    function getCspNonce() {
        return $_SESSION['_csp_nonce'] ?? '';
    }
}

if (!function_exists('applyContentSecurityPolicy')) {
    function applyContentSecurityPolicy() {
        if (headers_sent()) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isProduction = (getenv('APP_ENV') === 'production') || (!in_array($host, ['localhost', '127.0.0.1', 'localhost:8000']));

        // Optional nonce for stricter CSP; generate per-request
        $nonce = base64_encode(random_bytes(16));
        if (!isset($_SESSION['_csp_nonce'])) {
            $_SESSION['_csp_nonce'] = $nonce;
        } else {
            $nonce = $_SESSION['_csp_nonce'];
        }

        // Base directives (strict by default)
        $cspDirectives = [
            "default-src" => ["'self'"],
            // Use nonce for inline scripts
            "script-src" => [
                "'self'",
                "'nonce-$nonce'",
            // Allowed JS CDNs
            "https://cdn.jsdelivr.net",
            "https://code.jquery.com",
            "https://cdnjs.cloudflare.com",
            "https://static.cloudflare.com",
            "https://challenges.cloudflare.com",
            "https://mmtvtc.com"
            ],
            // Element-specific fallbacks for older user agents (mirror script-src)
            "script-src-elem" => [
                "'self'",
                "'nonce-$nonce'",
                "https://cdn.jsdelivr.net",
                "https://code.jquery.com",
                "https://cdnjs.cloudflare.com",
                "https://static.cloudflare.com",
                "https://challenges.cloudflare.com",
                "https://mmtvtc.com"
            ],
            // Disallow inline event handlers by default
            "script-src-attr" => ["'none'"],
            // Nonced styles only + allowlisted CDNs for stylesheets
            "style-src" => [
                "'self'",
                "'nonce-$nonce'",
                "https://cdn.jsdelivr.net",
                "https://cdnjs.cloudflare.com",
                "https://static.cloudflare.com"
            ],
            // Element/attr variants with safe fallbacks
            "style-src-elem" => [
                "'self'",
                "'nonce-$nonce'",
                "https://cdn.jsdelivr.net",
                "https://cdnjs.cloudflare.com",
                "https://static.cloudflare.com"
            ],
            "style-src-attr" => ["'none'"],
            // Allow fonts from same origin and Font Awesome CDN; allow data: for inlined fonts if any
            "font-src" => [
                "'self'",
                "https://cdnjs.cloudflare.com",
                "data:"
            ],
            // Allow data: for inline images such as avatars encoded in data URLs
            "img-src" => [
                "'self'",
                "data:",
                // Background images used in UI
                "https://images.unsplash.com"
            ],
            "connect-src" => ["'self'", "https:", "wss:", "wss://*.cloudflare.com", "https://challenges.cloudflare.com"],
            "object-src" => ["'none'"],
            "base-uri" => ["'self'"],
            "form-action" => ["'self'"],
            "frame-src" => ["'self'", "https://challenges.cloudflare.com"],
            "child-src" => ["'none'"],
            "media-src" => ["'self'"],
            "manifest-src" => ["'self'"],
            "worker-src" => ["'self'"],
            "frame-ancestors" => ["'none'"],
            // Report endpoint
            "report-uri" => ["/apis/csp_report.php"],
        ];

        // Page-specific relaxations
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === 'login_users_mmtvtc.php' || strpos($_SERVER['SCRIPT_NAME'] ?? '', 'auth/login_users_mmtvtc.php') !== false) {
            // Allow Cloudflare Turnstile only on login page
            $cspDirectives['script-src'][] = 'https://challenges.cloudflare.com';
            $cspDirectives['frame-src'][] = 'https://challenges.cloudflare.com';
        }

        // Optionally advertise Report-To; browsers may honor this in addition to report-uri
        $reportTo = json_encode([
            'group' => 'csp-endpoint',
            'max_age' => 10886400,
            'endpoints' => [ [ 'url' => (($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($host ?: 'localhost') . '/apis/csp_report.php') ] ],
            'include_subdomains' => true
        ]);
        if (!headers_sent()) {
            header('Report-To: ' . $reportTo);
        }

        $csp = '';
        foreach ($cspDirectives as $directive => $values) {
            $csp .= $directive . ' ' . implode(' ', (array)$values) . '; ';
        }
        
        // Add upgrade-insecure-requests directive for HTTPS enforcement
        $csp .= 'upgrade-insecure-requests; ';
        
        $csp = trim($csp);

        // Use Report-Only in development; enforce in production
        // Allow env override CSP_REPORT_ONLY=1 to keep report-only in production until logs are clean
        $envForceReportOnly = getenv('CSP_REPORT_ONLY');
        $useReportOnly = (!$isProduction) || ($envForceReportOnly === '1' || strtolower((string)$envForceReportOnly) === 'true');

        if ($useReportOnly) {
            header("Content-Security-Policy-Report-Only: $csp");
        } else {
            header("Content-Security-Policy: $csp");
        }

        // Expose nonce for templates if needed
        if (!headers_sent()) {
            header('X-CSP-Nonce: ' . $nonce);
        }
    }
}

// Apply immediately when included
applyContentSecurityPolicy();
?>


