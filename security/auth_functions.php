<?php
require_once __DIR__ . '/db_connect.php';

/**
 * Check if user is authenticated
 * @return bool True if user is logged in and session is valid
 */
function isUserAuthenticated() {
    // Consider a user authenticated if they have an email in session.
    // Some roles (instructor/admin) may not have a numeric id/student_number set consistently.
    if (!isset($_SESSION['email']) || $_SESSION['email'] === '') {
        return false;
    }
    
    // Check if session has login time
    if (!isset($_SESSION['login_time'])) {
        return false;
    }
    
    // Removed login-time based timeout to prevent premature session expiration
    
    return true;
}

/**
 * Check if user has completed email verification
 * @return bool True if user is verified
 */
function isUserVerified() {
    return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1;
}

/**
 * Check if user is in pending verification state
 * @return bool True if user needs to verify email
 */
function isPendingVerification() {
    return isset($_SESSION['pending_verification']) && $_SESSION['pending_verification'] === true;
}

/**
 * Require authentication - redirect to login if not authenticated
 * @param string $redirectTo Optional redirect URL after login
 */
function requireAuth($redirectTo = null) {
    if (!isUserAuthenticated()) {
        // Log unauthorized access attempt
        logSecurityEvent('UNAUTHORIZED_ACCESS_ATTEMPT', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_exists' => isset($_SESSION['id']) ? 'yes' : 'no'
        ]);
        
        // Store intended destination
        if ($redirectTo) {
            $_SESSION['redirect_after_login'] = $redirectTo;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        
        // Clear any existing session data
        session_unset();
        session_destroy();
        
        // Redirect to login using clean URL
        header("Location: " . generateSecureUrl('home', ['auth_required' => '1']));
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Require verified user - redirect to verification if not verified
 */
function requireVerification() {
    requireAuth(); // First ensure user is authenticated
    
    if (isPendingVerification() || !isUserVerified()) {
        logSecurityEvent('UNVERIFIED_ACCESS_ATTEMPT', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $_SESSION['id'] ?? 'unknown',
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        header("Location: " . generateObfuscatedUrl('email_verification'));
        exit();
    }
}

/**
 * Check if user has specific role/permission
 * @param string $role Required role
 * @return bool True if user has the role
 */
function hasRole($role) {
    if (!isUserAuthenticated()) {
        return false;
    }

    // Normalize role from various session fields
    $sessionRole = $_SESSION['role'] ?? null;
    $numericRole = $_SESSION['user_role'] ?? ($_SESSION['is_role'] ?? null); // 0=student,1=instructor,2=admin

    if ($sessionRole === null && $numericRole !== null) {
        $map = [ 2 => 'admin', 1 => 'instructor', 0 => 'student' ];
        if (is_numeric($numericRole) && isset($map[(int)$numericRole])) {
            $sessionRole = $map[(int)$numericRole];
        }
    }

    if (!is_string($sessionRole)) { return false; }
    return strtolower($sessionRole) === strtolower($role);
}

/**
 * Require one of several roles
 * @param array $roles Allowed roles (e.g., ['admin','instructor'])
 */
function requireAnyRole(array $roles) {
    requireAuth();
    foreach ($roles as $r) {
        if (hasRole($r)) { return; }
    }
    logSecurityEvent('INSUFFICIENT_PRIVILEGES', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['id'] ?? 'unknown',
        'required_any' => $roles,
        'user_role' => $_SESSION['role'] ?? ($_SESSION['user_role'] ?? ($_SESSION['is_role'] ?? 'none')),
        'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    http_response_code(403);
    die('Access denied: Insufficient privileges');
}

/**
 * Require specific role - return 403 if user doesn't have role
 * @param string $role Required role
 */
function requireRole($role) {
    requireAuth();
    
    if (!hasRole($role)) {
        logSecurityEvent('INSUFFICIENT_PRIVILEGES', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $_SESSION['id'] ?? 'unknown',
            'required_role' => $role,
            'user_role' => $_SESSION['role'] ?? 'none',
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        die('Access denied: Insufficient privileges');
    }
}

/**
 * Check if user can access specific resource
 * @param int $resourceUserId The user ID who owns the resource
 * @return bool True if current user can access the resource
 */
function canAccessUserResource($resourceUserId) {
    if (!isUserAuthenticated()) {
        return false;
    }
    
    // User can access their own resources
    if ($_SESSION['id'] == $resourceUserId) {
        return true;
    }
    
    // Admin can access any resource
    if (hasRole('admin')) {
        return true;
    }
    
    return false;
}

/**
 * Require access to user resource
 * @param int $resourceUserId The user ID who owns the resource
 */
function requireUserResourceAccess($resourceUserId) {
    requireAuth();
    
    if (!canAccessUserResource($resourceUserId)) {
        logSecurityEvent('UNAUTHORIZED_RESOURCE_ACCESS', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $_SESSION['id'] ?? 'unknown',
            'attempted_resource_user' => $resourceUserId,
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        die('Access denied: Cannot access this resource');
    }
}

/**
 * Get current user information
 * @return array|null User information or null if not authenticated
 */
function getCurrentUser() {
    if (!isUserAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['id'],
        'student_number' => $_SESSION['student_number'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'] ?? 'student',
        'login_time' => $_SESSION['login_time'],
        'last_activity' => $_SESSION['last_activity'] ?? time()
    ];
}

/**
 * Get current user ID
 * @return int|null Current user ID or null if not authenticated
 */
function getCurrentUserId() {
    return isUserAuthenticated() ? $_SESSION['id'] : null;
}

/**
 * Get current user email
 * @return string|null Current user email or null if not authenticated
 */
function getCurrentUserEmail() {
    return isUserAuthenticated() ? $_SESSION['email'] : null;
}

/**
 * Refresh user data from database
 * @param PDO $pdo Database connection
 * @return bool True if refresh successful
 */
function refreshUserSession($pdo) {
    if (!isUserAuthenticated()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, student_number, email, is_verified, role FROM students WHERE id = ? AND active = 1");
        $stmt->execute([$_SESSION['id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User no longer exists or is inactive
            logSecurityEvent('USER_SESSION_INVALID', [
                'user_id' => $_SESSION['id'],
                'reason' => 'User not found or inactive'
            ]);
            
            destroySession();
            return false;
        }
        
        // Update session with fresh data
        $_SESSION['student_number'] = $user['student_number'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_verified'] = $user['is_verified'];
        $_SESSION['role'] = $user['role'] ?? 'student';
        
        return true;
        
    } catch (Exception $e) {
        logSecurityEvent('SESSION_REFRESH_ERROR', [
            'user_id' => $_SESSION['id'],
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Check if current session needs OTP verification
 * @return bool True if OTP verification is required
 */
function needsOTPVerification() {
    return isset($_SESSION['otp_required']) && $_SESSION['otp_required'] === true;
}

/**
 * Require OTP verification
 */
function requireOTPVerification() {
    requireAuth();
    
    if (needsOTPVerification()) {
        header("Location: otp");
        exit();
    }
}

/**
 * Set user as logged in
 * @param array $userData User data from database
 * @param bool $requireOTP Whether OTP verification is required
 */
function setUserLoggedIn($userData, $requireOTP = false) {
    // Regenerate session ID to prevent session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Set session data
    $_SESSION['id'] = $userData['id'];
    $_SESSION['student_number'] = $userData['student_number'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['is_verified'] = $userData['is_verified'] ?? 0;
    $_SESSION['role'] = $userData['role'] ?? 'student';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Set IP and User Agent for security tracking
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Set OTP requirement if needed
    if ($requireOTP) {
        $_SESSION['otp_required'] = true;
    }
    
    // Clear any pending verification flags if user is verified
    if ($userData['is_verified'] == 1) {
        unset($_SESSION['pending_verification']);
    }
    
    logSecurityEvent('USER_LOGIN_SUCCESS', [
        'user_id' => $userData['id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'otp_required' => $requireOTP
    ]);
}

/**
 * Complete OTP verification
 */
function completeOTPVerification() {
    if (isUserAuthenticated()) {
        unset($_SESSION['otp_required']);
        $_SESSION['otp_verified_at'] = time();
        
        logSecurityEvent('OTP_VERIFICATION_SUCCESS', [
            'user_id' => $_SESSION['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
}

/**
 * Log user out and destroy session
 */
function logoutUser() {
    if (isUserAuthenticated()) {
        logSecurityEvent('USER_LOGOUT', [
            'user_id' => $_SESSION['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'session_duration' => time() - ($_SESSION['login_time'] ?? time())
        ]);
    }
    
    destroySession();
}

/**
 * Check if user account is locked
 * @param PDO $pdo Database connection
 * @param string $studentNumber Student number to check
 * @return bool True if account is locked
 */
function isAccountLocked($pdo, $studentNumber) {
    try {
        $stmt = $pdo->prepare("SELECT locked_until FROM students WHERE student_number = ?");
        $stmt->execute([$studentNumber]);
        $result = $stmt->fetch();
        
        if ($result && $result['locked_until']) {
            return strtotime($result['locked_until']) > time();
        }
        
        return false;
    } catch (Exception $e) {
        logSecurityEvent('ACCOUNT_LOCK_CHECK_ERROR', [
            'student_number' => $studentNumber,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get time remaining for account lock
 * @param PDO $pdo Database connection
 * @param string $studentNumber Student number to check
 * @return int Seconds remaining for lock, 0 if not locked
 */
function getAccountLockTimeRemaining($pdo, $studentNumber) {
    try {
        $stmt = $pdo->prepare("SELECT locked_until FROM students WHERE student_number = ?");
        $stmt->execute([$studentNumber]);
        $result = $stmt->fetch();
        
        if ($result && $result['locked_until']) {
            $lockTime = strtotime($result['locked_until']);
            $remaining = $lockTime - time();
            return max(0, $remaining);
        }
        
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Check for suspicious login patterns
 * @param string $ip IP address
 * @param string $userAgent User agent
 * @return bool True if patterns seem suspicious
 */
function isSuspiciousLoginPattern($ip, $userAgent) {
    // Check for common bot user agents
    $suspiciousAgents = [
        'curl', 'wget', 'python-requests', 'PostmanRuntime',
        'bot', 'crawler', 'spider', 'scraper'
    ];
    
    foreach ($suspiciousAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) {
            return true;
        }
    }
    
    // Check for suspicious IP patterns (very basic)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        // Private or reserved IP ranges might be suspicious depending on your setup
        // Adjust this logic based on your network configuration
    }
    
    return false;
}

/**
 * Get redirect URL after login
 * @return string URL to redirect to after successful login
 */
function getPostLoginRedirect() {
    $redirect = $_SESSION['redirect_after_login'] ?? '';
    unset($_SESSION['redirect_after_login']);
    
    // Validate redirect URL to prevent open redirects
    if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL) === false) {
        // If it's a relative URL, ensure it starts with /
        if (!empty($redirect) && $redirect[0] !== '/') {
            $redirect = '';
        }
    }
    
    return $redirect ?: generateSecureUrl('dashboard');
}

/**
 * Security logging function (if not already defined)
 */
if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent($event, $details = []) {
        $logDir = 'logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logData = [
            'timestamp' => date('c'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'details' => $details
        ];
        
        $log = json_encode($logData) . "\n";
        file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Session destroy function (if not already defined)
 */
if (!function_exists('destroySession')) {
    function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                if (!function_exists('deleteSecureCookie')) {
                    require_once __DIR__ . '/cookies.php';
                }
                deleteSecureCookie(session_name());
            }
            
            session_destroy();
        }
    }
}
?>