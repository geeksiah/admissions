<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>Minimal Admin Dashboard</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>User not logged in</p>";
    echo "<p><a href='/login'>Go to Login</a></p>";
    exit;
}

// Check if user has admin role
$allowedRoles = ['admin', 'super_admin', 'admissions_officer', 'reviewer'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    echo "<p style='color: red;'>Access denied. Your role: " . ($_SESSION['role'] ?? 'None') . "</p>";
    echo "<p><a href='/logout'>Logout</a></p>";
    exit;
}

echo "<p style='color: green;'>✓ User authenticated successfully</p>";
echo "<p><strong>User:</strong> " . ($_SESSION['first_name'] ?? '') . " " . ($_SESSION['last_name'] ?? '') . "</p>";
echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'None') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

echo "<h2>Navigation Test</h2>";
echo "<p><a href='/admin/applications'>Applications</a></p>";
echo "<p><a href='/admin/students'>Students</a></p>";
echo "<p><a href='/admin/programs'>Programs</a></p>";
echo "<p><a href='/admin/settings'>Settings</a></p>";
echo "<p><a href='/profile'>Profile</a></p>";
echo "<p><a href='/logout'>Logout</a></p>";

echo "<h2>Direct File Links</h2>";
echo "<p><a href='admin/applications.php'>Applications (Direct)</a></p>";
echo "<p><a href='admin/students.php'>Students (Direct)</a></p>";
echo "<p><a href='admin/programs.php'>Programs (Direct)</a></p>";

echo "<h2>Debug Links</h2>";
echo "<p><a href='/admin/test-500'>500 Error Debug</a></p>";
echo "<p><a href='/debug'>General Debug</a></p>";

echo "<h2>System Status</h2>";
echo "<p>✓ PHP Working</p>";
echo "<p>✓ Session Working</p>";
echo "<p>✓ Authentication Working</p>";
echo "<p>✓ Navigation Links Ready</p>";
?>
