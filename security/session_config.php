<?php
// Include URL router for clean URL handling
require_once __DIR__ . '/url_router.php';
// CSP will be applied after session starts
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/cors.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle logout and redirect to login_student.php
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . generateObfuscatedUrl('login'));
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    
    // Environment Detection
    $isProduction = (getenv('APP_ENV') === 'production') || 
                   (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']));
    
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               ($_SERVER['SERVER_PORT'] ?? 80) == 443 ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    // Security Configuration
    $sessionConfig = [
        'development' => [
            'secure' => false,              // Allow HTTP in development
            'samesite' => 'Lax',           // Slightly relaxed for development
            'lifetime' => 7200,            // 2 hours for development
            'gc_maxlifetime' => 7200,
            'regenerate_frequency' => 300, // 5 minutes
        ],
        'production' => [
            'secure' => true,              // HTTPS only in production
            'samesite' => 'Strict',        // Strict CSRF protection
            'lifetime' => 7200,            // 2 hours for production
            'gc_maxlifetime' => 7200,
            'regenerate_frequency' => 300, // 5 minutes
        ]
    ];
    
    $config = $isProduction ? $sessionConfig['production'] : $sessionConfig['development'];
    
    // Canonical host normalization (avoid www/apex split) and HTTPS enforcement for GET navigation only
    if ($isProduction) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isGet = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET');

        // Prefer apex domain (strip leading www.)
        if ($isGet && !$isAjax && strpos($host, 'www.') === 0) {
            $redirectURL = 'https://' . substr($host, 4) . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }

        // Force HTTPS for normal GET page loads
        if (!$isHTTPS && $isGet && !$isAjax) {
            $redirectURL = 'https://' . $host . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }
    }

    
    
    // Core Session Security Settings
    ini_set('session.cookie_httponly', 1);      // Prevent XSS access to session cookie
    ini_set('session.use_only_cookies', 1);     // Only use cookies, no URL sessions
    ini_set('session.use_strict_mode', 1);      // Prevent session fixation attacks
    // Only set Secure cookies when the current request is actually over HTTPS
    $cookieSecure = $isHTTPS ? 1 : 0;
    ini_set('session.cookie_secure', $cookieSecure);
    ini_set('session.gc_maxlifetime', $config['gc_maxlifetime']);
    ini_set('session.cookie_lifetime', $config['lifetime']);
    
    // Additional Security Settings
    ini_set('session.use_trans_sid', 0);        // Disable transparent session ID
    ini_set('session.entropy_length', 32);      // High entropy for session IDs
    ini_set('session.hash_function', 'sha256'); // Strong hash function
    ini_set('session.hash_bits_per_character', 6); // More characters in session ID
    
    // Session Cookie Name (with environment suffix for clarity)
    $sessionName = $isProduction ? 'MMTVTC_PROD' : 'MMTVTC_DEV';
    session_name($sessionName);
    
    // Enhanced Cookie Parameters
    // Determine a stable cookie domain to share between apex and subdomains
    $cookieDomain = '';
    $h = $_SERVER['HTTP_HOST'] ?? '';
    // Strip port
    $h = preg_replace('/:\\d+$/', '', $h);
    if (strpos($h, 'www.') === 0) { $h = substr($h, 4); }
    // Only set domain if host appears to be a domain name (not an IP)
    if (preg_match('/^[a-z0-9.-]+$/i', $h) && !filter_var($h, FILTER_VALIDATE_IP)) {
        $cookieDomain = '.' . $h; // leading dot to cover subdomains
    }

    session_set_cookie_params([
        'lifetime' => $config['lifetime'],
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => (bool)$cookieSecure,
        'httponly' => true,
        'samesite' => $config['samesite']
    ]);
    
    // Debug cookie configuration
    error_log("SESSION CONFIG - Cookie Domain: " . $cookieDomain . ", Secure: " . ($cookieSecure ? 'YES' : 'NO') . ", SameSite: " . $config['samesite']);
    
    // Start Session
    session_start();

    // Minimal security headers for pages that only include session_config
    if (!headers_sent()) {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }
    
    // Session Security Validation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    }
    
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    
    // Session Timeout Check
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > $config['gc_maxlifetime'])) {
        
        // Log session timeout
        error_log("Session timeout for session: " . session_id());
        
        // Destroy session
        session_unset();
        session_destroy();
        session_start(); // Start fresh session
        
        // Set timeout flag for application to handle
        $_SESSION['session_timeout'] = true;
        
        // Regenerate CSRF token after session restart
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Session Regeneration (prevent session fixation)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }
    
    if (time() - $_SESSION['last_regeneration'] > $config['regenerate_frequency']) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        
        // Keep CSRF token stable during routine session ID regeneration to avoid
        // invalidating tokens embedded in already-rendered forms. CSRF token will
        // still be regenerated on full session resets (timeout, fingerprint
        // rotation) and at login/logout.
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();

    // Lightweight browser fingerprint to bind the session to this browser instance
    // Do NOT include IP to avoid invalidation on network changes
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        // Possible session stealing or mismatched cookie → reset session
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['fingerprint_rotated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // IP Address observation (relaxed): only log changes; do not kill session
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!isset($_SESSION['ip_address'])) {
        $_SESSION['ip_address'] = $currentIp;
    } else if ($_SESSION['ip_address'] !== $currentIp) {
        error_log("IP address changed for session: " . session_id() .
                 " Old: " . $_SESSION['ip_address'] .
                 " New: " . $currentIp);
        // Update stored IP without destroying session to avoid false logouts on dynamic IPs
        $_SESSION['ip_address'] = $currentIp;
        $_SESSION['ip_changed'] = true;
    }
    
    // User Agent Validation (basic fingerprinting)
    if (isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            // User agent changed - log for monitoring
            error_log("User agent changed for session: " . session_id());
            // Note: Don't destroy session as user agents can change legitimately
        }
    } else {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Session Configuration Info (for debugging in development)
    if (!$isProduction) {
        $_SESSION['_debug_info'] = [
            'session_name' => session_name(),
            'session_id' => session_id(),
            'cookie_params' => session_get_cookie_params(),
            'lifetime' => $config['lifetime'],
            'environment' => 'development',
            'https_enabled' => $isHTTPS
        ];
    }
}

