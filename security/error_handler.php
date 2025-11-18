<?php
// Global error and exception handler for generic 500 responses with logging

if (!function_exists('registerGlobalErrorHandlers')) {
    function registerGlobalErrorHandlers() {
        // Convert PHP errors to ErrorException
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function ($e) {
            try {
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('UNHANDLED_EXCEPTION', [
                        'type' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                } else {
                    error_log('UNHANDLED_EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                }
            } catch (Throwable $inner) {
                // Avoid cascading failures
            }

            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Server Error</title></head><body><h1>Unexpected error</h1><p>Please try again later.</p></body></html>';
        });
    }
}

// Apply consistent error display based on environment
// - If APP_ENV=production (default), hide errors and log only
// - If APP_ENV=development or DEBUG_FORCE=true, show errors
function configureErrorDisplayFromEnv() {
    $appEnv = getenv('APP_ENV') ?: 'production';
    $debugForce = strtolower((string)(getenv('DEBUG_FORCE') ?: 'false'));
    $show = ($appEnv === 'development') || ($debugForce === 'true' || $debugForce === '1');
    if ($show) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        error_reporting(0);
    }
    ini_set('log_errors', '1');
}

registerGlobalErrorHandlers();
configureErrorDisplayFromEnv();
?>


