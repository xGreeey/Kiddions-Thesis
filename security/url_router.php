    <?php
/**
 * URL Router and Parameter Handler
 * Provides secure URL parameter processing and validation
 */

class URLRouter {
    private static $instance = null;
    private $validRoutes = [];
    private $parameterValidators = [];
    
    private function __construct() {
        $this->initializeValidators();
        $this->initializeRoutes();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize parameter validators for security
     */
    private function initializeValidators() {
        $this->parameterValidators = [
            'id' => function($value) {
                // Validate ID format (alphanumeric, 8-64 characters)
                return preg_match('/^[a-zA-Z0-9]{8,64}$/', $value);
            },
            'token' => function($value) {
                // Validate token format (alphanumeric, 32-128 characters)
                return preg_match('/^[a-zA-Z0-9]{32,128}$/', $value);
            },
            'user_id' => function($value) {
                // Validate user ID (numeric or alphanumeric)
                return preg_match('/^[a-zA-Z0-9]{1,20}$/', $value);
            },
            'session_id' => function($value) {
                // Validate session ID format
                return preg_match('/^[a-zA-Z0-9]{26,64}$/', $value);
            }
        ];
    }
    
    /**
     * Initialize valid routes for security
     */
    private function initializeRoutes() {
        $this->validRoutes = [
            'home' => ['id', 'token', 'ref'],
            'dashboard' => ['id', 'user_id', 'session_id'],
            'admin' => ['id', 'user_id', 'session_id'],
            'instructor' => ['id', 'user_id', 'session_id'],
            'profile' => ['id', 'user_id'],
            'grades' => ['id', 'user_id', 'course_id'],
            'api/notifications' => ['id', 'user_id'],
            'api/announcements' => ['id', 'user_id'],
            'api/jobs' => ['id', 'user_id', 'category']
        ];
    }
    
    /**
     * Get and validate URL parameters
     * @param string $route The route being accessed
     * @return array Validated parameters
     */
    public function getValidatedParameters($route = null) {
        $parameters = [];
        $route = $route ?: $this->getCurrentRoute();
        
        // Get all GET parameters
        foreach ($_GET as $key => $value) {
            if ($this->isValidParameter($route, $key, $value)) {
                $parameters[$key] = $this->sanitizeParameter($key, $value);
            } else {
                // Log suspicious parameter access
                $this->logSuspiciousAccess($route, $key, $value);
            }
        }
        
        return $parameters;
    }
    
    /**
     * Get current route from URL
     * @return string Current route
     */
    public function getCurrentRoute() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = trim($path, '/');
        
        // Remove query string from path
        $path = strtok($path, '?');
        
        return $path ?: 'home';
    }
    
    /**
     * Validate if parameter is allowed for route
     * @param string $route Route name
     * @param string $key Parameter key
     * @param string $value Parameter value
     * @return bool True if valid
     */
    private function isValidParameter($route, $key, $value) {
        // Check if route exists
        if (!isset($this->validRoutes[$route])) {
            return false;
        }
        
        // Check if parameter is allowed for this route
        if (!in_array($key, $this->validRoutes[$route])) {
            return false;
        }
        
        // Check if parameter format is valid
        if (isset($this->parameterValidators[$key])) {
            return $this->parameterValidators[$key]($value);
        }
        
        // Default validation for unknown parameters
        return $this->validateDefaultParameter($value);
    }
    
