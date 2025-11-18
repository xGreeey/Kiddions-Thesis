<?php
// Optionally load .env if using vlucas/phpdotenv
require_once __DIR__ . '/../vendor/autoload.php';
// Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

// Lightweight .env loader (no extra dependency)
// Loads key=value pairs from project root .env, ignoring comments and empty lines.
// Does not overwrite existing environment variables.
function loadEnvFile($envPath) {
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        // Strip surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        // Do not overwrite if already set in environment
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env from project root if present
loadEnvFile(__DIR__ . '/..' . '/.env');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: '';

$smtpHost = getenv('SMTP_HOST') ?: 'smtp.hostinger.com';
$smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USER') ?: '';
$smtpPass = getenv('SMTP_PASS') ?: '';
$smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: '';
$smtpFromName = getenv('SMTP_FROM_NAME') ?: 'MMTVTC';
$smtpSecure = getenv('SMTP_SECURE') ?: 'tls';

// Fail fast if critical settings are missing (avoid exposing secret values)
$missing = [];
foreach ([
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASS' => $dbPass,
    'SMTP_USER' => $smtpUser,
    'SMTP_PASS' => $smtpPass,
    'SMTP_FROM_EMAIL' => $smtpFromEmail,
] as $key => $val) {
    if ($val === '' || $val === null) { $missing[] = $key; }
}
if (!empty($missing)) {
    http_response_code(500);
    die('Configuration error: missing required environment variables: ' . implode(', ', $missing));
}

if (!defined('DB_SERVER')) define('DB_SERVER', $dbHost);
if (!defined('DB_USERNAME')) define('DB_USERNAME', $dbUser);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $dbPass);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);

if (!defined('SMTP_HOST')) define('SMTP_HOST', $smtpHost);
if (!defined('SMTP_PORT')) define('SMTP_PORT', $smtpPort);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', $smtpUser);
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', $smtpPass);
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', $smtpFromEmail);
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', $smtpFromName);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', $smtpSecure);

try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

?>