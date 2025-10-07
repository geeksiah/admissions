<?php
session_start();

echo "<h1>Session Test</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";

echo "<h2>Session Data:</h2>";
if (empty($_SESSION)) {
    echo "<p>No session data found.</p>";
    echo "<p><a href='/login'>Go to Login</a></p>";
} else {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}

echo "<h2>Server Info:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Test Links:</h2>";
echo "<p><a href='/admin/dashboard'>Test Admin Dashboard</a></p>";
echo "<p><a href='/test-basic'>Test Basic PHP</a></p>";
?>
