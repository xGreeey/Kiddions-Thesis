<?php
session_start();
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/csrf.php';

$error = '';
$success = '';
$valid_token = false;
$email = '';
$token = '';

// Check if token and email are provided
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
    
    try {
        // Validate token
        $stmt = $pdo->prepare("SELECT id FROM mmtvtc_users WHERE email = ? AND reset_token = ? AND reset_token_expires_at > NOW()");
        $stmt->execute([$email, $token]);
        
        if ($stmt->rowCount() > 0) {
            $valid_token = true;
        } else {
            $error = "Invalid or expired create password link. Please request a new password creation.";
        }
    } catch (PDOException $e) {
        error_log("Database error during token validation: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
} else {
    $error = "Invalid create password link. Please request a new password creation.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!csrfValidateToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate password
        $has_letter = preg_match('/[a-zA-Z]/', $password);
        $has_number = preg_match('/\d/', $password);
        $has_symbol = preg_match('/[^a-zA-Z\d]/', $password);
        $is_long_enough = strlen($password) >= 10;
        
        if (!($has_letter && $has_number && $has_symbol && $is_long_enough)) {
            $error = "Password does not meet security requirements.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                // Hash the new password using Argon2ID
                require_once '../security/security_core.php';
                $hashed_password = hashPassword($password);
                
                // Update password, mark verified, and clear reset token
                $update_stmt = $pdo->prepare("UPDATE mmtvtc_users SET password = ?, is_verified = 1, verification_token = NULL, token_expiry = NULL, reset_token = NULL, reset_token_expires_at = NULL WHERE email = ? AND reset_token = ?");
                
                if ($update_stmt->execute([$hashed_password, $email, $token])) {
                    $success = "Your password has been successfully created.";
                    $_SESSION['password_reset_success'] = true;

                    // Ensure student record exists for default student users (is_role = 0)
                    try {
                        // Load user details and role
                        $uStmt = $pdo->prepare("SELECT id, student_number, first_name, last_name, middle_name, email, is_role FROM mmtvtc_users WHERE email = ? LIMIT 1");
                        $uStmt->execute([$email]);
                        if ($userRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                            if ((int)($userRow['is_role'] ?? 0) === 0) {
                                // Determine course from add_trainees by student_number or email
                                $course = null;
                                try {
                                    $cStmt = $pdo->prepare("SELECT course FROM add_trainees WHERE student_number = ? OR email = ? ORDER BY created_at DESC LIMIT 1");
                                    $cStmt->execute([$userRow['student_number'], $userRow['email']]);
                                    if ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) { $course = $cRow['course'] ?? null; }
                                } catch (Throwable $e) { /* ignore */ }

                                // Check if student already exists by user_id OR student_number OR email
                                $checkStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? OR student_number = ? OR email = ? LIMIT 1");
                                $checkStmt->execute([$userRow['id'], $userRow['student_number'], $userRow['email']]);
                                $studentExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

                                if (!$studentExists) {
                                    $insStmt = $pdo->prepare("INSERT INTO students (user_id, student_number, first_name, last_name, middle_name, email, course, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                                    $insStmt->execute([
                                        $userRow['id'],
                                        $userRow['student_number'],
                                        $userRow['first_name'] ?? '',
                                        $userRow['last_name'] ?? '',
                                        $userRow['middle_name'] ?? '',
                                        $userRow['email'],
                                        $course
                                    ]);
                                } elseif ($course !== null) {
                                    $updStmt = $pdo->prepare("UPDATE students SET course = ? WHERE id = ?");
                                    $updStmt->execute([$course, $studentExists['id']]);
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('Ensure student after create_pass failed: ' . $e->getMessage());
                    }

                    // Redirect to login after 3 seconds
                    header("refresh:3;url=EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu");
                } else {
                    $error = "Failed to create password. Please try again.";
                }
            } catch (PDOException $e) {
                error_log("Database error during password update: " . $e->getMessage());
                $error = "Failed to create password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Create Password - MMTVTC</title>
    <link rel="icon" href="../assets/mmtvtc.png" type="image/png">
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
        .mosaic { position: fixed; inset: 0; z-index: 0; opacity: .18; filter: saturate(85%) contrast(95%); display: grid; grid-template-columns: repeat(6, 1fr); grid-auto-rows: 18vh; gap: 6px; padding: 120px 6px 6px; pointer-events: none; }
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
        @keyframes float { 0% { transform: translateY(0); opacity: .6; } 50% { transform: translateY(-20px); opacity: .9; } 100% { transform: translateY(0); opacity: .6; }
        }

        .reset-container {
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

        .logo {
            display: block;
            margin: 0 auto 15px;
            width: 100px;
        }

        h2 {
            text-align: center;
            color: #ffcc00;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #ccc;
            font-size: 14px;
            margin-bottom: 20px;
        }

        input[type="password"] {
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

        input:focus {
            border-color: #ffcc00;
            background: rgba(255, 255, 255, 0.15);
            box-shadow:
                0 0 0 3px rgba(255, 204, 0, 0.2),
                0 0 20px rgba(255, 204, 0, 0.1);
        }

        input:disabled {
            background-color: #e0e0e0;
            cursor: not-allowed;
        }

        .submit-btn {
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
        }

        .submit-btn:hover {
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
        }

        .error {
            background-color: rgba(204, 0, 0, 0.2);
            color: #ff6b6b;
        }

        .success {
            background-color: rgba(0, 204, 102, 0.2);
            color: #00cc66;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #ffcc00;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
            padding: 8px 0;
        }

        .back-link a:hover {
            color: #fff;
            text-decoration: underline;
        }

        .password-strength-container {
            width: 100%;
            margin-top: -12px;
            margin-bottom: 15px;
        }

        .password-strength-meter {
            height: 5px;
            width: 100%;
            background: rgba(255,255,255,0.25);
            border-radius: 3px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength-meter-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0;
        }

        .password-requirements {
            font-size: 12px;
            color: #ddd;
            margin-top: 5px;
            text-align: left;
        }

        .requirement {
            margin-bottom: 2px;
        }

        .requirement.met {
            color: #00cc66;
        }

        .requirement.unmet {
            color: #ff6b6b;
        }

        .password-match-indicator {
            color: #ff6b6b;
            font-size: 12px;
            text-align: left;
            margin-top: -12px;
            margin-bottom: 12px;
            visibility: hidden;
            width: 100%;
        }

        .submit-btn.loading {
            pointer-events: none;
            background: #ffcc00;
        }
        
        .submit-btn.loading .btn-text {
            visibility: hidden;
            opacity: 0;
        }
        
        .submit-btn.loading .loader {
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
            visibility: visible;
            opacity: 1;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="mosaic" aria-hidden="true">
        <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
    </div>
    <div class="particles" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    <img src="../assets/manda1.png" class="background-logo" alt="Watermark">

    <div class="overlay">
        <div class="reset-container">
            <img src="../assets/mmtvtc.png" alt="MMTVTC Logo" class="logo">
            <h2>Create Password</h2>
            <p class="subtitle">Create a new password for your account</p>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success">
                    <?= $success ?>
                    <p class="redirect-message">You will be redirected to the login page in <span id="countdown" class="countdown">3</span> seconds.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($valid_token && empty($success)): ?>
                <form id="resetForm" method="POST" action="?token=<?= htmlspecialchars($token) ?>&email=<?= htmlspecialchars($email) ?>">
                    <?= csrfInputField(); ?>
                    <input type="password" id="password" name="password" placeholder="New Password" required>
                    
                    <div class="password-strength-container">
                        <div class="password-strength-meter">
                            <div id="strengthMeter" class="password-strength-meter-fill"></div>
                        </div>
                        <div class="password-requirements">
                            <div id="lengthReq" class="requirement unmet">• At least 10 characters</div>
                            <div id="letterReq" class="requirement unmet">• At least 1 letter</div>
                            <div id="numberReq" class="requirement unmet">• At least 1 number</div>
                            <div id="symbolReq" class="requirement unmet">• At least 1 special character</div>
                        </div>
                    </div>
                    
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required disabled>
                    <div id="passwordMatchIndicator" class="password-match-indicator">Passwords do not match</div>
                    
                    <button type="submit" id="submitButton" class="submit-btn">
                        <span class="btn-text">Create Password</span>
                        <span class="loader"></span>
                    </button>
                </form>
            <?php elseif (empty($success)): ?>
                <div class="back-link">
                    <a href="4PV2J8hXLqMsRg6wKjEZnYbTfFcDx9">Request New Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            let countdown = 3;
            const countdownElement = document.getElementById('countdown');
            const timer = setInterval(function() {
                countdown--;
                countdownElement.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = 'EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu';
                }
            }, 1000);
        });
        <?php endif; ?>

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthMeter = document.getElementById('strengthMeter');
            const confirmPassword = document.getElementById('confirm_password');
            const hasLetter = /[a-zA-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSymbol = /[^a-zA-Z\d]/.test(password);
            const isLongEnough = password.length >= 10;
            document.getElementById('lengthReq').className = isLongEnough ? 'requirement met' : 'requirement unmet';
            document.getElementById('letterReq').className = hasLetter ? 'requirement met' : 'requirement unmet';
            document.getElementById('numberReq').className = hasNumber ? 'requirement met' : 'requirement unmet';
            document.getElementById('symbolReq').className = hasSymbol ? 'requirement met' : 'requirement unmet';
            let strength = 0;
            if (password.length > 0) strength += 1;
            if (hasLetter) strength += 1;
            if (hasNumber) strength += 1;
            if (hasSymbol) strength += 1;
            if (isLongEnough) strength += 1;
            const percentage = (strength / 5) * 100;
            strengthMeter.style.width = percentage + '%';
            if (strength <= 2) {
                strengthMeter.style.backgroundColor = '#ff6b6b';
            } else if (strength <= 3) {
                strengthMeter.style.backgroundColor = '#ffcc00';
            } else {
                strengthMeter.style.backgroundColor = '#00cc66';
            }
            const allCriteriaMet = hasLetter && hasNumber && hasSymbol && isLongEnough;
            confirmPassword.disabled = !allCriteriaMet;
            if (!allCriteriaMet && confirmPassword.value) {
                confirmPassword.value = '';
                document.getElementById('passwordMatchIndicator').style.visibility = 'hidden';
            }
            if (confirmPassword.value) {
                checkPasswordMatch();
            }
            return allCriteriaMet;
        }
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const indicator = document.getElementById('passwordMatchIndicator');
            if (confirmPassword.length > 0) {
                if (password !== confirmPassword) {
                    indicator.style.visibility = 'visible';
                } else {
                    indicator.style.visibility = 'hidden';
                }
            } else {
                indicator.style.visibility = 'hidden';
            }
            return password.length > 0 && confirmPassword.length > 0 && password === confirmPassword;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const form = document.getElementById('resetForm');
            const submitButton = document.getElementById('submitButton');

            if (passwordInput) {
                passwordInput.addEventListener('input', checkPasswordStrength);
            }
            if (confirmInput) {
                confirmInput.addEventListener('input', checkPasswordMatch);
            }
            if (form) {
                form.addEventListener('submit', function(e) {
                    const criteriaOk = checkPasswordStrength();
                    const matchOk = checkPasswordMatch();
                    if (!criteriaOk || !matchOk) {
                        e.preventDefault();
                        return false;
                    }
                    submitButton.classList.add('loading');
                });
            }
        });
    </script>
</body>
</html>

