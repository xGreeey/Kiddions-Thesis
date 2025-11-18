<?php
require_once 'db_connect.php';

/**
 * Comprehensive Abuse Protection System
 * Implements progressive delays, account lockouts, and rate limiting
 */

class AbuseProtection {
    private $pdo;
    private $config;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->config = [
            // Login attempt limits
            'max_login_attempts' => 500,
            'login_lockout_duration' => 900, // 15 minutes
            'progressive_delay_base' => 2, // seconds
            'progressive_delay_max' => 300, // 5 minutes
            
            // OTP rate limits
            'max_otp_requests_per_hour' => 50,
            'max_otp_requests_per_day' => 2000,
            'otp_cooldown_period' => 6, // 1 minute between requests
            
            // IP-based limits
            'max_attempts_per_ip_per_hour' => 50,
            'max_attempts_per_ip_per_day' => 200,
            
            // Account lockout escalation
            'escalation_threshold' => 10, // attempts before longer lockout
            'escalated_lockout_duration' => 3600, // 1 hour
            'permanent_lockout_threshold' => 25, // attempts before admin review
        ];
    }
    
    /**
     * Check if an IP address is rate limited
     */
    public function isIPRateLimited($ip, $action = 'login') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempt_count 
                FROM abuse_tracking 
                WHERE ip_address = ? 
                AND action_type = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$ip, $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $hourlyLimit = $this->config['max_attempts_per_ip_per_hour'];
            return $result['attempt_count'] >= $hourlyLimit;
            
        } catch (Exception $e) {
            error_log("IP rate limit check error: " . $e->getMessage());
            return false; // Fail open for availability
        }
    }
    
    /**
     * Check if an account is locked
     */
    public function isAccountLocked($identifier, $identifierType = 'email') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT locked_until, lockout_count, last_attempt 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND locked_until > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifierType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("Account lock check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get time remaining for account lock
     */
    public function getAccountLockTimeRemaining($identifier, $identifierType = 'email') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT locked_until 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND locked_until > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifierType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $lockTime = strtotime($result['locked_until']);
                $remaining = $lockTime - time();
                return max(0, $remaining);
            }
            
            return 0;
            
        } catch (Exception $e) {
            error_log("Account lock time check error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check OTP rate limits for an identifier
     */
    public function isOTPRateLimited($identifier, $identifierType = 'email') {
        try {
            // Check hourly limit
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'otp_request' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$identifier, $identifierType]);
            $hourlyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($hourlyCount >= $this->config['max_otp_requests_per_hour']) {
                return ['limited' => true, 'reason' => 'hourly_limit', 'retry_after' => 3600];
            }
            
            // Check daily limit
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'otp_request' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$identifier, $identifierType]);
            $dailyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($dailyCount >= $this->config['max_otp_requests_per_day']) {
                return ['limited' => true, 'reason' => 'daily_limit', 'retry_after' => 86400];
            }
            
            // Check cooldown period
            $stmt = $this->pdo->prepare("
                SELECT created_at 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'otp_request' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifierType]);
            $lastRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastRequest) {
                $lastRequestTime = strtotime($lastRequest['created_at']);
                $cooldownRemaining = $this->config['otp_cooldown_period'] - (time() - $lastRequestTime);
                
                if ($cooldownRemaining > 0) {
                    return ['limited' => true, 'reason' => 'cooldown', 'retry_after' => $cooldownRemaining];
                }
            }
            
            return ['limited' => false];
            
        } catch (Exception $e) {
            error_log("OTP rate limit check error: " . $e->getMessage());
            return ['limited' => false]; // Fail open
        }
    }
    
    /**
     * Record an abuse attempt
     */
    public function recordAttempt($identifier, $identifierType, $action, $ip, $success = false, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO abuse_tracking 
                (identifier, identifier_type, action_type, ip_address, success, details, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $detailsJson = json_encode($details);
            $stmt->execute([$identifier, $identifierType, $action, $ip, $success ? 1 : 0, $detailsJson]);
            
            // If failed attempt, check for lockout conditions
            if (!$success) {
                $this->checkAndApplyLockout($identifier, $identifierType, $ip);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to record abuse attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check and apply lockout if thresholds are exceeded
     */
    private function checkAndApplyLockout($identifier, $identifierType, $ip) {
        try {
            // Count recent failed attempts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as failed_count 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'login' 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$identifier, $identifierType]);
            $failedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($failedCount >= $this->config['max_login_attempts']) {
                $lockoutDuration = $this->config['login_lockout_duration'];
                
                // Escalate lockout duration for repeat offenders
                if ($failedCount >= $this->config['escalation_threshold']) {
                    $lockoutDuration = $this->config['escalated_lockout_duration'];
                }
                
                // Check for permanent lockout threshold
                if ($failedCount >= $this->config['permanent_lockout_threshold']) {
                    $this->flagForAdminReview($identifier, $identifierType, $failedCount);
                }
                
                // Apply lockout
                $lockoutUntil = date('Y-m-d H:i:s', time() + $lockoutDuration);
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO abuse_tracking 
                    (identifier, identifier_type, action_type, ip_address, success, details, locked_until, created_at) 
                    VALUES (?, ?, ?, ?, 0, ?, ?, NOW())
                ");
                
                $details = [
                    'lockout_reason' => 'failed_attempts',
                    'failed_count' => $failedCount,
                    'lockout_duration' => $lockoutDuration
                ];
                
                $stmt->execute([
                    $identifier, 
                    $identifierType, 
                    'account_lockout', 
                    $ip, 
                    json_encode($details), 
                    $lockoutUntil
                ]);
                
                // Log the lockout
                $this->logSecurityEvent('ACCOUNT_LOCKOUT_APPLIED', [
                    'identifier' => $identifier,
                    'identifier_type' => $identifierType,
                    'failed_count' => $failedCount,
                    'lockout_duration' => $lockoutDuration,
                    'ip' => $ip
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Lockout application error: " . $e->getMessage());
        }
    }
    
    /**
     * Calculate progressive delay for failed attempts
     */
    public function getProgressiveDelay($identifier, $identifierType) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as failed_count 
                FROM abuse_tracking 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'login' 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$identifier, $identifierType]);
            $failedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($failedCount <= 1) {
                return 0; // No delay for first few attempts
            }
            
            // Exponential backoff: base * (2 ^ (attempts - 1))
            $delay = $this->config['progressive_delay_base'] * pow(2, $failedCount - 2);
            $delay = min($delay, $this->config['progressive_delay_max']);
            
            return $delay;
            
        } catch (Exception $e) {
            error_log("Progressive delay calculation error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Flag account for admin review
     */
    private function flagForAdminReview($identifier, $identifierType, $attemptCount) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_alerts 
                (alert_type, identifier, identifier_type, details, created_at, status) 
                VALUES ('abuse_review', ?, ?, ?, NOW(), 'pending')
            ");
            
            $details = [
                'reason' => 'excessive_failed_attempts',
                'attempt_count' => $attemptCount,
                'requires_review' => true
            ];
            
            $stmt->execute([$identifier, $identifierType, json_encode($details)]);
            
            $this->logSecurityEvent('ACCOUNT_FLAGGED_FOR_REVIEW', [
                'identifier' => $identifier,
                'identifier_type' => $identifierType,
                'attempt_count' => $attemptCount
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to flag account for review: " . $e->getMessage());
        }
    }
    
    /**
     * Clear abuse records for successful authentication
     */
    public function clearAbuseRecords($identifier, $identifierType) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE abuse_tracking 
                SET success = 1, cleared_at = NOW() 
                WHERE identifier = ? 
                AND identifier_type = ? 
                AND action_type = 'login' 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$identifier, $identifierType]);
            
        } catch (Exception $e) {
            error_log("Failed to clear abuse records: " . $e->getMessage());
        }
    }
    
    /**
     * Get abuse statistics for monitoring
     */
    public function getAbuseStats($timeframe = '24h') {
        try {
            $interval = $timeframe === '24h' ? 'INTERVAL 1 DAY' : 'INTERVAL 1 HOUR';
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    action_type,
                    COUNT(*) as total_attempts,
                    SUM(success) as successful_attempts,
                    COUNT(*) - SUM(success) as failed_attempts,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT identifier) as unique_identifiers
                FROM abuse_tracking 
                WHERE created_at > DATE_SUB(NOW(), $interval)
                GROUP BY action_type
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get abuse stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log security event
     */
    private function logSecurityEvent($event, $details = []) {
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent($event, $details);
        } else {
            error_log("Security Event: $event - " . json_encode($details));
        }
    }
}


