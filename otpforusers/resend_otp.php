<?php
require_once __DIR__ . '/../security/session_config.php';
require_once __DIR__ . '/../security/db_connect.php';
require_once __DIR__ . '/send_otp.php';
// Unified error display via security/error_handler.php
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resend'])) {
    $response = array('success' => false, 'message' => '');
    
    if (!isset($_SESSION['email'])) {
        $response['message'] = "Session expired. Please log in again.";
    } else {
        $email = $_SESSION['email'];
        
        $stmt = $pdo->prepare("SELECT id FROM mmtvtc_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {

            $result = send_otp($email, $pdo);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = "Verification email has been resent.";
            } else {
                $response['message'] = "Failed to send verification email. Please try again.";
            }
        } else {
            $response['message'] = "User not found. Please log in again.";
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>