<?php
// Include required files
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/csp.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/error_handler.php';

// Environment configuration
if (getenv('APP_ENV') !== 'development') {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

/**
 * Set global security headers for all pages
 */
function setGlobalSecurityHeaders() {
    // Remove sensitive headers if present
    header_remove('X-Powered-By');
    header_remove('Server');
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent page from being embedded in frames
    header('X-Frame-Options: DENY');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Disable DNS prefetching
    header('X-DNS-Prefetch-Control: off');

    // Cross-origin policies
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    // Note: COEP can break embeddings; omit or set only after testing
    // header('Cross-Origin-Embedder-Policy: require-corp');
    
    // Force HTTPS (if in production)
    $isProduction = (getenv('APP_ENV') === 'production') || 
                   (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']));
    
    if ($isProduction) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // CSP is applied via security/csp.php
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions policy
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');

    // Limit content types Adobe Flash/Acrobat etc.
    header('X-Permitted-Cross-Domain-Policies: none');

    // Prevent file downloads from opening automatically in IE
    header('X-Download-Options: noopen');
    
    // Cache control for sensitive pages
    if (isset($_SESSION['id'])) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

// Apply security headers
setGlobalSecurityHeaders();

/**
 * Generate CSRF token if not exists
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Security logging function
 * @param string $event Event name
 * @param array $details Event details
 */
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
        'user_id' => $_SESSION['id'] ?? null,
        'details' => $details
    ];
    
    $log = json_encode($logData) . "\n";
    file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
    
    // Log critical events to system log
    $criticalEvents = [
        'CSRF_ATTACK', 'SQL_INJECTION_ATTEMPT', 'UNAUTHORIZED_ACCESS_ATTEMPT',
        'MULTIPLE_FAILED_LOGIN', 'ACCOUNT_TAKEOVER_ATTEMPT', 'MALICIOUS_FILE_UPLOAD'
    ];
    
    if (in_array($event, $criticalEvents)) {
        error_log("SECURITY ALERT: $event - " . json_encode($details));
        
        // You could also send email alerts or notifications here
        // sendSecurityAlert($event, $details);
    }
}

/**
 * Input validation and sanitization functions
 */
function validatePhoneNumber($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    if (strlen($password) < 8 || strlen($password) > 128) {
        return false;
    }
    
    // Check for at least one letter and one number
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function sanitizeForSQL($input) {
    return trim($input); // Use with prepared statements only
}

/**
 * Rate limiting functions
 */
function checkRateLimit($pdo, $ip, $action = 'general', $maxAttempts = 10, $timeWindow = 300) {
    try {
        $windowStart = time() - $timeWindow;
        
        // Clean old attempts
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->execute([date('Y-m-d H:i:s', $windowStart)]);
        
        // Check current attempts
        $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM rate_limits WHERE ip = ? AND action = ? AND created_at > ?");
        $stmt->execute([$ip, $action, date('Y-m-d H:i:s', $windowStart)]);
        $result = $stmt->fetch();
        
        return ($result['attempts'] ?? 0) < $maxAttempts;
        
    } catch (Exception $e) {
        logSecurityEvent('RATE_LIMIT_CHECK_ERROR', ['error' => $e->getMessage()]);
        return true; // Allow on error to prevent DoS
    }
}

function recordRateLimitAttempt($pdo, $ip, $action = 'general', $userId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, action, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$ip, $action, $userId]);
    } catch (Exception $e) {
        logSecurityEvent('RATE_LIMIT_RECORD_ERROR', ['error' => $e->getMessage()]);
    }
}

/**
 * SQL Injection detection
 */
function detectSQLInjection($input) {
    $patterns = [
        '/(\bunion\b.*\bselect\b)/i',
        '/(\bselect\b.*\bfrom\b)/i',
        '/(\binsert\b.*\binto\b)/i',
        '/(\bdelete\b.*\bfrom\b)/i',
        '/(\bdrop\b.*\btable\b)/i',
        '/(\bor\b.*=.*)/i',
        '/(\'.*or.*\'.*=.*\')/i',
        '/(\b(and|or)\b.*\b(true|false)\b)/i',
        '/(\/\*.*\*\/)/i',
        '/(-{2,})/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * XSS detection and prevention
 */
function detectXSS($input) {
    $patterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe\b[^>]*>/i',
        '/<object\b[^>]*>/i',
        '/<embed\b[^>]*>/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * File upload security
 */
function validateFileUpload($file) {
    // Check if file was uploaded without errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Allowed MIME types
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    // Check MIME type
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Verify actual file content
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }
    
    // Check for malicious content in filename
    $filename = $file['name'];
    if (preg_match('/\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$/i', $filename)) {
        return false;
    }
    
    return true;
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Secure password hashing
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3          // 3 threads
    ]);
}


/**
 * Initialize rate limiting table if needed
 */
function initializeRateLimitTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL DEFAULT 'general',
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_action (ip, action),
            INDEX idx_created_at (created_at)
        )";
        
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        logSecurityEvent('RATE_LIMIT_TABLE_INIT_ERROR', ['error' => $e->getMessage()]);
        return false;
    }
}

// Initialize rate limiting table
try {
    initializeRateLimitTable($pdo);
} catch (Exception $e) {
    // Handle gracefully
}

// Validate session security
if (function_exists('validateSessionSecurity')) {
    validateSessionSecurity();
}

// Clean up old logs periodically (1% chance)
if (rand(1, 100) === 1) {
    try {
        $logFile = 'logs/security.log';
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10MB
            // Archive old log
            rename($logFile, 'logs/security_' . date('Y-m-d_H-i-s') . '.log');
        }
    } catch (Exception $e) {
        // Handle gracefully
    }
}
?>