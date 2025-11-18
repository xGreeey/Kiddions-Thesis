<?php
require_once __DIR__ . '/../security/session_config.php';

header('Content-Type: application/json; charset=UTF-8');

// Basic auth check that works across roles
$isValid = isSessionValid();
$hasIdentity = isset($_SESSION['email']) || isset($_SESSION['id']) || isset($_SESSION['user_id']);

$role = isset($_SESSION['is_role']) ? (int)$_SESSION['is_role'] : (isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : null); // 0 student, 1 instructor, 2 admin
$resp = [
	'success' => true,
	'authenticated' => ($isValid && $hasIdentity),
	'role' => $role,
    'sid' => session_id(),
];

// Server-side tracing to error.log for diagnostics
try {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ref = $_SERVER['HTTP_REFERER'] ?? 'unset';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $ck = implode(',', array_keys($_COOKIE ?? []));
    error_log('SESSION_STATUS: uri=' . $uri . ' sid=' . session_id() . ' auth=' . ($resp['authenticated'] ? '1' : '0') . ' role=' . (string)$role . ' cookies=' . $ck . ' referer=' . $ref . ' ua=' . $ua);
} catch (Throwable $e) {
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>

