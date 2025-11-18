<?php
require_once __DIR__ . '/../security/session_config.php';
require_once __DIR__ . '/../security/cookies.php';

// Debug: log logout request context
error_log('LOGOUT: begin | sid=' . (session_id() ?: 'none') . ' | cookie_name=' . session_name() . ' | cookies=' . json_encode(array_keys($_COOKIE ?? [])));

// Clear remember-me token (if any)
if (function_exists('clearRememberMe')) { clearRememberMe(); }

// Fully destroy the session and its cookie
if (function_exists('destroySession')) {
    destroySession();
    error_log('LOGOUT: destroySession() called');
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        deleteSecureCookie(session_name());
    }
    session_destroy();
    error_log('LOGOUT: manual session destroy path used');
}

// Optional: set a short-lived flag so other tabs that load root shortly after logout won't autologin
setSecureCookie('MMTVTC_LOGOUT_FLAG', '1', 60);
error_log('LOGOUT: set logout flag and redirect to index.php');

header('Location: /home');
exit();
?>