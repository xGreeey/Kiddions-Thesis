<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin role
requireAnyRole(['admin']);

try {
    // Get total count from students table
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_students FROM students");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalStudents = (int)($result['total_students'] ?? 0);
    
    // Get additional statistics
    $stats = [
        'total_students' => $totalStudents,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Get count by course (optional additional data)
    $courseStmt = $pdo->prepare("
        SELECT 
            course, 
            COUNT(*) as count 
        FROM students 
        WHERE course IS NOT NULL AND course != '' 
        GROUP BY course 
        ORDER BY count DESC
    ");
    $courseStmt->execute();
    $courseStats = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['by_course'] = $courseStats;
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Throwable $e) {
    error_log('Student count API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch student count',
        'error' => $e->getMessage()
    ]);
}
?>
