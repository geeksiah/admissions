<?php
/**
 * PHP 8.4 Compatible Configuration
 * Simplified version without potential compatibility issues
 */

// Application Settings
define('APP_NAME', 'Admissions Management System');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true);

// Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'u279576488_admissions');
define('DB_USER', 'u279576488_lapaz');
define('DB_PASS', '7uVV;OEX|');

// Security Settings
define('SESSION_NAME', 'ADMISSIONS_SESSION');
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// Pagination Settings
define('RECORDS_PER_PAGE', 20);

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

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

// Timezone
date_default_timezone_set(TIMEZONE);

// Session Configuration
ini_set('session.name', SESSION_NAME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_httponly', true);
ini_set('session.use_strict_mode', true);

// Utility Functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $target = '/login';
        header('Location: ' . $target);
        exit();
    }
}

function hasRole($requiredRoles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
    
    return in_array($userRole, $roles);
}

function requireRole($requiredRoles) {
    if (!hasRole($requiredRoles)) {
        $target = '/unauthorized';
        header('Location: ' . $target);
        exit();
    }
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = DATETIME_FORMAT) {
    return date($format, strtotime($datetime));
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