/**
 * Initialize abuse protection system
 */
function initializeAbuseProtection($pdo) {
    try {
        return new AbuseProtection($pdo);
        
    } catch (Exception $e) {
        error_log("Failed to initialize abuse protection: " . $e->getMessage());
        return null;
    }
}

/**
 * Middleware function to check abuse protection before processing requests
 */
function checkAbuseProtection($identifier, $identifierType, $action, $ip) {
    global $pdo;
    
    $abuseProtection = initializeAbuseProtection($pdo);
    if (!$abuseProtection) {
        return ['allowed' => true]; // Fail open
    }
    
    // Check IP rate limits
    if ($abuseProtection->isIPRateLimited($ip, $action)) {
        return [
            'allowed' => false,
            'reason' => 'ip_rate_limited',
            'message' => 'Too many requests from this IP address. Please try again later.'
        ];
    }
    
    // Check account lockout
    if ($abuseProtection->isAccountLocked($identifier, $identifierType)) {
        $timeRemaining = $abuseProtection->getAccountLockTimeRemaining($identifier, $identifierType);
        return [
            'allowed' => false,
            'reason' => 'account_locked',
            'message' => "Account is temporarily locked. Please try again in {$timeRemaining} seconds.",
            'retry_after' => $timeRemaining
        ];
    }
    
    // Check OTP rate limits for OTP requests
    if ($action === 'otp_request') {
        $otpCheck = $abuseProtection->isOTPRateLimited($identifier, $identifierType);
        if ($otpCheck['limited']) {
            return [
                'allowed' => false,
                'reason' => 'otp_rate_limited',
                'message' => 'OTP request rate limit exceeded. Please try again later.',
                'retry_after' => $otpCheck['retry_after']
            ];
        }
    }
    
    return ['allowed' => true];
}

?>
