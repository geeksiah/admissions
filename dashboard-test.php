<?php
echo "<h1>Dashboard Test</h1>";
echo "<p>This is a test dashboard to check if the issue is with the dashboard file.</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";

// Test basic PHP
echo "<p>PHP Version: " . phpversion() . "</p>";

echo "<h2>Links</h2>";
echo "<p><a href='login.php'>Login</a></p>";
echo "<p><a href='test-hostinger.php'>Test Hostinger</a></p>";
?>
