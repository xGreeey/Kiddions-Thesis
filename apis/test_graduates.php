<?php
session_start();
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Simple test query
    $query = "SELECT COUNT(*) as total FROM students";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'total_students' => $result['total'],
        'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
        'session_role' => $_SESSION['role'] ?? 'not_set'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
