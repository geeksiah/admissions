<?php
// Hostinger test file
echo "<h1>Hostinger Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";

// Test file access
echo "<h2>File Access Test</h2>";
echo "<p>config/config.php exists: " . (file_exists('config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>login.php exists: " . (file_exists('login.php') ? 'YES' : 'NO') . "</p>";

echo "<h2>Test Links</h2>";
echo "<p><a href='login.php'>Login (Direct)</a></p>";
echo "<p><a href='/login'>Login (Clean URL)</a></p>";
echo "<p><a href='/test-simple'>Test Simple (Clean URL)</a></p>";
echo "<p><a href='admin/test-direct.php'>Test Direct (Direct)</a></p>";
?>