/**
 * Utility Functions for Session Management
 */

/**
 * Check if session is valid and not expired
 */
function isSessionValid() {
    if (!isset($_SESSION['created']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $isProduction = (getenv('APP_ENV') === 'production') || 
                   (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']));
    
    // Use env override when provided to keep app timeout consistent with server GC
    $envLifetime = getenv('SESSION_MAX_LIFETIME');
    if ($envLifetime !== false && ctype_digit((string)$envLifetime)) {
        $maxLifetime = (int)$envLifetime;
    } else {
        $maxLifetime = $isProduction ? 7200 : 7200; // default: 2 hours for both prod and dev
    }
    
    if (time() - $_SESSION['last_activity'] > $maxLifetime) {
        return false;
    }
    
    return true;
}

/**
 * Securely destroy session
 */
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Clear session data
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            if (!function_exists('deleteSecureCookie')) {
                require_once __DIR__ . '/cookies.php';
            }
            deleteSecureCookie(session_name());
        }
        
        // Destroy session
        session_destroy();
    }
}

/**
 * Force session regeneration (call after login)
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        
        // Update IP and user agent after login
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

/**
 * Remember-me implementation (signed, httpOnly cookie)
 * - Creates a signed token with userId|expires|nonce and HMAC-SHA256
 * - Auto-login on next visit if session is missing and token is valid
 */
function getRememberMeCookieName() { return 'MMTVTC_REMEMBER'; }

function getRememberSecret() {
    $env = getenv('REMEMBER_ME_SECRET');
    if (!empty($env)) return $env;
    // Derive a per-host fallback secret (not ideal, but avoids migrations)
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return hash('sha256', 'remember-me|'.$host.'|'.__FILE__);
}

function signRememberPayload($payload) {
    $secret = getRememberSecret();
    return hash_hmac('sha256', $payload, $secret);
}

function setRememberMe($userId, $ttlDays = 30) {
    if (!function_exists('setSecureCookie')) { require_once __DIR__ . '/cookies.php'; }
    $expires = time() + ($ttlDays * 86400);
    $nonce = bin2hex(random_bytes(16));
    $payload = implode('|', [ (string)$userId, (string)$expires, $nonce ]);
    $sig = signRememberPayload($payload);
    $token = base64_encode($payload.'|'.$sig);
    setSecureCookie(getRememberMeCookieName(), $token, $ttlDays * 86400);
}

