<?php
// Request guard: method filtering, size limits, API content-type validation, and global rate limiting

if (!function_exists('guardRequest')) {
    function guardRequest($pdo = null) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Block risky/legacy HTTP methods
        $blockedMethods = ['TRACE', 'TRACK', 'DEBUG'];
        if (in_array($method, $blockedMethods, true)) {
            http_response_code(405);
            header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            exit();
        }

        // Enforce request size limits (2MB default, 10MB for multipart)
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        $maxDefault = 2 * 1024 * 1024;
        $maxMultipart = 10 * 1024 * 1024;
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
        $maxAllowed = $isMultipart ? $maxMultipart : $maxDefault;
        if ($contentLength > 0 && $contentLength > $maxAllowed) {
            http_response_code(413);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Request entity too large']);
            exit();
        }

        // API content-type validation for state-changing requests
        $isApi = (strpos($path, '/apis/') === 0);
        if ($isApi && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $allowedTypes = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];
            $matched = false;
            foreach ($allowedTypes as $allowed) {
                if (stripos($contentType, $allowed) === 0) { $matched = true; break; }
            }
            if (!$matched) {
                http_response_code(415);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unsupported Media Type']);
                exit();
            }
        }

        // Global lightweight rate limiting (DB-backed if available)
        if ($pdo) {
            if (function_exists('checkRateLimit')) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                if (!checkRateLimit($pdo, $ip, 'global', 300, 300)) { // 300 req / 5 min baseline
                    if (function_exists('logSecurityEvent')) {
                        logSecurityEvent('GLOBAL_RATE_LIMIT_EXCEEDED', ['ip' => $ip]);
                    }
                    http_response_code(429);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Too many requests']);
                    exit();
                }
            }
        }
    }
}

// Attempt to get $pdo if available
if (!isset($pdo)) {
    // db_connect.php is already included by core/session config, but guard against missing
    if (file_exists(__DIR__ . '/db_connect.php')) {
        require_once __DIR__ . '/db_connect.php';
    }
}

guardRequest(isset($pdo) ? $pdo : null);
?>


