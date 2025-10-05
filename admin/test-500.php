<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>500 Error Debug</h1>";

echo "<h2>Step 1: Basic PHP</h2>";
echo "<p>PHP is working</p>";

echo "<h2>Step 2: Session Test</h2>";
session_start();
echo "<p>Session started successfully</p>";
echo "<p>Session ID: " . session_id() . "</p>";

echo "<h2>Step 3: Config Loading</h2>";
try {
    require_once '../config/config.php';
    echo "<p>Config loaded successfully</p>";
    echo "<p>APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'Not defined') . "</p>";
} catch (Exception $e) {
    echo "<p>Config loading failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 4: Database Loading</h2>";
try {
    require_once '../config/database.php';
    echo "<p>Database class loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>Database loading failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 5: Database Connection</h2>";
try {
    $database = new Database();
    echo "<p>Database connection created successfully</p>";
} catch (Exception $e) {
    echo "<p>Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 6: SecurityManager Loading</h2>";
try {
    require_once '../classes/SecurityManager.php';
    echo "<p>SecurityManager class loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>SecurityManager loading failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 7: SecurityManager Creation</h2>";
try {
    $security = new SecurityManager($database);
    echo "<p>SecurityManager created successfully</p>";
} catch (Exception $e) {
    echo "<p>SecurityManager creation failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 8: Authentication Check</h2>";
try {
    echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p>User not logged in</p>";
    } else {
        echo "<p>User is logged in</p>";
    }
} catch (Exception $e) {
    echo "<p>Authentication check failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 9: Test Links</h2>";
echo "<p><a href='/admin/dashboard-simple'>Simple Dashboard</a></p>";
echo "<p><a href='/admin/test-500'>This Test Page</a></p>";
echo "<p><a href='/debug'>Debug Page</a></p>";
echo "<p><a href='/dashboard'>Main Dashboard</a></p>";
?>
