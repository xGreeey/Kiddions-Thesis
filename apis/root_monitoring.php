<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 3) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Access denied']);
    exit();
}

$action = $_GET['action'] ?? 'activities';

try {
    switch ($action) {
        case 'activities':
            // Last 50 security events
            $logFile = dirname(__DIR__) . '/logs/security.log';
            $lines = [];
            if (is_readable($logFile)) {
                $fp = fopen($logFile, 'r');
                if ($fp) {
                    $buffer = [];
                    while (!feof($fp)) {
                        $line = fgets($fp);
                        if ($line !== false) { $buffer[] = trim($line); }
                    }
                    fclose($fp);
                    $lines = array_slice($buffer, -50);
                }
            }
            echo json_encode(['success'=>true,'data'=>array_map(function($l){ return json_decode($l, true) ?: $l; }, $lines)]);
            break;

        case 'threats':
            // Recent abuse_tracking entries
            $stmt = $pdo->query("SELECT id, identifier, identifier_type, action, ip_address, success, created_at FROM abuse_tracking ORDER BY created_at DESC LIMIT 50");
            $rows = $stmt->fetchAll();
            echo json_encode(['success'=>true,'data'=>$rows]);
            break;

        case 'health':
            // Simple DB and log health
            $dbOk = false; $now = null;
            try { $stmt = $pdo->query('SELECT NOW() AS now'); $now = $stmt->fetchColumn(); $dbOk = true; } catch (Throwable $e) {}
            $logDir = dirname(__DIR__) . '/logs';
            $sizes = [];
            foreach (['error.log','security.log','otp_security.log'] as $f) {
                $p = $logDir . '/' . $f; $sizes[$f] = is_file($p) ? filesize($p) : 0;
            }
            echo json_encode(['success'=>true,'data'=>['db_ok'=>$dbOk,'db_time'=>$now,'log_sizes'=>$sizes]]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Internal error']);
}
?>


