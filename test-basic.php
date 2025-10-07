<?php
// Basic PHP test
echo "<h1>Basic PHP Test</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

// Test session
echo "<h2>Session Test</h2>";
session_start();
echo "Session ID: " . session_id() . "<br>";

// Set test session data
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test_admin';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Admin';
$_SESSION['role'] = 'admin';

echo "Session data set successfully<br>";

// Test basic HTML
echo "<h2>HTML Test</h2>";
echo "<p>If you can see this, PHP and HTML are working.</p>";

// Test Bootstrap
echo "<h2>Bootstrap Test</h2>";
echo '<div class="alert alert-success">Bootstrap CSS is working!</div>';

echo "<h2>Links Test</h2>";
echo '<a href="/admin/dashboard" class="btn btn-primary">Test Admin Dashboard</a>';
?>
