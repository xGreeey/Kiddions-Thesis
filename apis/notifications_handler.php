<?php
// Clean notifications handler - follows exact same pattern as announcement_handler.php
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/error_handler.php';
require_once '../security/auth_functions.php';
require_once '../security/csrf.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');  

// session is started inside session_config.php

// Centralized auth: reads must be authenticated; mutations require admin
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    requireAuth();
} else {
    requireAnyRole(['admin']);
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Fetch all notifications
            $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
            $notifications = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $notifications,
                'csrf_token' => $_SESSION['csrf_token'] ?? ''
            ]);
            break;

        case 'POST':
            csrfRequireValid();
            // Request diagnostics for debugging delivery issues
            error_log('Notifications POST Debug: ' . json_encode([
                'session_id' => session_id(),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unset',
                'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unset',
                'has_post' => !empty($_POST),
                'post_keys' => array_keys($_POST ?? []),
                'has_get' => !empty($_GET),
                'get_keys' => array_keys($_GET ?? []),
                'x_csrf_header' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'unset',
                'cookie_sid' => $_COOKIE['PHPSESSID'] ?? 'unset'
            ]));
            // If JSON, decode and merge into $_POST for unified handling
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($ct, 'application/json') === 0) {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if (!isset($_POST[$k])) { $_POST[$k] = $v; }
                    }
                }
                error_log('Notifications POST JSON merge: ' . json_encode([
                    'raw_len' => isset($raw) ? strlen($raw) : 0,
                    'merged_post_keys' => array_keys($_POST ?? [])
                ]));
            }

            // Fallback: if $_POST is empty, attempt to parse raw urlencoded body
            if (empty($_POST)) {
                $rawBody = file_get_contents('php://input');
                if (is_string($rawBody) && $rawBody !== '') {
                    $parsed = [];
                    parse_str($rawBody, $parsed);
                    if (!empty($parsed) && is_array($parsed)) {
                        foreach ($parsed as $k => $v) {
                            if (!isset($_POST[$k])) { $_POST[$k] = $v; }
                        }
                    }
                }
            }

            // Accept action from POST or query string as fallback (handles cases where parsing fails upstream)
            $action = $_POST['action'] ?? ($_GET['action'] ?? '');
            // Heuristic: if no action provided but we have notification fields, assume add_notification
            if ($action === '' && isset($_POST['title'], $_POST['message'], $_POST['type'])) {
                $action = 'add_notification';
            }
            
            switch ($action) {
                case 'add_notification':
                    // CSRF already enforced globally for POST via csrfRequireValid()
                    
                    // Get and validate input data
                    $title = trim($_POST['title'] ?? '');
                    $message = trim($_POST['message'] ?? '');
                    $icon = trim($_POST['icon'] ?? '');
                    $type = trim($_POST['type'] ?? '');
                    $time = trim($_POST['time'] ?? 'now');
                    $customTime = trim($_POST['customTime'] ?? '');
                    
                    // Debug: Log received data
                    error_log("Notification data received: " . json_encode([
                        'title' => $title,
                        'message' => $message,
                        'icon' => $icon,
                        'type' => $type,
                        'time' => $time,
                        'customTime' => $customTime
                    ]));
                    
                    // Validate required fields (time is optional and defaults server-side)
                    if (empty($title) || empty($message) || empty($icon) || empty($type)) {
                        echo json_encode(['success' => false, 'message' => 'All fields are required']);
                        exit();
                    }
                    
                    // Prepare time display
                    $time_display = $time;
                    if ($time === 'custom' && !empty($customTime)) {
                        $time_display = $customTime;
                    } else {
                        // Convert time codes to display text
                        $time_map = [
                            'now' => 'Just now',
                            '5min' => '5 min ago',
                            '1hour' => '1 hour ago',
                            '2hours' => '2 hours ago',
                            '1day' => '1 day ago'
                        ];
                        $time_display = $time_map[$time] ?? 'Just now';
                    }
                    
                    // Insert notification with detailed error handling
                    try {
                        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, icon, type, time_display, custom_time, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                        $result = $stmt->execute([$title, $message, $icon, $type, $time_display, $customTime]);
                        
                        if ($result) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Notification added successfully',
                                'data' => ['id' => $pdo->lastInsertId()]
                            ]);
                        } else {
                            $errorInfo = $stmt->errorInfo();
                            error_log("Notification insert failed: " . json_encode($errorInfo));
                            echo json_encode([
                                'success' => false, 
                                'message' => 'Failed to add notification: ' . $errorInfo[2]
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log("Notification insert exception: " . $e->getMessage());
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Database error: ' . $e->getMessage()
                        ]);
                    }
                    break;
                    
                case 'delete_notification':
                    // CSRF already enforced globally for POST via csrfRequireValid()
                    
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                        exit();
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $result = $stmt->execute([$id]);
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
                    break;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Notifications handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>