<?php
require_once __DIR__ . '/../security/session_config.php';
require_once __DIR__ . '/../security/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

$raw = file_get_contents('php://input');
$data = [];
if (is_string($raw) && $raw !== '') {
    $dec = json_decode($raw, true);
    if (is_array($dec)) { $data = $dec; }
}

$event = isset($data['event']) ? (string)$data['event'] : 'unknown';
$page  = isset($data['page']) ? (string)$data['page'] : ($_SERVER['HTTP_REFERER'] ?? 'unset');
$info  = isset($data['info']) ? $data['info'] : [];

try {
    $log = [
        'CLIENT_EVENT' => $event,
        'sid' => session_id(),
        'auth' => (isset($_SESSION['email']) || isset($_SESSION['id']) || isset($_SESSION['user_id'])) ? 1 : 0,
        'role' => $_SESSION['user_role'] ?? null,
        'page' => $page,
        'cookies' => array_keys($_COOKIE ?? []),
        'info' => $info,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ];
    error_log('CLIENT_EVENT: ' . json_encode($log));
} catch (Throwable $e) {}

echo json_encode([ 'success' => true ]);
exit;
?>

