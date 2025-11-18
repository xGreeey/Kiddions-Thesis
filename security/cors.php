<?php
// Centralized CORS configuration

if (!function_exists('applyCorsHeaders')) {
    function applyCorsHeaders() {
        if (headers_sent()) return;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isProduction = (getenv('APP_ENV') === 'production') || (!in_array($host, ['localhost', '127.0.0.1', 'localhost:8000']));

        // Allowed origins
        $allowlist = $isProduction
            ? [
                // Add your production origins here
                'https://' . $host,
              ]
            : [
                'http://localhost',
                'http://127.0.0.1',
                'http://localhost:8000',
                'http://127.0.0.1:8000',
                'https://localhost',
                'https://127.0.0.1',
            ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $isApi = (strpos($path, '/apis/') === 0);

        // Only emit CORS headers for API routes
        if ($isApi && $origin && in_array($origin, $allowlist, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization');
            header('Access-Control-Expose-Headers: X-CSRF-Token');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }

        // Handle preflight for APIs only
        if ($isApi && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS')) {
            http_response_code(204);
            exit();
        }
    }
}

applyCorsHeaders();
?>


