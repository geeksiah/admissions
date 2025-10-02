<?php
/**
 * Basic Test - No includes, just environment info
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Basic Environment Test</h1>\n";

echo "<h2>PHP Info</h2>\n";
echo "PHP Version: " . phpversion() . "<br>\n";
echo "Current Directory: " . getcwd() . "<br>\n";
echo "Script Path: " . __FILE__ . "<br>\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>\n";

echo "<h2>Session Info</h2>\n";
session_start();
echo "Session ID: " . session_id() . "<br>\n";
echo "Session Status: " . session_status() . "<br>\n";

if (isset($_SESSION['user_id'])) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>\n";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>\n";
    echo "First Name: " . ($_SESSION['first_name'] ?? 'Not set') . "<br>\n";
} else {
    echo "❌ No user session found<br>\n";
}

echo "<h2>File System</h2>\n";
$rootPath = dirname(__DIR__);
echo "Root Path: " . $rootPath . "<br>\n";

$checkFiles = [
    'config/config.php',
    'config/database.php',
    'models/User.php',
    'admin/dashboard_absolute.php'
];

foreach ($checkFiles as $file) {
    $fullPath = $rootPath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ " . $file . " exists<br>\n";
    } else {
        echo "❌ " . $file . " missing<br>\n";
    }
}

echo "<h2>Directory Contents</h2>\n";
echo "<h3>Root Directory:</h3>\n";
if (is_dir($rootPath)) {
    $files = scandir($rootPath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . (is_dir($rootPath . '/' . $file) ? ' (directory)' : '') . "<br>\n";
        }
    }
} else {
    echo "❌ Root directory not accessible<br>\n";
}

echo "<h3>Admin Directory:</h3>\n";
$adminPath = $rootPath . '/admin';
if (is_dir($adminPath)) {
    $files = scandir($adminPath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . "<br>\n";
        }
    }
} else {
    echo "❌ Admin directory not accessible<br>\n";
}

echo "<h2>Test Complete</h2>\n";
echo "<p>This basic test should always work. If you see this, PHP is functioning.</p>\n";
?>
