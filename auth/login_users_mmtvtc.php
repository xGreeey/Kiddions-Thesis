<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once '../security/abuse_protection.php';
require_once '../otpforusers/send_otp.php';

// Environment configuration
if (getenv('APP_ENV') !== 'development') {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Cloudflare Turnstile configuration
define('TURNSTILE_SITE_KEY', '0x4AAAAAABmurJoaXa1UxfmM');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAABmurA59GN__hvTiM_DfF71ofUs');

// Security logging function
function logSecurityEvent($event, $details) {
    $logDir = '../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('Y-m-d H:i:s') . " - " . $event . " - " . json_encode($details) . "\n";
    file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
}

// Cloudflare Turnstile verification function
function verifyTurnstile($token, $ip) {
    if (empty($token)) {
        return false;
    }
    
    $data = [
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $ip
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    
    if ($result === false) {
        error_log("Turnstile verification request failed");
        return false;
    }
    
    $response = json_decode($result, true);
    
    if ($response === null || !isset($response['success'])) {
        error_log("Turnstile verification response invalid: " . $result);
        return false;
    }
    
    return $response['success'] === true;
}

// Rate limiting functions for your EXACT table structure
function checkRateLimit($pdo, $ip) {
    try {
        // Clean old attempts (older than 15 minutes)
        $fifteenMinutesAgo = time() - (15 * 60);
        
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE last_attempt < ?");
        $stmt->execute([$fifteenMinutesAgo]);
        
        // Check current attempts by IP in the last 15 minutes
        $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip = ? AND last_attempt > ?");
        $stmt->execute([$ip, $fifteenMinutesAgo]);
        $result = $stmt->fetch();
        
        if ($result && $result['attempts'] >= 5) {
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Allow on error to prevent DoS
    }
}

// Record failed attempt for your exact table structure
function recordFailedAttempt($pdo, $ip, $studentNumber = null, $userAgent = null) {
    try {
        $currentTime = time();
        
        // Check if record exists for this IP
        $stmt = $pdo->prepare("SELECT id, attempts FROM login_attempts WHERE ip = ?");
        $stmt->execute([$ip]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $newAttempts = $existing['attempts'] + 1;
            $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = ?, student_number = ?, user_agent = ?, ip_address = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $currentTime, $studentNumber, $userAgent, $ip, $existing['id']]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, student_number, attempt_time, ip_address, user_agent, attempts, last_attempt) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, 1, ?)");
            $stmt->execute([$ip, $studentNumber, $currentTime, $ip, $userAgent, $currentTime]);
        }
        
        error_log("Failed attempt recorded for IP: $ip");
    } catch (Exception $e) {
        error_log("Failed to log attempt: " . $e->getMessage());
    }
}

// Clear failed attempts on successful login
function clearFailedAttempts($pdo, $ip) {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->execute([$ip]);
        error_log("Cleared failed attempts for IP: $ip");
    } catch (Exception $e) {
        error_log("Failed to clear attempts: " . $e->getMessage());
    }
}

// Test function to verify table structure
function testTableStructure($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE login_attempts");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'ip', 'student_number', 'attempt_time', 'ip_address', 'user_agent', 'attempts', 'last_attempt'];
        $missing = array_diff($requiredColumns, $columns);
        
        if (empty($missing)) {
            error_log("✅ All required columns exist in login_attempts table");
            return true;
        } else {
            error_log("❌ Missing columns in login_attempts table: " . implode(', ', $missing));
            return false;
        }
    } catch (Exception $e) {
        error_log("Table structure test failed: " . $e->getMessage());
        return false;
    }
}

// Initialize/repair table if needed
function initializeLoginAttemptsTable($pdo) {
    try {
        // First test if table structure is correct
        if (testTableStructure($pdo)) {
            return true;
        }
        
        // If table structure is incomplete, try to fix it
        $alterQueries = [
            "ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS attempts INT(11) DEFAULT 1",
            "ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS last_attempt INT(11) NULL"
        ];
        
        foreach ($alterQueries as $query) {
            try {
                $pdo->exec($query);
            } catch (Exception $e) {
                error_log("ALTER TABLE warning (may be normal): " . $e->getMessage());
            }
        }
        
        return testTableStructure($pdo);
    } catch (Exception $e) {
        error_log("Table initialization error: " . $e->getMessage());
        return false;
    }
}

