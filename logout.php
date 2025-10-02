<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize security
$database = new Database();
$security = new Security($database);

// Log the logout
if (isLoggedIn()) {
    $security->logSecurityEvent($_SESSION['user_id'], 'logout');
}

// Perform logout
$security->logout();

// Redirect to login page
redirect('/login.php');
?>
