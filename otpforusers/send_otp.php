<?php
require_once __DIR__ . '/../security/session_config.php';
require_once __DIR__ . '/../security/db_connect.php';
require_once __DIR__ . '/../security/abuse_protection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Only require autoload if it hasn't been loaded
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function send_otp($email, $pdo) {
    try {
        // Debug logging
        error_log("DEBUG: send_otp called for email: $email");
        
        // Initialize abuse protection
        $abuseProtection = initializeAbuseProtection($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check abuse protection before processing
        if ($abuseProtection) {
            $abuseCheck = checkAbuseProtection($email, 'email', 'otp_request', $ip);
            if (!$abuseCheck['allowed']) {
                error_log("DEBUG: OTP request blocked by abuse protection: " . $abuseCheck['reason']);
                return [
                    'success' => false,
                    'error' => $abuseCheck['reason'],
                    'message' => $abuseCheck['message'],
                    'retry_after' => $abuseCheck['retry_after'] ?? null
                ];
            }
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("DEBUG: Invalid email format: $email");
            // Record invalid email attempt
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_request', $ip, false, ['reason' => 'invalid_email']);
            }
            return false;
        }
        
        // Check if user exists (use 'id' instead of 'student_id')
        $stmt = $pdo->prepare("SELECT id, email, is_verified FROM mmtvtc_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("DEBUG: User not found for email: $email");
            // Record failed attempt for non-existent user
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_request', $ip, false, ['reason' => 'user_not_found']);
            }
            return false;
        }
        
        error_log("DEBUG: User found: " . print_r($user, true));
        
        // Generate OTP
        $otp = rand(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        error_log("DEBUG: Generated OTP: $otp, expires: $expires_at");
        
        // Update OTP in database (simplified - no otp_attempts column)
        $stmt = $pdo->prepare("UPDATE mmtvtc_users SET otp_code = ?, otp_expires_at = ? WHERE email = ?");
        if (!$stmt->execute([$otp, $expires_at, $email])) {
            error_log("DEBUG: Failed to update OTP in database: " . implode(" ", $stmt->errorInfo()));
            return false;
        }
        
        // Check if update affected any rows
        if ($stmt->rowCount() === 0) {
            error_log("DEBUG: No rows updated for email: $email (email might not exist)");
            return false;
        }
        
        error_log("DEBUG: OTP updated in database successfully, rows affected: " . $stmt->rowCount());
        
        // Store OTP in session for verification (plain text for simplicity)
        $_SESSION['email_otp'] = $otp;
        $_SESSION['otp_expires'] = strtotime($expires_at);
        $_SESSION['otp_email'] = $email;
        
        error_log("DEBUG: OTP stored in session");
        
        // Send email
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // Disable SSL verification for development
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->Sender = SMTP_FROM_EMAIL; // envelope from
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email);
            if (!empty(getenv('OTP_DIAG_BCC'))) {
                $mail->addBCC(getenv('OTP_DIAG_BCC'));
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code - MMTVTC';
            $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #003366;'>MMTVTC Login Verification</h2>
                <p>Your OTP code is:</p>
                <div style='background: #f0f0f0; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; color: #003366; margin: 20px 0;'>
                    $otp
                </div>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            </div>
            ";
            $mail->AltBody = "Your OTP code is: $otp. It will expire in 5 minutes.";
            
            $sentOk = $mail->send();
            error_log("DEBUG: OTP email sent successfully to: $email");
            error_log('DEBUG: Mail send() returned: ' . ($sentOk ? 'true' : 'false'));
            
            // Record successful OTP request
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_request', $ip, true, ['otp_sent' => true]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("DEBUG: PHPMailer Error: {$mail->ErrorInfo}");
            error_log("DEBUG: Exception: " . $e->getMessage());
            
            // Record failed OTP request
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_request', $ip, false, ['reason' => 'email_send_failed', 'error' => $e->getMessage()]);
            }
            
            return false;
        }
        
    } catch (Exception $e) {
        error_log("DEBUG: General error in send_otp function: " . $e->getMessage());
        
        // Record failed OTP request
        if ($abuseProtection) {
            $abuseProtection->recordAttempt($email, 'email', 'otp_request', $ip, false, ['reason' => 'system_error', 'error' => $e->getMessage()]);
        }
        
        return false;
    }
}

// Simple OTP verification function
function verify_otp($inputOTP, $email, $pdo) {
    try {
        error_log("DEBUG: Verifying OTP: $inputOTP for email: $email");
        
        // Initialize abuse protection
        $abuseProtection = initializeAbuseProtection($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check session data
        if (!isset($_SESSION['email_otp']) || !isset($_SESSION['otp_email']) || $_SESSION['otp_email'] !== $email) {
            error_log("DEBUG: Invalid session data");
            // Record failed verification attempt
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_verify', $ip, false, ['reason' => 'invalid_session']);
            }
            return false;
        }
        
        // Check expiration
        if (!isset($_SESSION['otp_expires']) || time() > $_SESSION['otp_expires']) {
            error_log("DEBUG: OTP expired");
            // Clear expired session data
            unset($_SESSION['email_otp'], $_SESSION['otp_expires'], $_SESSION['otp_email']);
            // Record failed verification attempt
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_verify', $ip, false, ['reason' => 'otp_expired']);
            }
            return false;
        }
        
        // Verify OTP
        if ($inputOTP === $_SESSION['email_otp']) {
            error_log("DEBUG: OTP verified successfully");
            
            // Clear OTP data after successful verification
            $stmt = $pdo->prepare("UPDATE mmtvtc_users SET otp_code = NULL, otp_expires_at = NULL WHERE email = ?");
            $stmt->execute([$email]);
            
            // Clear session data
            unset($_SESSION['email_otp'], $_SESSION['otp_expires'], $_SESSION['otp_email']);
            
            // Record successful verification
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_verify', $ip, true, ['otp_verified' => true]);
            }
            
            return true;
        } else {
            error_log("DEBUG: OTP verification failed. Expected: " . $_SESSION['email_otp'] . ", Got: $inputOTP");
            // Record failed verification attempt
            if ($abuseProtection) {
                $abuseProtection->recordAttempt($email, 'email', 'otp_verify', $ip, false, ['reason' => 'invalid_otp']);
            }
            return false;
        }
        
    } catch (Exception $e) {
        error_log("DEBUG: Verification error: " . $e->getMessage());
        return false;
    }
}
?>