    /**
     * Default parameter validation
     * @param string $value Parameter value
     * @return bool True if valid
     */
    private function validateDefaultParameter($value) {
        // Basic security validation
        if (empty($value) || strlen($value) > 255) {
            return false;
        }
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize parameter value
     * @param string $key Parameter key
     * @param string $value Parameter value
     * @return string Sanitized value
     */
    private function sanitizeParameter($key, $value) {
        // Remove any potential XSS
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        // Trim whitespace
        $value = trim($value);
        
        // Additional sanitization based on parameter type
        switch ($key) {
            case 'id':
            case 'user_id':
            case 'session_id':
            case 'token':
                // Keep only alphanumeric characters
                $value = preg_replace('/[^a-zA-Z0-9]/', '', $value);
                break;
                
            case 'ref':
            case 'category':
                // Allow alphanumeric, hyphens, and underscores
                $value = preg_replace('/[^a-zA-Z0-9\-_]/', '', $value);
                break;
        }
        
        return $value;
    }
    
    /**
     * Generate secure URL with parameters
     * @param string $route Route name
     * @param array $parameters Parameters to include
     * @return string Generated URL
     */
    public function generateSecureUrl($route, $parameters = []) {
        $baseUrl = $this->getBaseUrl();
        $url = $baseUrl . '/' . $route;
        
        if (!empty($parameters)) {
            $validParams = [];
            foreach ($parameters as $key => $value) {
                if ($this->isValidParameter($route, $key, $value)) {
                    $validParams[$key] = $this->sanitizeParameter($key, $value);
                }
            }
            
            if (!empty($validParams)) {
                $url .= '?' . http_build_query($validParams);
            }
        }
        
        return $url;
    }
    
    /**
     * Generate obfuscated URL for sensitive operations
     * @param string $action Action to perform
     * @param array $parameters Parameters to include
     * @return string Obfuscated URL
     */
    public function generateObfuscatedUrl($action, $parameters = []) {
        $obfuscatedRoutes = [
            'login' => 'EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu',
            'verify_email' => 'otp',
            'email_verification' => 'vdbZscYYEJbqotvNnWlyA8I1gwfpcH',
            'forgot_password' => 'aKVeZ02vR7CTx28Jylr5FVaRxVFHzg',
            'reset_password' => 'nsZLoj1b49kcshf6JhimM3Tvdn1rLK'
        ];
        
        if (!isset($obfuscatedRoutes[$action])) {
            return $this->generateSecureUrl($action, $parameters);
        }
        
        $baseUrl = $this->getBaseUrl();
        $url = $baseUrl . '/' . $obfuscatedRoutes[$action];
        
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        
        return $url;
    }
    
    /**
     * Get base URL
     * @return string Base URL
     */
    private function getBaseUrl() {
        // Detect HTTPS accurately, including common proxy headers
        $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHTTPS ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        
        return rtrim($protocol . '://' . $host . $path, '/');
    }
    
    /**
     * Log suspicious access attempts
     * @param string $route Route being accessed
     * @param string $key Parameter key
     * @param string $value Parameter value
     */
    private function logSuspiciousAccess($route, $key, $value) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'route' => $route,
            'parameter_key' => $key,
            'parameter_value' => substr($value, 0, 100), // Limit log size
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $logDir = 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logMessage = "SUSPICIOUS_PARAMETER_ACCESS - " . json_encode($logData) . "\n";
        @file_put_contents($logDir . '/security.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check if current request is using clean URL format
     * @return bool True if using clean URL
     */
    public function isCleanUrlRequest() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH);
        
        // Check if path doesn't contain .php extension
        return !preg_match('/\.php/', $path);
    }
    
    /**
     * Redirect to clean URL format
     * @param string $route Route name
     * @param array $parameters Parameters to include
     */
    public function redirectToCleanUrl($route, $parameters = []) {
        $cleanUrl = $this->generateSecureUrl($route, $parameters);
        header("Location: $cleanUrl", true, 301);
        exit();
    }
}

// Global helper functions
function getUrlParameters($route = null) {
    return URLRouter::getInstance()->getValidatedParameters($route);
}

function generateSecureUrl($route, $parameters = []) {
    return URLRouter::getInstance()->generateSecureUrl($route, $parameters);
}

function generateObfuscatedUrl($action, $parameters = []) {
    return URLRouter::getInstance()->generateObfuscatedUrl($action, $parameters);
}

function redirectToCleanUrl($route, $parameters = []) {
    URLRouter::getInstance()->redirectToCleanUrl($route, $parameters);
}

function isCleanUrlRequest() {
    return URLRouter::getInstance()->isCleanUrlRequest();
}