// Initialize table structure
initializeLoginAttemptsTable($pdo);

// Input validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    return strlen($password) >= 8 && strlen($password) <= 128;
}

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// CSRF validation
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session validation
function validateSession() {
    if (!isset($_SESSION['email']) || !isset($_SESSION['student_number'])) {
        return false;
    }
    
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
        return false;
    }
    
    return true;
}

function getRoleBasedRedirect($role) {
    switch ($role) {
        case 0:
            return 'dashboard';
        case 1:
            return 'instructor';
        case 2:
            return 'admin';
        default:
            return 'dashboard';
    }
}

// Update last login information
function updateLastLogin($pdo, $userId, $ip) {
    try {
        $stmt = $pdo->prepare("UPDATE mmtvtc_users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
        $stmt->execute([$ip, $userId]);
        error_log("Updated last login for user ID: $userId");
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

// Main login processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    error_log("Login attempt started");
    
    if (isset($_POST['ajax_login'])) {
        error_log("AJAX login attempt started");
        error_log("POST data: " . print_r($_POST, true));
    }
    
    // CSRF validation
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        logSecurityEvent('CSRF_ATTACK', [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if (isset($_POST['ajax_login'])) {
            $response['message'] = 'Security validation failed.';
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $_SESSION['login_error'] = 'Security validation failed.';
            header("Location: home?");
        }
        exit();
    }
    
    // Cloudflare Turnstile verification
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    if (!verifyTurnstile($turnstileToken, $ip)) {
        logSecurityEvent('TURNSTILE_FAILED', [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'token_present' => !empty($turnstileToken)
        ]);
        
        $response['message'] = 'Please complete the security verification.';
        recordFailedAttempt($pdo, $ip, null, $userAgent);
        
        if (isset($_POST['ajax_login'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $_SESSION['login_error'] = $response['message'];
            header("Location: home?");
        }
        exit();
    }
    
    // Initialize abuse protection
    $abuseProtection = initializeAbuseProtection($pdo);
    
    // Database-based rate limiting (legacy)
    if (!checkRateLimit($pdo, $ip)) {
        logSecurityEvent('RATE_LIMIT_EXCEEDED', [
            'ip' => $ip,
            'user_agent' => $userAgent
        ]);
        
        $response['message'] = "Too many login attempts. Please wait 15 minutes.";
        
        if (isset($_POST['ajax_login'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $_SESSION['login_error'] = $response['message'];
            header("Location: home?");
        }
        exit();
    }
    
    // Input validation
    if (empty($_POST['email']) || empty($_POST['password'])) {
        $response['message'] = "Please fill out all fields.";
        recordFailedAttempt($pdo, $ip, null, $userAgent);
    } else {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password']; // Don't sanitize passwords
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Invalid email format.";
            recordFailedAttempt($pdo, $ip, $email, $userAgent);
            logSecurityEvent('INVALID_EMAIL_FORMAT', [
                'ip' => $ip,
                'email_attempt' => $email,
                'user_agent' => $userAgent
            ]);
        }
        // Validate password length
        else if (!validatePassword($password)) {
            $response['message'] = "Invalid password format.";
            recordFailedAttempt($pdo, $ip, $email, $userAgent);
            logSecurityEvent('INVALID_PASSWORD_FORMAT', [
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
        }
        // Process login
        else {
            try {
                // Check abuse protection before processing login
                if ($abuseProtection) {
                    $abuseCheck = checkAbuseProtection($email, 'email', 'login', $ip);
                    if (!$abuseCheck['allowed']) {
                        $response['message'] = $abuseCheck['message'];
                        if (isset($abuseCheck['retry_after'])) {
                            $response['retry_after'] = $abuseCheck['retry_after'];
                        }
                        
                        if (isset($_POST['ajax_login'])) {
                            header('Content-Type: application/json');
                            echo json_encode($response);
                        } else {
                            $_SESSION['login_error'] = $response['message'];
                            header("Location: home?");
                        }
                        exit();
                    }
                }
                
                // Modified query to include is_role field
                $stmt = $pdo->prepare("SELECT id, student_number, email, password, is_verified, failed_attempts, locked_until, is_role FROM mmtvtc_users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                // Check if account is locked
                if ($user && isset($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                    $response['message'] = "Account is temporarily locked. Please try again later.";
                    recordFailedAttempt($pdo, $ip, $email, $userAgent);
                    logSecurityEvent('LOCKED_ACCOUNT_ACCESS', [
                        'ip' => $ip,
                        'email' => $email,
                        'user_agent' => $userAgent
                    ]);
                }

                else if ($user && password_verify($password, $user['password'])) {
                    // Record successful login attempt
                    if ($abuseProtection) {
                        $abuseProtection->recordAttempt($email, 'email', 'login', $ip, true, ['login_successful' => true]);
                        $abuseProtection->clearAbuseRecords($email, 'email');
                    }

                    $stmt = $pdo->prepare("UPDATE mmtvtc_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    clearFailedAttempts($pdo, $ip);
                    
                    // Update last login information
                    updateLastLogin($pdo, $user['id'], $ip);
                    
                    session_regenerate_id(true);

                    if (isset($user['is_verified']) && $user['is_verified'] == 0) {
                        $_SESSION['pending_verification'] = true;
                        $_SESSION['email'] = $user['email'];
                        
                        logSecurityEvent('UNVERIFIED_LOGIN_ATTEMPT', [
                            'ip' => $ip,
                            'email' => $email,
                            'user_agent' => $userAgent,
                            'role' => $user['is_role']
                        ]);
                        
                        $response['success'] = true;
                        $response['redirect'] = 'vdbZscYYEJbqotvNnWlyA8I1gwfpcH';
                    } else {
                        // Send OTP
                        if (send_otp($user['email'], $pdo)) {
                            $_SESSION['student_number'] = $user['student_number'];
                            $_SESSION['id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['is_role'] = $user['is_role']; // Store user role in session
                            $_SESSION['user_role'] = $user['is_role']; // Keep for backward compatibility
                            $_SESSION['login_time'] = time();
                            
                            logSecurityEvent('SUCCESSFUL_LOGIN', [
                                'ip' => $ip,
                                'email' => $email,
                                'user_agent' => $userAgent,
                                'role' => $user['is_role']
                            ]);
                            
                            error_log("Login successful for: " . $user['email'] . " with role: " . $user['is_role']);
                            
                            $response['success'] = true;
                            // Use role-based redirect - this will go to OTP verification first
                            $response['redirect'] = 'otp';
                        } else {
                            $response['message'] = "Error sending verification code. Please try again.";
                            recordFailedAttempt($pdo, $ip, $email, $userAgent);
                            logSecurityEvent('OTP_SEND_FAILED', [
                                'ip' => $ip,
                                'email' => $email,
                                'user_agent' => $userAgent,
                                'role' => $user['is_role']
                            ]);
                        }
                    }
                } else {
                    // Record failed login attempt
                    if ($abuseProtection) {
                        $abuseProtection->recordAttempt($email, 'email', 'login', $ip, false, ['reason' => 'invalid_credentials']);
                    }
                    
                    // Handle failed login
                    recordFailedAttempt($pdo, $ip, $student_number, $userAgent);
                    
                    // Update user failed attempts if user exists
                    if ($user) {
                        $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
                        $lockUntil = null;
                        
                        // Lock account after 5 failed attempts for 30 minutes
                        if ($failedAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
                        }
                        
                        $stmt = $pdo->prepare("UPDATE mmtvtc_users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failedAttempts, $lockUntil, $user['id']]);
                    }
                    
                    logSecurityEvent('FAILED_LOGIN', [
                        'ip' => $ip,
                        'email' => $email,
                        'user_agent' => $userAgent,
                        'user_exists' => $user ? true : false
                    ]);
                    
                    $response['message'] = "Invalid email or password.";
                }
            } catch (Exception $e) {
                logSecurityEvent('LOGIN_ERROR', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                    'user_agent' => $userAgent
                ]);
                $response['message'] = "An error occurred. Please try again later.";
                recordFailedAttempt($pdo, $ip, $email, $userAgent);
            }
        }
    }
    
    // Return response
    if (isset($_POST['ajax_login'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        if ($response['success'] && isset($response['redirect'])) {
            header("Location: " . $response['redirect']);
        } else {
            $_SESSION['login_error'] = $response['message'];
            header("Location: home?");
        }
        exit();
    }
}

// Fetch announcements from database
$announcements = [];
try {
    // Fetch active announcements ordered by creation date (newest first)
    $stmt = $pdo->prepare("
        SELECT title, content, date_created, type 
        FROM announcements 
        WHERE is_active = 1 
        ORDER BY date_created DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $dbAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert database announcements to the format expected by the frontend
    foreach ($dbAnnouncements as $announcement) {
        $announcements[] = [
            'title' => $announcement['title'],
            'content' => $announcement['content'],
            'date' => date('Y-m-d', strtotime($announcement['date_created'])),
            'type' => strtolower($announcement['type']) // Convert to lowercase for CSS classes
        ];
    }
    
    // If no announcements found, show default message
    if (empty($announcements)) {
        $announcements[] = [
            'title' => 'Welcome to MMTVTC',
            'content' => 'Check back here for important announcements and updates.',
            'date' => date('Y-m-d'),
            'type' => 'info'
        ];
    }
    
} catch (Exception $e) {
    logSecurityEvent('ANNOUNCEMENT_FETCH_ERROR', ['error' => $e->getMessage()]);
    // Fallback announcements if database fails
    $announcements = [
        [
            'title' => 'System Notice',
            'content' => 'Please check back later for announcements.',
            'date' => date('Y-m-d'),
            'type' => 'info'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>MMTVTC Login</title>
    <!-- Use system fonts for strict CSP -->
    
    <!-- Cloudflare Turnstile Script -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" referrerpolicy="no-referrer" crossorigin="anonymous"></script>
    
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
            background:
                linear-gradient(120deg, rgba(8, 12, 22, .92), rgba(10, 15, 28, .92)),
                url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
        }

        /* Glassmorphic Header (same as index) */
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

        /* Ambient visuals to match index */
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

        .main-container {
            display: flex;
            gap: 0px;
            align-items: center;
            max-width: 90%;
            width: 100%;
            justify-content: center;
            position: relative;
        }

        .divider {
            width: 2px;
            height: 500px;
            background: linear-gradient(
                180deg,
                transparent 0%,
                rgba(255, 204, 0, 0.3) 20%,
                rgba(255, 204, 0, 0.6) 50%,
                rgba(255, 204, 0, 0.3) 80%,
                transparent 100%
            );
            position: relative;
            z-index: 2;
            box-shadow: 0 0 20px rgba(255, 204, 0, 0.2);
        }

        .announcement-container {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-right: none;
            border-radius: 20px 0 0 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            padding: 40px 30px;
            width: 320px;
            max-width: 90%;
            position: relative;
            overflow: hidden;
            height: 600px;
            min-height: 600px;
        }

        .announcement-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            gap: 10px;
        }

        .announcement-header h2 {
            color: #ffcc00;
            font-weight: 600;
            margin: 0;
            font-size: 18px;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
        }

        .announcements-list {
            flex: 1; 
            overflow-y: auto;
            padding-right: 10px;
            max-height: calc(600px - 120px);
        }

        .announcements-list::-webkit-scrollbar {
            width: 6px;
        }

        .announcements-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .announcements-list::-webkit-scrollbar-thumb {
            background: rgba(255, 204, 0, 0.3);
            border-radius: 3px;
        }

        .announcements-list::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 204, 0, 0.5);
        }

        .announcement-item {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
        }

        .announcement-item:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .announcement-item.important {
            border-left: 4px solid #ff6b6b;
        }

        .announcement-item.info {
            border-left: 4px solid #4ecdc4;
        }

        .announcement-item.success {
            border-left: 4px solid #95e1d3;
        }

        .announcement-title {
            color: #ffcc00;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
        }

        .announcement-content {
            color: #fff;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .announcement-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 11px;
            text-align: right;
        }

        .no-announcements {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-left: none;
            border-radius: 0 20px 20px 0;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            padding: 40px 30px;
            width: 320px;
            max-width: 90%;
            position: relative;
            overflow: hidden;
            height: 600px; 
            min-height: 600px; 
            display: flex;
            flex-direction: column;
            justify-content: center; 
        }

        .login-container::before {
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

        input[type="email"],
        input[type="password"],
        input[type="text"], /* for visible password */
        input[type="hidden"] {
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

        /* Password visibility toggle */
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-60%);
            background: transparent;
            border: 0;
            color: #ffcc00;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            z-index: 11;
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
        }
        /* Animated slash when hiding password */
        .password-toggle .eye-slash {
			stroke-dasharray: 1;
			stroke-dashoffset: 1;
            animation: eyeSlashDraw 200ms ease-out forwards;
        }
		/* Reverse animation when showing password */
		.password-toggle .eye-slash-out {
			stroke-dasharray: 1;
			stroke-dashoffset: 0;
			animation: eyeSlashErase 200ms ease-in forwards;
		}
        @keyframes eyeSlashDraw {
            to { stroke-dashoffset: 0; }
        }
		@keyframes eyeSlashErase {
			to { stroke-dashoffset: 1; }
		}
        #password {
            padding-right: 42px; /* room for right toggle button */
        }

        input[type="hidden"] {
            display: none;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder,
        input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        input:focus {
            border-color: #ffcc00;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 0 0 3px rgba(255, 204, 0, 0.2),
                0 0 20px rgba(255, 204, 0, 0.1);
        }

        /* Turnstile container styling */
        .turnstile-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            min-height: 65px;
            align-items: center;
            z-index: 10;
            position: relative;
        }

        .cf-turnstile {
            transform: scale(0.9);
            transform-origin: center;
        }

        .login-btn {
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

        .login-btn:disabled {
            background: linear-gradient(135deg, #666 0%, #555 100%);
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .login-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #e6b800 0%, #cc9900 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 6px 20px rgba(255, 204, 0, 0.3),
                0 0 0 1px rgba(255, 204, 0, 0.2);
        }
        
        .login-btn.loading {
            pointer-events: none;
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
        }
        
        .login-btn.loading .btn-text {
            visibility: hidden;
            opacity: 0;
        }
        
        .login-btn.loading .loader {
            visibility: visible;
            opacity: 1;
        }
        
        .login-btn .btn-text {
            transition: all 0.2s ease;
        }
        
        .login-btn .loader {
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

        .login-btn.success {
            background: linear-gradient(135deg, #00cc66 0%, #009944 100%);
            color: white;
            animation: successPulse 0.5s ease-out;
        }
        
        .login-btn.success .btn-text {
            visibility: hidden;
            opacity: 0;
        }
        
        .login-btn.success::after {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: white;
        }

        @keyframes successPulse {
            0% { transform: scale(0.95); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .error {
            color: #ff6b6b;
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
            text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
            z-index: 10;
            position: relative;
        }

        .error.visible {
            opacity: 1;
        }

        
        .links-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 5px;
            gap: 10px;
            z-index: 10;
            position: relative;
        }
        
        .auth-link {
            color: #ffcc00;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
            padding: 8px 0;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
        }
        
        .auth-link:hover {
            color: #fff;
            text-decoration: underline;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
            20%, 40%, 60%, 80% { transform: translateX(10px); }
        }

        .shake {
            animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both;
        }

        .login-container.loading input {
            pointer-events: none;
            opacity: 0.7;
        }

        .success-message {
            background: linear-gradient(135deg, rgba(0, 204, 102, 0.2) 0%, rgba(0, 153, 68, 0.2) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 204, 102, 0.3);
            color: #00ff88;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            animation: fadeInDown 0.5s;
            text-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
            z-index: 10;
            position: relative;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                gap: 0px;
                max-width: 95%;
            }
            
            .divider {
                width: 300px;
                height: 2px;
                background: linear-gradient(
                    90deg,
                    transparent 0%,
                    rgba(255, 204, 0, 0.3) 20%,
                    rgba(255, 204, 0, 0.6) 50%,
                    rgba(255, 204, 0, 0.3) 80%,
                    transparent 100%
                );
            }
            
            .announcement-container,
            .login-container {
                width: 100%;
                max-width: 350px;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .announcement-container {
                order: 2;
                max-height: 300px;
                border-radius: 0 0 20px 20px;
                border-top: none;
                height: 400px;
                min-height: 400px;
            }
            
            .login-container {
                order: 1;
                border-radius: 20px 20px 0 0;
                border-bottom: none;
                height: 550px;
                min-height: 550px;
            }

            .cf-turnstile {
                transform: scale(0.8);
            }
        }

        @media (max-width: 400px) {
            .announcement-container,
            .login-container {
                width: 90%;
                padding: 30px 20px;
            }
            
            .logo {
                width: 80px;
            }
            
            h2 {
                font-size: 18px;
            }
            
            .announcement-header h2 {
                font-size: 16px;
            }
            
            input[type="email"],
            input[type="password"],
            input[type="text"], /* for visible password */
            .login-btn {
                padding: 14px;
                font-size: 16px;
            }

            .cf-turnstile {
                transform: scale(0.7);
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

    <!-- ambient visuals to match index -->
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
                <div style="font-size:14px; line-height:1; color:#cfd7e6;"></div>
                <span>MMTVTC</span>
            </div>
        </div>
        <nav>
            <a href="home?">Home</a>
        </nav>
    </header>

    <div class="overlay">
        <div class="main-container">
            <!-- Announcements Container -->
            <div class="announcement-container">
                <div class="announcement-header">
                    <h2>Announcements</h2>
                </div>
                
                <div class="announcements-list">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-item <?php echo htmlspecialchars($announcement['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="announcement-title"><?php echo htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="announcement-content"><?php echo htmlspecialchars($announcement['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="announcement-date"><?php echo htmlspecialchars(date('M d, Y', strtotime($announcement['date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-announcements">
                            No announcements at this time.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Divider -->
            <div class="divider"></div>

            <!-- Login Container -->
            <div id="loginContainer" class="login-container">
                <img src="../assets/mmtvtc.png" alt="MMTVTC Logo" class="logo">
                <h2>MMTVTC Login</h2>
                
                <?php if (isset($_SESSION['verified_success']) && $_SESSION['verified_success']): ?>
                    <div class="success-message">
                        Your email has been successfully verified!
                    </div>
                    <?php unset($_SESSION['verified_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['password_reset_success']) && $_SESSION['password_reset_success']): ?>
                    <div class="success-message">
                        Your password has been successfully reset!
                    </div>
                    <?php unset($_SESSION['password_reset_success']); ?>
                <?php endif; ?>
            
                <div id="errorMessage" class="error <?php echo isset($_SESSION['login_error']) ? 'visible' : ''; ?>">
                    <?php 
                    if (isset($_SESSION['login_error'])) {
                        echo htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['login_error']);
                    }
                    ?>
                </div>
                
                <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="email" id="email" name="email" placeholder="Email Address" required maxlength="255">
                    <div class="password-field">
                        <button type="button" id="togglePassword" class="password-toggle" aria-label="Show password" aria-pressed="false" aria-hidden="true" hidden>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <input type="password" id="password" name="password" placeholder="Password" required maxlength="128" minlength="8">
                    </div>
                    
                    <!-- Cloudflare Turnstile Widget -->
                    <div class="turnstile-container">
                        <div class="cf-turnstile" 
                             data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>" 
                             data-callback="onTurnstileSuccess"
                             data-error-callback="onTurnstileError"
                             data-expired-callback="onTurnstileExpired"
                             data-theme="dark">
                        </div>
                    </div>
                    
                    <button type="submit" id="loginButton" class="login-btn" disabled>
                        <span class="btn-text">Login</span>
                        <span class="loader"></span>
                    </button>
                    <div class="links-container">
                        <a href="/aKVeZ02vR7CTx28Jylr5FVaRxVFHzg" class="auth-link">Forgot Password</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        // Global variables for Turnstile
        let turnstileVerified = false;
        let turnstileWidget = null;

        // Turnstile callback functions
        function onTurnstileSuccess(token) {
            turnstileVerified = true;
            const loginButton = document.getElementById('loginButton');
            loginButton.disabled = false;
            loginButton.style.opacity = '1';
            console.log('Turnstile verification successful');
        }

        function onTurnstileError(error) {
            turnstileVerified = false;
            const loginButton = document.getElementById('loginButton');
            loginButton.disabled = true;
            loginButton.style.opacity = '0.6';
            console.error('Turnstile error:', error);
            
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = 'Security verification failed. Please try again.';
            errorMessage.classList.add('visible');
        }

        function onTurnstileExpired() {
            turnstileVerified = false;
            const loginButton = document.getElementById('loginButton');
            loginButton.disabled = true;
            loginButton.style.opacity = '0.6';
            console.log('Turnstile token expired');
            
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = 'Security verification expired. Please try again.';
            errorMessage.classList.add('visible');
        }

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
                const loginForm = document.getElementById('loginForm');
                const loginContainer = document.getElementById('loginContainer');
                const mainContainer = document.querySelector('.main-container');
                const errorMessage = document.getElementById('errorMessage');
                const loginButton = document.getElementById('loginButton');
                const emailInput = document.getElementById('email');
                const passwordInput = document.getElementById('password');

                // Initialize login button as disabled
                loginButton.disabled = true;
                loginButton.style.opacity = '0.6';

                // Security: Input validation and sanitization
                function validateEmail(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                }
                
                function validatePassword(password) {
                    return password.length >= 8 && password.length <= 128;
                }
                
                // Security: Rate limiting on client side (additional protection)
                let attemptCount = 0;
                const maxAttempts = 5;
                let lastAttemptTime = 0;
                
                function canAttemptLogin() {
                    const now = Date.now();
                    if (now - lastAttemptTime > 900000) { // 15 minutes
                        attemptCount = 0;
                    }
                    return attemptCount < maxAttempts;
                }
                
                // Security: Prevent form manipulation
                function validateForm() {
                    const email = emailInput.value.trim();
                    const password = passwordInput.value;
                    
                    if (!validateEmail(email)) {
                        showError('Invalid email format');
                        return false;
                    }
                    
                    if (!validatePassword(password)) {
                        showError('Password must be 8-128 characters long');
                        return false;
                    }

                    if (!turnstileVerified) {
                        showError('Please complete the security verification');
                        return false;
                    }
                    
                    return true;
                }
                
                function showError(message) {
                    errorMessage.textContent = message;
                    errorMessage.classList.add('visible');
                    
                    mainContainer.classList.remove('shake');
                    requestAnimationFrame(() => {
                        mainContainer.classList.add('shake');
                        setTimeout(() => {
                            mainContainer.classList.remove('shake');
                        }, 600);
                    });
                }
                
                // Security: Input sanitization
                emailInput.addEventListener('input', function() {
                    if (this.value.length > 255) {
                        this.value = this.value.substring(0, 255);
                    }
                });
                
                // Security: Limit password length
                passwordInput.addEventListener('input', function() {
                    if (this.value.length > 128) {
                        this.value = this.value.substring(0, 128);
                    }
                });

                // Focus handling for iOS devices to prevent zooming
                const inputs = document.querySelectorAll('input');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        document.body.style.fontSize = '16px';
                    });
                });

                // Password visibility toggle (UI only)
                const togglePasswordBtn = document.getElementById('togglePassword');
                if (togglePasswordBtn) {
                    // Show the eye only when there is text in the field
                    function syncEyeVisibility() {
                        const hasValue = passwordInput.value.length > 0;
                        togglePasswordBtn.hidden = !hasValue;
                        togglePasswordBtn.setAttribute('aria-hidden', hasValue ? 'false' : 'true');
                        if (!hasValue) {
                            // ensure we reset to hidden state when field is cleared
                            if (passwordInput.getAttribute('type') !== 'password') {
                                passwordInput.setAttribute('type', 'password');
                                togglePasswordBtn.setAttribute('aria-pressed', 'false');
                                togglePasswordBtn.setAttribute('aria-label', 'Show password');
                                togglePasswordBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                            }
                        }
                    }
                    // initialize and attach listeners
                    syncEyeVisibility();
                    passwordInput.addEventListener('input', syncEyeVisibility);
                    passwordInput.addEventListener('change', syncEyeVisibility);
                    togglePasswordBtn.addEventListener('click', function () {
                        const isPassword = passwordInput.getAttribute('type') === 'password';
                        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                        this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                        // Toggle icon: swap pupil to a slash icon when hidden
						this.innerHTML = isPassword
							? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.77 21.77 0 0 1-4.87 6.82"/><line class="eye-slash" x1="21" y1="3" x2="3" y2="21" pathLength="1"/></svg>'
							: (function(){
								// when switching back, animate the existing slash out before swapping
								const btn = togglePasswordBtn;
								const svg = btn.querySelector('svg');
								if (svg) {
									const line = svg.querySelector('.eye-slash');
									if (line) {
										line.classList.remove('eye-slash');
										line.classList.add('eye-slash-out');
										// defer the icon swap until the erase animation completes
										setTimeout(() => {
											btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
										}, 200);
										// return current markup temporarily so nothing flashes
										return btn.innerHTML;
									}
								}
								return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
							})()
						;
                    });
                }
                
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    errorMessage.textContent = '';
                    errorMessage.classList.remove('visible');
                    
                    // Client-side rate limiting check
                    if (!canAttemptLogin()) {
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
                    loginButton.classList.add('loading');
                    loginButton.disabled = true;
                    loginContainer.classList.add('loading');
                    
                    const formData = new FormData(loginForm);
                    formData.append('ajax_login', 'true');
                    
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
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Reset attempt count on success
                            attemptCount = 0;
                            
                            loginButton.classList.remove('loading');
                            loginButton.classList.add('success');
                            
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 500);
                        } else {
                            loginButton.classList.remove('loading');
                            loginButton.disabled = !turnstileVerified;
                            loginContainer.classList.remove('loading');
                            
                            showError(data.message || 'Login failed. Please try again.');
                            
                            // Reset Turnstile on failed login
                            if (typeof turnstile !== 'undefined') {
                                turnstile.reset();
                                turnstileVerified = false;
                                loginButton.disabled = true;
                                loginButton.style.opacity = '0.6';
                            }
                        }
                    })
                    .catch(error => {
                        loginButton.classList.remove('loading');
                        loginButton.disabled = !turnstileVerified;
                        loginContainer.classList.remove('loading');
                        
                        showError('Connection error. Please check your internet and try again.');
                        console.error('Login error:', error);
                        
                        // Reset Turnstile on error
                        if (typeof turnstile !== 'undefined') {
                            turnstile.reset();
                            turnstileVerified = false;
                            loginButton.disabled = true;
                            loginButton.style.opacity = '0.6';
                        }
                    });
                });
                
                // Security: Clear form on page unload
                window.addEventListener('beforeunload', function() {
                    loginForm.reset();
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