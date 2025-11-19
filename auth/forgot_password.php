<?php
session_start();
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/csp.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('csrfValidateToken')) { require_once __DIR__ . '/../security/csrf.php'; }
    if (!csrfValidateToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
    $email = trim($_POST['email']);
    
    // Validate input   
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        try {
            // Check if student exists with matching email
            $stmt = $pdo->prepare("SELECT id, student_number, first_name, last_name, email FROM mmtvtc_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate password reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // Token expires in 10 minutes
                
                // Store token in database
                $update_stmt = $pdo->prepare("UPDATE mmtvtc_users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
                
                if ($update_stmt->execute([$token, $expires_at, $user['id']])) {
                    // Send password reset email
                    if (send_reset_email($user, $token)) {
                        $success = "A password reset link has been sent to your email address. Please check your inbox.";
                        
                        // Store email in session for resending
                        $_SESSION['reset_email'] = $email;
                    } else {
                        $error = "Failed to send password reset email. Please try again.";
                    }
                } else {
                    $error = "An error occurred. Please try again later.";
                }
            } else {
                $error = "No account found with the provided email address.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
    }
}

function send_reset_email($user, $token) {
    $mail = new PHPMailer(true);
    $success = false;
    
    try {
        $reset_url = "http://" . $_SERVER['HTTP_HOST'] . "/nsZLoj1b49kcshf6JhimM3Tvdn1rLK?token=" . $token . "&email=" . urlencode($user['email']);
        
        $mail->isSMTP();
        
        // Add the optimization configurations here
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->SMTPKeepAlive = true;
        $mail->Timeout = 30; // Increase timeout for SMTP operations 
        $mail->SMTPDebug = 0; // Disable debugging output

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
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    .email-container { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                    .email-header { text-align: center; margin-bottom: 20px; }
                    .email-title { color: #003366; }
                    .email-button { text-align: center; margin: 25px 0; }
                    .email-link { background-color: #ffcc00; color: #003366; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; }
                    .email-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <h2 class='email-title'>MMTVTC Password Reset</h2>
                    </div>
                    <p>Hello {$user['first_name']},</p>
                    <p>We received a request to reset your password for your MMTVTC student account.</p>
                    <p>Please click the button below to reset your password:</p>
                    <div class='email-button'>
                        <a href='$reset_url' class='email-link'>Reset Password</a>
                    </div>
                    <p>If the button above doesn't work, you can also click on the link below or copy and paste it into your browser:</p>
                    <p><a href='$reset_url'>$reset_url</a></p>
                    <p>This link will expire in 10 minutes.</p>
                    <p>If you didn't request a password reset, you can safely ignore this email.</p>
                    <div class='email-footer'>
                        <p>MMTVTC - Mandaluyong Manpower Technical and Vocational Training Center</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hello {$user['first_name']},

We received a request to reset your password for your MMTVTC student account.

Please visit the following link to reset your password: $reset_url

This link will expire in 10 minutes.

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Forgot Password - MMTVTC</title>
    <!-- Use system fonts for strict CSP -->
    
    <style nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            touch-action: manipulation;
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        body {
            font-family: 'Poppins', sans-serif;
            position: relative;
            min-height: 100vh;
            color: #fff;
            background:
                linear-gradient(120deg, rgba(8, 12, 22, .92), rgba(10, 15, 28, .92)),
                url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
        }

        /* Glassmorphic Header (same as login) */
        header.navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 28px;
            margin: 14px auto;
            max-width: 1200px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,.35);
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; letter-spacing: .2px; }
        .brand img { width: 34px; height: 34px; object-fit: cover; border-radius: 8px; }
        .brand span { color: #ffd633; }
        .brand-subtitle { font-size: 14px; line-height: 1; color: #cfd7e6; }
        nav a { color: #e8ecf3; text-decoration: none; margin-left: 22px; font-weight: 500; opacity: .9; transition: opacity .2s ease, transform .2s ease; }
        nav a:hover { opacity: 1; transform: translateY(-1px); }

        /* Brand scrollbar (subtle yellow) */
        html { scrollbar-width: thin; scrollbar-color: #ffcc00 rgba(255,255,255,0.08); }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.06); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #ffe680, #ffcc00); border-radius: 10px; border: 2px solid rgba(0,0,0,0.25); }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #fff0a6, #ffdf57); }

        .background-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70%;
            max-width: 1000px;
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }

        .overlay {
            position: absolute;
            z-index: 1;
            height: 100%;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(to right, rgba(0,0,0,0.45), rgba(0,0,0,0.65));
        }

        /* Ambient visuals to match login */
        .mosaic {
            position: fixed; inset: 0; z-index: 0; opacity: .18;
            filter: saturate(85%) contrast(95%);
            display: grid; grid-template-columns: repeat(6, 1fr); grid-auto-rows: 18vh; gap: 6px; padding: 120px 6px 6px;
            pointer-events: none;
        }
        .mosaic div { border-radius: 10px; background-position: center; background-size: cover; }
        .mosaic div:nth-child(1) { background-image: url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
        .mosaic div:nth-child(2) { background-image: url('https://images.unsplash.com/photo-1591453089816-9a46f7aee49f?auto=format&fit=crop&w=900&q=60'); }
        .mosaic div:nth-child(3) { background-image: url('https://images.unsplash.com/photo-1580281657527-47dfa27d0f5a?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
        .mosaic div:nth-child(4) { background-image: url('https://images.unsplash.com/photo-1507537297725-24a1c029d3ca?auto=format&fit=crop&w=900&q=60'); }
        .mosaic div:nth-child(5) { background-image: url('https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=900&q=60'); grid-column: span 3; }
        .mosaic div:nth-child(6) { background-image: url('https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
        .mosaic div:nth-child(7) { background-image: url('https://images.unsplash.com/photo-1541976076758-347942db1970?auto=format&fit=crop&w=900&q=60'); }
        .mosaic div:nth-child(8) { background-image: url('https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
        .mosaic div:nth-child(9) { background-image: url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=900&q=60'); }

        .particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .particles span { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.35); filter: blur(1px); animation: float 10s linear infinite; }
        .particles span:nth-child(2) { left: 10%; top: 30%; animation-duration: 12s; }
        .particles span:nth-child(3) { left: 75%; top: 20%; animation-duration: 11s; }
        .particles span:nth-child(4) { left: 60%; top: 70%; animation-duration: 13s; }
        .particles span:nth-child(5) { left: 25%; top: 65%; animation-duration: 9s; }
        @keyframes float { 0% { transform: translateY(0); opacity: .6; } 50% { transform: translateY(-20px); opacity: .9; } 100% { transform: translateY(0); opacity: .6; } }

        .forgot-container {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            padding: 40px 30px;
            width: 350px;
            max-width: 90%;
            position: relative;
            overflow: hidden;
        }

        .forgot-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.1) 0%,
                rgba(255, 255, 255, 0.05) 50%,
                rgba(255, 255, 255, 0.02) 100%
            );
            pointer-events: none;
            z-index: -1;
        }

        .logo {
            display: block;
            margin: 0 auto 15px;
            width: 100px;
            z-index: 10;
            position: relative;
        }

        h2 {
            text-align: center;
            color: #ffcc00;
            font-weight: 600;
            margin-bottom: 25px;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
            z-index: 10;
            position: relative;
        }

        .subtitle {
            text-align: center;
            color: #ccc;
            font-size: 14px;
            margin-bottom: 20px;
            z-index: 10;
            position: relative;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0 18px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            outline: none;
            font-size: 16px;
            color: #fff;
            transition: all 0.3s ease;
            z-index: 10;
            position: relative;
        }

        input[type="email"]::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        input:focus {
            border-color: #ffcc00;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 0 0 3px rgba(255, 204, 0, 0.2),
                0 0 20px rgba(255, 204, 0, 0.1);
        }

        .reset-btn {
            position: relative;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            color: #003366;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            margin-bottom: 15px;
            overflow: hidden;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            box-shadow: 
                0 4px 15px rgba(255, 204, 0, 0.2),
                0 0 0 1px rgba(255, 204, 0, 0.1);
            z-index: 10;
        }

        .reset-btn:hover {
            background: linear-gradient(135deg, #e6b800 0%, #cc9900 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 6px 20px rgba(255, 204, 0, 0.3),
                0 0 0 1px rgba(255, 204, 0, 0.2);
        }

        .error, .success {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid;
            z-index: 10;
            position: relative;
        }

        .error {
            background: rgba(204, 0, 0, 0.2);
            border-color: rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
            text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
        }

        .success {
            background: rgba(0, 204, 102, 0.2);
            border-color: rgba(0, 204, 102, 0.3);
            color: #00ff88;
            text-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
            z-index: 10;
            position: relative;
        }

        .back-link a {
            color: #ffcc00;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
            padding: 8px 0;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
        }

        .back-link a:hover {
            color: #fff;
            text-decoration: underline;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
        }

        .reset-btn.loading {
            pointer-events: none;
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
        }
        
        .reset-btn.loading .btn-text {
            visibility: hidden;
            opacity: 0;
        }
        
        .reset-btn.loading .loader {
            visibility: visible;
            opacity: 1;
        }
        
        .reset-btn .btn-text {
            transition: all 0.2s ease;
        }
        
        .reset-btn .loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 24px;
            height: 24px;
            border: 3px solid rgba(0, 51, 102, 0.3);
            border-radius: 50%;
            border-top-color: #003366;
            animation: spin 1s linear infinite;
            visibility: hidden;
            opacity: 0;
            transition: all 0.2s ease;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .forgot-container.loading input {
            pointer-events: none;
            opacity: 0.7;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
            20%, 40%, 60%, 80% { transform: translateX(10px); }
        }

        .shake {
            animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .forgot-container {
                width: 90%;
                max-width: 350px;
            }
        }

        @media (max-width: 400px) {
            .forgot-container {
                width: 90%;
                padding: 30px 20px;
            }
            
            .logo {
                width: 80px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            input[type="email"],
            .reset-btn {
                padding: 14px;
                font-size: 16px;
            }
        }
        
        @supports (-webkit-touch-callout: none) {
            input, button {
                border-radius: 8px !important;
            }
        }
    </style>
</head>
<body ontouchmove="event.preventDefault()">

    <!-- ambient visuals to match login -->
    <div class="mosaic" aria-hidden="true">
        <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
    </div>
    <div class="particles" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>

    <header class="navbar">
        <div class="brand">
            <img src="../images/manpower logo.jpg" alt="MMTVTC logo" />
            <div>
                <div class="brand-subtitle"></div>
                <span>MMTVTC</span>
            </div>
        </div>
        <nav>
            <a href="home?">Home</a>
        </nav>
    </header>

    <img src="../assets/manda1.png" class="background-logo" alt="Watermark">

    <div class="overlay">
        <div class="forgot-container">
            <img src="../assets/mmtvtc.png" alt="MMTVTC Logo" class="logo">
            <h2>Forgot Password</h2>
            <p class="subtitle">Enter your email address to reset your password</p>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>
            
            <form id="resetForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="email" id="email" name="email" placeholder="Email Address" required>
                <button type="submit" id="resetButton" class="reset-btn">
                    <span class="btn-text">Send Reset Link</span>
                    <span class="loader"></span>
                </button>
                <div class="back-link">
                    <a href="/EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        // Security: Prevent basic client-side attacks
        (function() {
            'use strict';
            
            // Prevent zooming on double tap in iOS
            document.addEventListener('touchmove', function(e) {
                if (e.scale !== 1) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Disable zooming
            document.addEventListener('gesturestart', function(e) {
                e.preventDefault();
            }, { passive: false });
            
            // Security: Prevent console access in production
            if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                console.log = function() {};
                console.warn = function() {};
                console.error = function() {};
            }
            
            // Security: Basic anti-debugging
            setInterval(function() {
                if (window.devtools && window.devtools.open) {
                    window.location.href = 'about:blank';
                }
            }, 1000);
            
            document.addEventListener('DOMContentLoaded', function() {
                const resetForm = document.getElementById('resetForm');
                const resetButton = document.getElementById('resetButton');
                const forgotContainer = document.querySelector('.forgot-container');
                const emailInput = document.getElementById('email');
                
                // Security: Input validation and sanitization
                function validateEmail(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                }
                
                // Security: Rate limiting on client side (additional protection)
                let attemptCount = 0;
                const maxAttempts = 5;
                let lastAttemptTime = 0;
                
                function canAttemptReset() {
                    const now = Date.now();
                    if (now - lastAttemptTime > 900000) { // 15 minutes
                        attemptCount = 0;
                    }
                    return attemptCount < maxAttempts;
                }
                
                // Security: Prevent form manipulation
                function validateForm() {
                    const email = emailInput.value.trim();
                    
                    if (!validateEmail(email)) {
                        showError('Invalid email format');
                        return false;
                    }
                    
                    return true;
                }
                
                function showError(message) {
                    // Remove existing error messages
                    const existingError = document.querySelector('.error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Create new error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = message;
                    
                    // Insert after subtitle
                    const subtitle = document.querySelector('.subtitle');
                    subtitle.parentNode.insertBefore(errorDiv, subtitle.nextSibling);
                    
                    forgotContainer.classList.remove('shake');
                    requestAnimationFrame(() => {
                        forgotContainer.classList.add('shake');
                        setTimeout(() => {
                            forgotContainer.classList.remove('shake');
                        }, 600);
                    });
                }
                
                // Security: Input sanitization
                emailInput.addEventListener('input', function() {
                    if (this.value.length > 255) {
                        this.value = this.value.substring(0, 255);
                    }
                });
                
                // Focus handling for iOS devices to prevent zooming
                const inputs = document.querySelectorAll('input');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        document.body.style.fontSize = '16px';
                    });
                });
                
                resetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Remove existing error messages
                    const existingError = document.querySelector('.error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Client-side rate limiting check
                    if (!canAttemptReset()) {
                        showError('Too many attempts. Please wait 15 minutes before trying again.');
                        return;
                    }
                    
                    // Validate form
                    if (!validateForm()) {
                        return;
                    }
                    
                    // Update attempt tracking
                    attemptCount++;
                    lastAttemptTime = Date.now();
                    
                    // Show loading state
                    resetButton.classList.add('loading');
                    resetButton.disabled = true;
                    forgotContainer.classList.add('loading');
                    
                    const formData = new FormData(resetForm);
                    
                    // Security: Add request timestamp to prevent replay attacks
                    formData.append('timestamp', Date.now());
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(data => {
                        // Check if response contains success message
                        if (data.includes('A password reset link has been sent')) {
                            // Reset attempt count on success
                            attemptCount = 0;
                            
                            resetButton.classList.remove('loading');
                            
                            // Show success message
                            const successDiv = document.createElement('div');
                            successDiv.className = 'success';
                            successDiv.textContent = 'A password reset link has been sent to your email address. Please check your inbox.';
                            
                            // Insert after subtitle
                            const subtitle = document.querySelector('.subtitle');
                            subtitle.parentNode.insertBefore(successDiv, subtitle.nextSibling);
                            
                            // Disable form
                            emailInput.disabled = true;
                            resetButton.disabled = true;
                        } else {
                            resetButton.classList.remove('loading');
                            resetButton.disabled = false;
                            forgotContainer.classList.remove('loading');
                            
                            // Extract error message from response
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(data, 'text/html');
                            const errorElement = doc.querySelector('.error');
                            const errorMessage = errorElement ? errorElement.textContent : 'An error occurred. Please try again.';
                            
                            showError(errorMessage);
                        }
                    })
                    .catch(error => {
                        resetButton.classList.remove('loading');
                        resetButton.disabled = false;
                        forgotContainer.classList.remove('loading');
                        
                        showError('Connection error. Please check your internet and try again.');
                        console.error('Reset error:', error);
                    });
                });
                
                // Security: Clear form on page unload
                window.addEventListener('beforeunload', function() {
                    resetForm.reset();
                });
                
                // Security: Prevent form resubmission on back button
                window.addEventListener('pageshow', function(event) {
                    if (event.persisted) {
                        window.location.reload();
                    }
                });
            });
        })();
    </script>
</body>
</html>