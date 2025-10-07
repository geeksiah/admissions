<?php
// Core configuration and helpers (PHP 8.2+ compatible)

// If installer created secrets, load them first so DB_* are defined
if (file_exists(__DIR__ . '/db_secrets.php')) {
    require_once __DIR__ . '/db_secrets.php';
}

// App
define('APP_NAME', 'Admissions Management System');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true);

// During install these are written; provide safe defaults
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: '');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');

// Sessions
define('SESSION_NAME', 'ADMISSIONS_SESSION');
ini_set('session.name', SESSION_NAME);
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (session_status() === PHP_SESSION_NONE) session_start();

// Errors
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Helpers
function sanitizeInput($value) { return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8'); }
function isLoggedIn() { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
function requireLogin() { if (!isLoggedIn()) { header('Location: /login'); exit; } }
function hasRole($roles) {
    $role = $_SESSION['role'] ?? '';
    $required = is_array($roles) ? $roles : [$roles];
    return in_array($role, $required, true);
}
function requireRole($roles) { if (!hasRole($roles)) { header('Location: /unauthorized'); exit; } }
function generateCSRFToken() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function validateCSRFToken($t) { return isset($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t); }
function formatBytes($bytes, $precision = 2) { $units = ['B','KB','MB','GB','TB']; $i=0; while ($bytes >= 1024 && $i < count($units)-1) { $bytes/=1024; $i++; } return round($bytes,$precision).' '.$units[$i]; }

?>


