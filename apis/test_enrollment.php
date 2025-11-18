<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Suppress any output before JSON
ob_start();

try {
    require_once __DIR__ . '/../security/db_connect.php';
    require_once __DIR__ . '/../security/session_config.php';
    
    // Check if PDO is available
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection not available');
    }
    
    // Check authentication
    $isAdmin = isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 2;
    if(!$isAdmin){ 
        ob_clean();
        http_response_code(403); 
        echo json_encode(['success'=>false,'message'=>'Forbidden - User role: ' . ($_SESSION['user_role'] ?? 'not set')]); 
        exit; 
    }
    
    // Simple test query
    $studentNumber = $_GET['student_number'] ?? '';
    if (empty($studentNumber)) {
        ob_clean();
        echo json_encode(['success'=>false,'message'=>'Student number required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, student_number, first_name, last_name, course, batch, enrollment_status, enrollment_start_date, enrollment_end_date, created_at FROM students WHERE student_number=? ORDER BY created_at DESC");
    $stmt->execute([$studentNumber]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    ob_clean();
    echo json_encode(['success'=>true,'data'=>$rows]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Test enrollment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error: ' . $e->getMessage()]);
    exit;
}
?>
