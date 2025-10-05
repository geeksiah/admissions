<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Navigation Debug</h1>";

// Test 1: Check if session is working
session_start();
echo "<h2>Session Test</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
echo "<p>Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";

// Test 2: Check if config files exist
echo "<h2>Config Test</h2>";
echo "<p>config.php exists: " . (file_exists('config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>database.php exists: " . (file_exists('config/database.php') ? 'YES' : 'NO') . "</p>";

// Test 3: Check if SecurityManager exists
echo "<h2>SecurityManager Test</h2>";
echo "<p>SecurityManager.php exists: " . (file_exists('classes/SecurityManager.php') ? 'YES' : 'NO') . "</p>";

// Test 4: Try to load config
echo "<h2>Config Loading Test</h2>";
try {
    require_once 'config/config.php';
    echo "<p>Config loaded successfully</p>";
    echo "<p>APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'Not defined') . "</p>";
} catch (Exception $e) {
    echo "<p>Config loading failed: " . $e->getMessage() . "</p>";
}

// Test 5: Try to load database
echo "<h2>Database Test</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    echo "<p>Database class loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>Database loading failed: " . $e->getMessage() . "</p>";
}

// Test 6: Check file paths
echo "<h2>File Path Test</h2>";
echo "<p>admin/dashboard.php exists: " . (file_exists('admin/dashboard.php') ? 'YES' : 'NO') . "</p>";
echo "<p>student/dashboard.php exists: " . (file_exists('student/dashboard.php') ? 'YES' : 'NO') . "</p>";

// Test 7: Check .htaccess
echo "<h2>.htaccess Test</h2>";
echo "<p>.htaccess exists: " . (file_exists('.htaccess') ? 'YES' : 'NO') . "</p>";

// Test 8: Simulate dashboard redirect logic
echo "<h2>Dashboard Redirect Test</h2>";
if (isset($_SESSION['role'])) {
    echo "<p>User role: " . $_SESSION['role'] . "</p>";
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admissions_officer' || $_SESSION['role'] === 'reviewer') {
        echo "<p>Would redirect to: admin/dashboard.php</p>";
    } else {
        echo "<p>Would redirect to: student/dashboard.php</p>";
    }
} else {
    echo "<p>No role set, would redirect to: admin/dashboard.php</p>";
}

echo "<h2>Test Links</h2>";
echo "<p><a href='/dashboard'>Dashboard (Clean URL)</a></p>";
echo "<p><a href='/admin/applications'>Admin Applications (Clean URL)</a></p>";
echo "<p><a href='dashboard.php'>Dashboard (Direct)</a></p>";
echo "<p><a href='admin/dashboard.php'>Admin Dashboard (Direct)</a></p>";
?>
