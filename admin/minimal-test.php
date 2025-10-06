<?php
echo "<h1>Minimal Admin Test</h1>";
echo "<p>This is a minimal admin test with no dependencies.</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Test basic PHP
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";

// Test file access
echo "<h2>File Access Test</h2>";
echo "<p>config/config.php exists: " . (file_exists('../config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>config/database.php exists: " . (file_exists('../config/database.php') ? 'YES' : 'NO') . "</p>";

// Test basic includes
echo "<h2>Testing Basic Includes</h2>";
try {
    if (file_exists('../config/config.php')) {
        require_once '../config/config.php';
        echo "<p>✅ config/config.php loaded</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading config: " . $e->getMessage() . "</p>";
}

try {
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        echo "<p>✅ config/database.php loaded</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading database: " . $e->getMessage() . "</p>";
}

echo "<h2>Links</h2>";
echo "<p><a href='../dashboard'>Dashboard</a></p>";
echo "<p><a href='../login'>Login</a></p>";
echo "<p><a href='../test-hostinger'>Test Hostinger</a></p>";
?>
