<?php
require_once '../security/validation.php';
require_once '../security/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Get the action from the request
$action = $_GET['action'] ?? '';

try {
    // Database connection is already established in db_connect.php
    
    if ($action === 'check_surname') {
        $surname = $_GET['surname'] ?? '';
        
        if (empty($surname)) {
            echo json_encode(['success' => false, 'message' => 'Surname is required']);
            exit;
        }
        
        // Sanitize the surname input
        $surname = sanitizeAndValidate($surname, 'string', ['max_length' => 255]);
        
        // Check if surname exists in mmtvtc_users table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM mmtvtc_users WHERE last_name = ?");
        $stmt->execute([$surname]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exists = $result['count'] > 0;
        
        echo json_encode([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'Surname already exists' : 'Surname is available'
        ]);
        
    } elseif ($action === 'check_student_number') {
        $studentNumber = $_GET['student_number'] ?? '';
        
        if (empty($studentNumber)) {
            echo json_encode(['success' => false, 'message' => 'Student number is required']);
            exit;
        }
        
        // Sanitize the student number input
        $studentNumber = sanitizeAndValidate($studentNumber, 'string', ['max_length' => 50]);
        
        // Check if student number exists in mmtvtc_users table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM mmtvtc_users WHERE student_number = ?");
        $stmt->execute([$studentNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exists = $result['count'] > 0;
        
        echo json_encode([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'Student number already exists' : 'Student number is available'
        ]);
        
    } elseif ($action === 'check_email') {
        $email = $_GET['email'] ?? '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }
        
        // Sanitize the email input
        $email = sanitizeAndValidate($email, 'string', ['max_length' => 255]);
        
        // Check if email exists in mmtvtc_users table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM mmtvtc_users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exists = $result['count'] > 0;
        
        echo json_encode([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? 'Email address already exists' : 'Email address is available'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in duplicate_check.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in duplicate_check.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
