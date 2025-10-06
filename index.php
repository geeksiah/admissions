<?php
/**
 * Comprehensive Admissions Management System
 * Main Entry Point
 */

session_start();

define('APP_ROOT', __DIR__);

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/User.php';

$database = new Database();
$pdo = $database->getConnection();
$userModel = new User($pdo);

if (!file_exists('config/installed.lock')) {
    header('Location: install/index.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $userModel->getById($_SESSION['user_id']);

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $currentUser['role'] ?? '';
switch ($role) {
    case 'admin':
    case 'super_admin':
        header('Location: admin/dashboard.php');
        break;
    case 'student':
        header('Location: student/dashboard.php');
        break;
    default:
        header('Location: unauthorized.php');
        break;
}
exit;