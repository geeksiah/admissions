<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Config Test</h1>";

echo "<h2>Step 1: Check if config files exist</h2>";
echo "<p>config/config.php exists: " . (file_exists('config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>config/database.php exists: " . (file_exists('config/database.php') ? 'YES' : 'NO') . "</p>";

echo "<h2>Step 2: Try to load config.php</h2>";
try {
    require_once 'config/config.php';
    echo "<p style='color: green;'>✓ config.php loaded successfully</p>";
    echo "<p>APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'Not defined') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ config.php failed: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ config.php error: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 3: Try to load database.php</h2>";
try {
    require_once 'config/database.php';
    echo "<p style='color: green;'>✓ database.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ database.php failed: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ database.php error: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 4: Try to create Database object</h2>";
try {
    $database = new Database();
    echo "<p style='color: green;'>✓ Database object created successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database creation failed: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Database creation error: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 5: Test Links</h2>";
echo "<p><a href='/admin/dashboard-minimal'>Minimal Dashboard</a></p>";
echo "<p><a href='/admin/test-500'>500 Error Debug</a></p>";
echo "<p><a href='/debug'>General Debug</a></p>";
?>
