<?php
/**
 * URL Helper Functions
 * Common functions for generating secure URLs throughout the application
 */

// Include the URL router
require_once __DIR__ . '/url_router.php';

/**
 * Generate dashboard URL with user ID
 * @param string $userType Type of dashboard (student, admin, instructor)
 * @param string $userId User ID to include
 * @return string Generated URL
 */
function getDashboardUrl($userType = 'student', $userId = null) {
    $route = $userType === 'admin' ? 'admin' : ($userType === 'instructor' ? 'instructor' : 'dashboard');
    $params = $userId ? ['id' => $userId] : [];
    return generateSecureUrl($route, $params);
}

/**
 * Generate profile URL with user ID
 * @param string $userId User ID
 * @return string Generated URL
 */
function getProfileUrl($userId) {
    return generateSecureUrl('profile', ['id' => $userId]);
}

/**
 * Generate grades URL with parameters
 * @param string $userId User ID
 * @param string $courseId Course ID (optional)
 * @return string Generated URL
 */
function getGradesUrl($userId, $courseId = null) {
    $params = ['id' => $userId];
    if ($courseId) {
        $params['course_id'] = $courseId;
    }
    return generateSecureUrl('grades', $params);
}

/**
 * Generate API URL with parameters
 * @param string $endpoint API endpoint
 * @param array $params Parameters
 * @return string Generated URL
 */
function getApiUrl($endpoint, $params = []) {
    return generateSecureUrl("api/$endpoint", $params);
}

/**
 * Generate secure session URL
 * @param string $sessionId Session ID
 * @param string $route Route to redirect to
 * @return string Generated URL
 */
function getSessionUrl($sessionId, $route = 'dashboard') {
    return generateSecureUrl($route, ['session_id' => $sessionId]);
}

/**
 * Generate login redirect URL
 * @param string $redirectTo Where to redirect after login
 * @return string Generated URL
 */
function getLoginUrl($redirectTo = null) {
    $params = [];
    if ($redirectTo) {
        $params['redirect'] = $redirectTo;
    }
    return generateObfuscatedUrl('login', $params);
}

/**
 * Generate logout URL
 * @return string Generated URL
 */
function getLogoutUrl() {
    return generateObfuscatedUrl('login');
}

/**
 * Generate verification URL
 * @param string $token Verification token
 * @return string Generated URL
 */
function getVerificationUrl($token) {
    return generateObfuscatedUrl('verify_email', ['token' => $token]);
}

/**
 * Generate password reset URL
 * @param string $token Reset token
 * @return string Generated URL
 */
function getPasswordResetUrl($token) {
    return generateObfuscatedUrl('reset_password', ['token' => $token]);
}

/**
 * Generate forgot password URL
 * @return string Generated URL
 */
function getForgotPasswordUrl() {
    return generateObfuscatedUrl('forgot_password');
}

/**
 * Generate registration URL
 * @return string Generated URL
 */
function getRegistrationUrl() {
    return generateObfuscatedUrl('register');
}

/**
 * Generate home URL with optional parameters
 * @param array $params Parameters
 * @return string Generated URL
 */
function getHomeUrl($params = []) {
    return generateSecureUrl('home', $params);
}

/**
 * Generate notification URL
 * @param string $notificationId Notification ID
 * @return string Generated URL
 */
function getNotificationUrl($notificationId) {
    return getApiUrl('notifications', ['id' => $notificationId]);
}

/**
 * Generate announcement URL
 * @param string $announcementId Announcement ID
 * @return string Generated URL
 */
function getAnnouncementUrl($announcementId) {
    return getApiUrl('announcements', ['id' => $announcementId]);
}

/**
 * Generate job URL
 * @param string $jobId Job ID
 * @return string Generated URL
 */
function getJobUrl($jobId) {
    return getApiUrl('jobs', ['id' => $jobId]);
}

/**
 * Check if current URL is clean format
 * @return bool True if clean URL
 */
function isCurrentUrlClean() {
    return isCleanUrlRequest();
}

/**
 * Redirect to clean URL if needed
 * @param string $route Route name
 * @param array $params Parameters
 */
function ensureCleanUrl($route, $params = []) {
    if (!isCurrentUrlClean()) {
        redirectToCleanUrl($route, $params);
    }
}

/**
 * Generate breadcrumb URLs
 * @param array $breadcrumbs Array of breadcrumb items
 * @return array Array with URLs added
 */
function generateBreadcrumbUrls($breadcrumbs) {
    $result = [];
    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['url'])) {
            $result[] = $crumb;
        } else {
            $result[] = array_merge($crumb, ['url' => getHomeUrl()]);
        }
    }
    return $result;
}

/**
 * Generate pagination URLs
 * @param string $route Base route
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param array $additionalParams Additional parameters
 * @return array Array with pagination URLs
 */
function generatePaginationUrls($route, $currentPage, $totalPages, $additionalParams = []) {
    $urls = [];
    
    // Previous page
    if ($currentPage > 1) {
        $urls['prev'] = generateSecureUrl($route, array_merge($additionalParams, ['page' => $currentPage - 1]));
    }
    
    // Next page
    if ($currentPage < $totalPages) {
        $urls['next'] = generateSecureUrl($route, array_merge($additionalParams, ['page' => $currentPage + 1]));
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $urls['pages'][$i] = generateSecureUrl($route, array_merge($additionalParams, ['page' => $i]));
    }
    
    return $urls;
}
