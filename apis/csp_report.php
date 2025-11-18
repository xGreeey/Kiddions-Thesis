<?php
// Endpoint to receive CSP violation reports
// Basic anti-abuse controls and validation

// Only accept JSON POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid content type']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data === null) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Normalize report body according to CSP spec
$report = $data['csp-report'] ?? $data['csp_report'] ?? $data;

// Basic validation: require at least a directive and blocked URI
if (!is_array($report) || (!isset($report['effective-directive']) && !isset($report['violated-directive']))) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid report body']);
    exit();
}

// Sample reports to avoid log storms: keep ~50% randomly
if (mt_rand(1, 100) > 50) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit();
}

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Rotate log if it grows too large (~5MB)
$logFile = $logDir . '/csp_reports.log';
$maxSize = 5 * 1024 * 1024; // 5MB
if (@file_exists($logFile) && @filesize($logFile) > $maxSize) {
    @rename($logFile, $logFile . '.' . date('Ymd_His'));
}

$entry = [
    'timestamp' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'report' => $report,
];

@file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
?>


