<?php
/**
 * Comprehensive Admissions Management System
 * Main Entry Point
 * 
 * @author Admissions Management System
 * @version 1.0.0
 */

// Start session
session_start();

// Define application constants
define('APP_ROOT', __DIR__);
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));

// Include configuration
require_once 'config/config.php';
require_once 'config/database.php';

// Include core classes
require_once 'classes/Security.php';
require_once 'classes/Validator.php';
require_once 'classes/FileUpload.php';

// Initialize database and security
$database = new Database();
$security = new Security($database);

// License management and anti-tampering will be added later
// For now, skip these checks to ensure basic functionality works

// Check if installation is required
if (!file_exists('config/installed.lock')) {
    header('Location: install/index.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include user model to get user data
require_once 'models/User.php';
$pdo = $database->getConnection();
$userModel = new User($pdo);

// Get current user data
$currentUser = $userModel->getById($_SESSION['user_id']);

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Redirect based on user role
$role = $currentUser['role'] ?? '';
switch ($role) {
    case 'admin':
    case 'super_admin':
        header('Location: admin/dashboard_working.php');
        break;
    case 'student':
        header('Location: student/dashboard.php');
        break;
    default:
        header('Location: unauthorized.php');
        break;
}
exit;
?>
