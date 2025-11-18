<?php
require_once '../security/db_connect.php';

error_reporting(E_ALL);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $surname = trim($_GET['surname'] ?? '');
        
        if (empty($surname)) {
            echo json_encode([
                'success' => true,
                'exists' => false,
                'message' => 'Surname is required'
            ]);
            exit;
        }
        
        // Check if surname exists in add_trainees table
        $stmt = $pdo->prepare("SELECT id, firstname, surname FROM add_trainees WHERE surname = ? LIMIT 1");
        $stmt->execute([$surname]);
        $trainee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($trainee) {
            echo json_encode([
                'success' => true,
                'exists' => true,
                'message' => 'This surname already exists in the system',
                'trainee' => [
                    'id' => $trainee['id'],
                    'name' => $trainee['firstname'] . ' ' . $trainee['surname']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'exists' => false,
                'message' => 'Surname is available'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Surname validation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error checking surname availability',
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
