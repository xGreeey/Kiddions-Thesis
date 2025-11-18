<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/abuse_protection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_verified']) || !$_SESSION['user_verified'] || 
    !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $abuseProtection = initializeAbuseProtection($pdo);
    
    if (!$abuseProtection) {
        throw new Exception('Failed to initialize abuse protection system');
    }
    
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'stats':
            $timeframe = $_GET['timeframe'] ?? '24h';
            $stats = $abuseProtection->getAbuseStats($timeframe);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'timeframe' => $timeframe,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            break;
            
        case 'recent_attempts':
            $limit = (int)($_GET['limit'] ?? 50);
            $stmt = $pdo->prepare("
                SELECT 
                    identifier,
                    identifier_type,
                    action_type,
                    ip_address,
                    success,
                    details,
                    created_at
                FROM abuse_tracking 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'attempts' => $attempts,
                    'limit' => $limit
                ]
            ]);
            break;
            
        case 'locked_accounts':
            $stmt = $pdo->prepare("
                SELECT 
                    identifier,
                    identifier_type,
                    ip_address,
                    details,
                    locked_until,
                    created_at
                FROM abuse_tracking 
                WHERE locked_until > NOW()
                ORDER BY locked_until ASC
            ");
            $stmt->execute();
            $lockedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'locked_accounts' => $lockedAccounts
                ]
            ]);
            break;
            
        case 'admin_alerts':
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    alert_type,
                    identifier,
                    identifier_type,
                    details,
                    status,
                    created_at,
                    reviewed_at,
                    reviewed_by
                FROM admin_alerts 
                WHERE status = 'pending'
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'alerts' => $alerts
                ]
            ]);
            break;
            
        case 'unlock_account':
            $identifier = $_POST['identifier'] ?? '';
            $identifierType = $_POST['identifier_type'] ?? '';
            
            if (empty($identifier) || empty($identifierType)) {
                throw new Exception('Missing required parameters');
            }
            
            // Clear lockout records
            $stmt = $pdo->prepare("
                UPDATE abuse_tracking 
                SET locked_until = NULL 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND locked_until > NOW()
            ");
            $stmt->execute([$identifier, $identifierType]);
            
            // Log admin action
            logSecurityEvent('ADMIN_UNLOCKED_ACCOUNT', [
                'admin_id' => $_SESSION['id'],
                'identifier' => $identifier,
                'identifier_type' => $identifierType,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Account unlocked successfully'
            ]);
            break;
            
        case 'resolve_alert':
            $alertId = (int)($_POST['alert_id'] ?? 0);
            $action = $_POST['action'] ?? '';
            
            if ($alertId <= 0 || empty($action)) {
                throw new Exception('Missing required parameters');
            }
            
            $status = in_array($action, ['resolved', 'dismissed']) ? $action : 'reviewed';
            
            $stmt = $pdo->prepare("
                UPDATE admin_alerts 
                SET status = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $_SESSION['id'], $alertId]);
            
            // Log admin action
            logSecurityEvent('ADMIN_RESOLVED_ALERT', [
                'admin_id' => $_SESSION['id'],
                'alert_id' => $alertId,
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Alert resolved successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log('Abuse monitoring API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process request',
        'error' => $e->getMessage()
    ]);
}
?>
