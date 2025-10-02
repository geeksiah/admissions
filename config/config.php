<?php
/**
 * Comprehensive Admissions Management System
 * Application Configuration
 * 
 * This file contains all system-wide configuration settings including:
 * - Application metadata (name, version, URL)
 * - Security settings (session, CSRF, password policies)
 * - File upload configurations
 * - Email and SMS settings
 * - Backup and recovery settings
 * - Performance and caching settings
 * - Helper functions for common operations
 * 
 * @package AdmissionsManagement
 * @version 1.0.0
 * @author Admissions Management Team
 * @since 2024-01-01
 */

// Application Settings
define('APP_NAME', 'Admissions Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
define('APP_DEBUG', true); // Enable for debugging production issues

// Security Settings
define('SESSION_NAME', 'ADMISSIONS_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Email Settings
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@university.edu');
define('SMTP_FROM_NAME', 'University Admissions');
define('EMAIL_SEND_IMMEDIATELY', false);
define('EMAIL_BATCH_SIZE', 50);

// SMS Settings
define('SMS_PROVIDER', 'twilio');
define('SMS_FROM_NUMBER', '+1234567890');
define('SMS_ACCOUNT_SID', '');
define('SMS_AUTH_TOKEN', '');
define('SMS_SEND_IMMEDIATELY', false);
define('SMS_BATCH_SIZE', 100);

// Backup Settings
define('BACKUP_NOTIFICATION_EMAIL', 'admin@university.edu');
define('BACKUP_MAX_RETENTION_DAYS', 30);
define('BACKUP_COMPRESSION_ENABLED', true);

// Pagination Settings
define('RECORDS_PER_PAGE', 20);
define('MAX_PAGINATION_LINKS', 10);

// Database Constants (for backward compatibility)
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

// Timezone
date_default_timezone_set(TIMEZONE);

// Session Configuration
ini_set('session.name', SESSION_NAME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_httponly', true);
ini_set('session.use_strict_mode', true);

// Security Headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\';');
}

// Autoloader with absolute paths
spl_autoload_register(function ($class) {
    $rootPath = dirname(__DIR__);
    $directories = [
        $rootPath . '/classes/',
        $rootPath . '/models/',
        $rootPath . '/controllers/',
        $rootPath . '/helpers/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

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
    // Handle both relative and absolute URLs
    if (strpos($url, '/') === 0) {
        // Absolute URL
        header('Location: ' . $url);
    } else {
        // Relative URL - make it relative to current directory
        header('Location: ' . $url);
    }
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
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
        redirect('unauthorized.php');
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

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set security headers
setSecurityHeaders();
