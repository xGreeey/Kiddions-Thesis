<?php
session_start();
require_once 'security/db_connect.php';
require_once 'session_config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');
    
    if (!isset($_SESSION['reset_email']) || !isset($_SESSION['student_number'])) {
        $response['message'] = "Session expired. Please submit the forgot password form again.";
    } else {
        $email = $_SESSION['reset_email'];
        $student_number = $_SESSION['student_number'];
        
        $stmt = $mysqli->prepare("SELECT id, student_number, first_name, last_name, email FROM students WHERE student_number = ? AND email = ?");
        $stmt->bind_param("ss", $student_number, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {

            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes')); 

            $update_stmt = $mysqli->prepare("UPDATE students SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $token, $expires_at, $user['id']);
            
            if ($update_stmt->execute()) {

                if (send_reset_email($user, $token)) {
                    $response['success'] = true;
                    $response['message'] = "Password reset email has been resent.";
                } else {
                    $response['message'] = "Failed to send password reset email. Please try again.";
                }
            } else {
                $response['message'] = "An error occurred. Please try again later.";
            }
            
            $update_stmt->close();
        } else {
            $response['message'] = "User not found. Please submit the forgot password form again.";
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function send_reset_email($user, $token) {
    $mail = new PHPMailer(true);
    $success = false;
    
    try {

        $reset_url = "http://" . $_SERVER['HTTP_HOST'] . "/nsZLoj1b49kcshf6JhimM3Tvdn1rLK?token=" . $token . "&email=" . urlencode($user['email']);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($user['email']);
        
        $mail->isHTML(true);
        $mail->Subject = 'MMTVTC Password Reset';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #003366;'>MMTVTC Password Reset</h2>
                </div>
                <p>Hello {$user['first_name']},</p>
                <p>We received a request to reset your password for your MMTVTC student account.</p>
                <p>Please click the button below to reset your password:</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$reset_url' style='background-color: #ffcc00; color: #003366; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password</a>
                </div>
                <p>If the button above doesn't work, you can also click on the link below or copy and paste it into your browser:</p>
                <p><a href='$reset_url'>$reset_url</a></p>
                <p>This link will expire in 5 minutes.</p>
                <p>If you didn't request a password reset, you can safely ignore this email.</p>
                <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666;'>
                    <p>MMTVTC - Manila's Metropolitan Technical-Vocational Training Center</p>
                </div>
            </div>
        ";
        
        $mail->AltBody = "Hello {$user['first_name']},

We received a request to reset your password for your MMTVTC student account.

Please visit the following link to reset your password: $reset_url

This link will expire in 60 minutes.

If you didn't request a password reset, you can safely ignore this email.

MMTVTC - Manila's Metropolitan Technical-Vocational Training Center";
        
        $mail->send();
        error_log("Password reset email sent to: {$user['email']}");
        $success = true;
    } catch (Exception $e) {
        error_log("Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        $success = false;
    }
    
    return $success;
}
?>