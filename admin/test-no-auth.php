<?php
echo "<h1>Admin Test - No Authentication</h1>";
echo "<p>This admin test bypasses authentication to isolate the issue.</p>";

// Test basic PHP
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session data: " . print_r($_SESSION, true) . "</p>";

// Test file access
echo "<h2>File Access Test</h2>";
echo "<p>config/config.php exists: " . (file_exists('../config/config.php') ? 'YES' : 'NO') . "</p>";
echo "<p>config/database.php exists: " . (file_exists('../config/database.php') ? 'YES' : 'NO') . "</p>";

// Test basic includes without authentication
echo "<h2>Testing Includes (No Auth)</h2>";
try {
    if (file_exists('../config/config.php')) {
        require_once '../config/config.php';
        echo "<p>✅ config/config.php loaded</p>";
        
        // Test if functions exist
        echo "<p>isLoggedIn function exists: " . (function_exists('isLoggedIn') ? 'YES' : 'NO') . "</p>";
        echo "<p>hasRole function exists: " . (function_exists('hasRole') ? 'YES' : 'NO') . "</p>";
        echo "<p>requireRole function exists: " . (function_exists('requireRole') ? 'YES' : 'NO') . "</p>";
        
        // Test isLoggedIn
        if (function_exists('isLoggedIn')) {
            echo "<p>isLoggedIn result: " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading config: " . $e->getMessage() . "</p>";
}

try {
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        echo "<p>✅ config/database.php loaded</p>";
        
        // Test database connection
        try {
            $database = new Database();
            echo "<p>✅ Database class instantiated</p>";
        } catch (Exception $e) {
            echo "<p>❌ Error creating database: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading database: " . $e->getMessage() . "</p>";
}

echo "<h2>Links</h2>";
echo "<p><a href='../dashboard'>Dashboard</a></p>";
echo "<p><a href='../login'>Login</a></p>";
echo "<p><a href='../test-hostinger'>Test Hostinger</a></p>";
echo "<p><a href='minimal-test.php'>Minimal Test (Direct)</a></p>";
?>
