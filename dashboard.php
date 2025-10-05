<?php
/**
 * Dashboard Redirect
 * This file redirects direct dashboard access to the proper routing
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to proper dashboard based on user role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admissions_officer' || $_SESSION['role'] === 'reviewer') {
        header('Location: admin/dashboard-simple.php');
    } else {
        header('Location: student/dashboard.php');
    }
} else {
    // Default to admin dashboard if role is not set
    header('Location: admin/dashboard-simple.php');
}
exit;
?>