function clearRememberMe() {
    if (!function_exists('deleteSecureCookie')) { require_once __DIR__ . '/cookies.php'; }
    deleteSecureCookie(getRememberMeCookieName());
}

function autoLoginFromRememberMe() {
    // If already authenticated, nothing to do
    if (!empty($_SESSION['id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['email'])) { return; }
    $cookie = $_COOKIE[getRememberMeCookieName()] ?? '';
    if (empty($cookie)) return;
    $decoded = base64_decode($cookie, true);
    if ($decoded === false) return;
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) return; // userId|expires|nonce|sig
    list($userId, $expires, $nonce, $sig) = $parts;
    if (!ctype_digit($expires) || time() > (int)$expires) { clearRememberMe(); return; }
    $payload = implode('|', [ $userId, $expires, $nonce ]);
    if (!hash_equals(signRememberPayload($payload), $sig)) { clearRememberMe(); return; }

    // Optionally load user from DB to confirm existence
    try {
        if (!isset($GLOBALS['pdo'])) {
            $dbPath = __DIR__ . '/db_connect.php';
            if (file_exists($dbPath)) { require_once $dbPath; }
        }
        if (isset($GLOBALS['pdo'])) {
            // Load enough fields to satisfy downstream auth checks and routing
            $stmt = $GLOBALS['pdo']->prepare('SELECT id, email, is_verified, student_number, is_role FROM mmtvtc_users WHERE id = ? LIMIT 1');
            if ($stmt->execute([ (int)$userId ])) {
                $u = $stmt->fetch();
                if ($u) {
                    $_SESSION['id'] = (int)$u['id'];
                    $_SESSION['email'] = $u['email'] ?? null;
                    $_SESSION['user_verified'] = (bool)($u['is_verified'] ?? true);
                    // Populate fields required by requireAuth() and dashboards
                    if (isset($u['student_number']) && $u['student_number'] !== null && $u['student_number'] !== '') {
                        $_SESSION['student_number'] = $u['student_number'];
                    } else {
                        // Fallback: try to fetch student_number from students table
                        try {
                            $s = $GLOBALS['pdo']->prepare('SELECT student_number FROM students WHERE user_id = ? OR id = ? LIMIT 1');
                            if ($s->execute([ (int)$userId, (int)$userId ])) {
                                $sr = $s->fetch();
                                if ($sr && !empty($sr['student_number'])) {
                                    $_SESSION['student_number'] = $sr['student_number'];
                                }
                            }
                        } catch (Throwable $_e) { /* ignore */ }
                    }
                    if (isset($u['is_role'])) {
                        $_SESSION['is_role'] = $u['is_role'];
                        $_SESSION['user_role'] = $u['is_role'];
                    }
                    // Establish baseline session timestamps expected elsewhere
                    $_SESSION['login_time'] = $_SESSION['login_time'] ?? time();
                    $_SESSION['last_activity'] = time();
                    regenerateSession();
                    return;
                }
            }
        }
        // Fallback: trust token but mark unverified
        $_SESSION['id'] = (int)$userId;
        $_SESSION['user_verified'] = true;
        regenerateSession();
    } catch (Throwable $e) {
        // On any error, clear token
        error_log('Remember-me autologin failed: '.$e->getMessage());
        clearRememberMe();
    }
}

// Run remember-me auto login early each request, but skip if a recent logout flag exists
$__logoutFlag = isset($_COOKIE['MMTVTC_LOGOUT_FLAG']) && $_COOKIE['MMTVTC_LOGOUT_FLAG'] !== '';
if (!$__logoutFlag) {
    autoLoginFromRememberMe();
}

/**
 * Get session info for debugging (development only)
 */
/** function getSessionInfo() {
    $isProduction = (getenv('APP_ENV') === 'production') || 
                   (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']));
    
    if ($isProduction) {
        return null; // Don't expose session info in production
    }
    
    return $_SESSION['_debug_info'] ?? null;
} **/

// Auto-cleanup function for expired sessions (call this periodically)
function cleanupExpiredSessions() {
    if (rand(1, 100) === 1) { // 1% chance to run cleanup
        session_gc();
    }
}


cleanupExpiredSessions();

// Apply CSP AFTER session is fully initialized
require_once __DIR__ . '/csp.php';
?>