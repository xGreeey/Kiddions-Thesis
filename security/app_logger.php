<?php
// Application-level structured logging to logs/error.log (JSON Lines)

if (!function_exists('appLogWrite')) {
    function appLogWrite($level, $event, $details = [], $throwable = null) {
        $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'event' => $event,
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'referer' => $_SERVER['HTTP_REFERER'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ],
            'session' => [
                'id' => session_id(),
                'user_id' => $_SESSION['id'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'student_number' => $_SESSION['student_number'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'login_time' => $_SESSION['login_time'] ?? null,
                'last_activity' => $_SESSION['last_activity'] ?? null,
            ],
            'security' => [
                'csrf_token_present' => !empty($_SESSION['csrf_token']),
                'csrf_header' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                'cookie_names' => array_keys($_COOKIE ?? []),
            ],
            'request_body' => [
                'post_keys' => array_keys($_POST ?? []),
                'get_keys' => array_keys($_GET ?? []),
                'files' => array_map(function($f){ return isset($f['name']) ? $f['name'] : null; }, $_FILES ?? []),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
                'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null,
            ],
            'details' => $details,
        ];

        if ($throwable instanceof Throwable) {
            $entry['error'] = [
                'type' => get_class($throwable),
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => explode("\n", $throwable->getTraceAsString()),
            ];
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error.log', $line, FILE_APPEND | LOCK_EX);

        // Forward to SIEM if endpoint configured
        $siemEndpoint = getenv('SIEM_WEBHOOK_URL') ?: '';
        if ($siemEndpoint) {
            try {
                $ch = curl_init($siemEndpoint);
                if ($ch) {
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    curl_exec($ch);
                    curl_close($ch);
                }
            } catch (Throwable $__) { /* best-effort */ }
        }
    }
}

if (!function_exists('appLogError')) {
    function appLogError($event, $details = [], $throwable = null) {
        appLogWrite('ERROR', $event, $details, $throwable);
    }
}

if (!function_exists('appLogInfo')) {
    function appLogInfo($event, $details = []) {
        appLogWrite('INFO', $event, $details, null);
    }
}

?>


