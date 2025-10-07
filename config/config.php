<?php
/**
 * Comprehensive Admissions Management System
 * Application Configuration
 */

// Application Settings
define('APP_NAME', 'Admissions Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
define('APP_DEBUG', true);

// Security Settings
define('SESSION_NAME', 'ADMISSIONS_SESSION');
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Email Settings
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@university.edu');
define('SMTP_FROM_NAME', 'University Admissions');

// Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'admissions_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Date and Time Settings
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIMEZONE', 'America/New_York');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', 0); 
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_httponly', true);
    ini_set('session.use_strict_mode', true);
}

function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src 'self';");
}

spl_autoload_register(function ($class) {
    $rootPath = dirname(__DIR__);
    $directories = [
        $rootPath . '/classes/',
        $rootPath . '/models/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login');
        exit;
    }
}

function requireRole($requiredRoles) {
    requireLogin();
    $userRole = $_SESSION['role'] ?? '';
    $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
    
    if (!in_array($userRole, $roles)) {
        header('Location: /unauthorized');
        exit;
    }
}

function formatDate($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setSecurityHeaders();