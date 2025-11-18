<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

error_reporting(E_ALL);
// Unified error display via security/error_handler.php

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');


// session is started inside session_config.php

// Authentication: allow admin role or presence of common ids
// Debug session data (can be removed in production)
// error_log("Announcement Auth Check - Session data: " . json_encode([
//     'user_role' => $_SESSION['user_role'] ?? 'not_set',
//     'id' => $_SESSION['id'] ?? 'not_set',
//     'user_id' => $_SESSION['user_id'] ?? 'not_set',
//     'student_number' => $_SESSION['student_number'] ?? 'not_set',
//     'email' => $_SESSION['email'] ?? 'not_set',
//     'session_id' => session_id()
// ]));

// Centralized auth: reads require auth; mutations require admin
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    requireAuth();
} else {
    requireAnyRole(['admin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequireValid();
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'publish_announcement':
                // CSRF already enforced globally for POST via csrfRequireValid()
                // Get and validate input data
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $type = $_POST['type'] ?? 'general';
                $priority = $_POST['priority'] ?? 'normal';
                $audience = $_POST['audience'] ?? 'all';
                $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
                
                // Validation
                if (empty($title)) {
                    throw new Exception('Title is required');
                }
                
                if (empty($content)) {
                    throw new Exception('Content is required');
                }
                
                if (strlen($title) > 255) {
                    throw new Exception('Title is too long (max 255 characters)');
                }
                
                if (strlen($content) > 1000) {
                    throw new Exception('Content is too long (max 1000 characters)');
                }
                
                // Log the data being inserted for debugging
                error_log("Inserting announcement: Title=$title, Type=$type, Content length=" . strlen($content));
                
                // Insert announcement with all fields
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, content, type, priority, audience, date_created, expiry_date, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, 1, NOW(), NOW())
                ");
                
                $result = $stmt->execute([$title, $content, $type, $priority, $audience, $expiry]);
                
                if ($result) {
                    $announcementId = $pdo->lastInsertId();
                    error_log("Announcement inserted successfully with ID: $announcementId");
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Announcement published successfully',
                        'announcement_id' => $announcementId
                    ]);
                } else {
                    throw new Exception('Failed to insert announcement into database');
                }
                break;
                
            case 'get_announcements':
                $stmt = $pdo->prepare("
                    SELECT id, title, content, type, priority, audience, date_created, expiry_date, is_active, created_at
                    FROM announcements 
                    ORDER BY date_created DESC 
                    LIMIT 3
                ");
                $stmt->execute();
                $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get server current time for consistent client display
                $serverNow = $pdo->query("SELECT NOW() AS now")->fetch(PDO::FETCH_ASSOC)['now'] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'announcements' => $announcements,
                    'server_now' => $serverNow
                ]);
                break;
                
            case 'toggle_announcement':
                // CSRF already enforced globally for POST via csrfRequireValid()
                $id = intval($_POST['id'] ?? 0);
                $is_active = intval($_POST['is_active'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid announcement ID');
                }
                
                $stmt = $pdo->prepare("UPDATE announcements SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$is_active, $id]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Announcement status updated']);
                } else {
                    throw new Exception('Failed to update announcement status');
                }
                break;
                
            case 'delete_announcement':
                // CSRF already enforced globally for POST via csrfRequireValid()
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid announcement ID');
                }
                
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                $result = $stmt->execute([$id]);
                
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
                } else {
                    throw new Exception('Failed to delete announcement or announcement not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action: ' . $action);
        }
        
    } catch (Exception $e) {
        error_log("Announcement handler error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>