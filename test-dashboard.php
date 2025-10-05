<?php
session_start();

echo "<h1>Test Dashboard</h1>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not logged in') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

echo "<h2>Navigation Test Links</h2>";
echo "<p><a href='/dashboard'>Dashboard (Clean URL)</a></p>";
echo "<p><a href='/admin/applications'>Admin Applications (Clean URL)</a></p>";
echo "<p><a href='/admin/students'>Admin Students (Clean URL)</a></p>";
echo "<p><a href='/student/applications'>Student Applications (Clean URL)</a></p>";
echo "<p><a href='/debug'>Debug Page (Clean URL)</a></p>";

echo "<h2>Direct File Links</h2>";
echo "<p><a href='dashboard.php'>Dashboard (Direct)</a></p>";
echo "<p><a href='admin/dashboard.php'>Admin Dashboard (Direct)</a></p>";
echo "<p><a href='admin/applications.php'>Admin Applications (Direct)</a></p>";
echo "<p><a href='debug-navigation.php'>Debug Navigation (Direct)</a></p>";

echo "<h2>Login Test</h2>";
echo "<p><a href='/login'>Login (Clean URL)</a></p>";
echo "<p><a href='login.php'>Login (Direct)</a></p>";

if (isset($_SESSION['user_id'])) {
    echo "<h2>Logout</h2>";
    echo "<p><a href='/logout'>Logout (Clean URL)</a></p>";
    echo "<p><a href='logout.php'>Logout (Direct)</a></p>";
}
?>
