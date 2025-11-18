<?php
// Centralized CSRF protection utilities (report-only by default)

if (!function_exists('csrfEnsureToken')) {
    function csrfEnsureToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (!headers_sent()) {
            header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfGetToken')) {
    function csrfGetToken() {
        return $_SESSION['csrf_token'] ?? csrfEnsureToken();
    }
}

if (!function_exists('csrfValidateToken')) {
    function csrfValidateToken($token) {
        $expected = $_SESSION['csrf_token'] ?? '';
        return !empty($expected) && is_string($token) && hash_equals($expected, $token);
    }
}

if (!function_exists('csrfInputField')) {
    function csrfInputField() {
        $token = htmlspecialchars(csrfGetToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('csrfMetaTag')) {
    function csrfMetaTag() {
        $token = htmlspecialchars(csrfGetToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}

if (!function_exists('csrfReportOnlyCheck')) {
    function csrfReportOnlyCheck() {
        // Only log POST, PUT, PATCH, DELETE without enforcing yet
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $paramToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            $token = !empty($headerToken) ? $headerToken : $paramToken;

            if (!csrfValidateToken($token)) {
                // Log the failure with detailed debugging; do not block yet
                $debug = [
                    'method' => $method,
                    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'received_token' => $token ?: 'EMPTY',
                    'expected_token' => $_SESSION['csrf_token'] ?? 'NO_SESSION_TOKEN',
                    'has_header' => !empty($headerToken),
                    'has_param' => !empty($paramToken),
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unset',
                    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unset',
                    'session_id' => session_id(),
                    'post_keys' => array_keys($_POST ?? []),
                    'get_keys' => array_keys($_GET ?? []),
                    'cookies' => array_keys($_COOKIE ?? []),
                    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'unset',
                    'referer' => $_SERVER['HTTP_REFERER'] ?? 'unset',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unset'
                ];
                if (!function_exists('logSecurityEvent')) {
                    error_log('CSRF_REPORT_ONLY: ' . json_encode($debug));
                } else {
                    logSecurityEvent('CSRF_REPORT_ONLY', $debug);
                }
            }
        }
    }
}

if (!function_exists('csrfRequireValid')) {
    function csrfRequireValid() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return; // Only enforce for mutating methods
        }

        // Accept token from header first, then from params (POST preferred, fallback to GET for legacy forms)
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $paramToken = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
        $token = $headerToken !== '' ? $headerToken : $paramToken;

        if (!csrfValidateToken($token)) {
            $debug = [
                'method' => $method,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'has_header' => $headerToken !== '',
                'has_param' => $paramToken !== '',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unset',
                'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unset',
                'session_id' => session_id(),
            ];
            if (function_exists('logSecurityEvent')) {
                logSecurityEvent('CSRF_BLOCKED', $debug);
            } else {
                error_log('CSRF_BLOCKED: ' . json_encode($debug));
            }
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit();
        }
    }
}

// Ensure token and perform report-only check on include
csrfEnsureToken();
csrfReportOnlyCheck();
?>


