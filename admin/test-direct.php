<?php
// Test direct access without .htaccess
echo "<h1>Direct Access Test</h1>";
echo "<p>This file is accessed directly: admin/test-direct.php</p>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";

// Test if we can access other files
echo "<h2>File Access Test</h2>";
echo "<p>config/config.php exists: " . (file_exists('../config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>config/database.php exists: " . (file_exists('../config/database.php') ? 'YES' : 'NO') . "</p>";

echo "<h2>Links</h2>";
echo "<p><a href='simple-test.php'>Simple Test (Direct)</a></p>";
echo "<p><a href='dashboard-ultra-simple.php'>Ultra Simple Dashboard (Direct)</a></p>";
echo "<p><a href='../index.php'>Index (Direct)</a></p>";
?>
