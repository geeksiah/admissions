<?php
echo "<h1>Config Test</h1>";

// Test 1: Basic PHP
echo "<h2>✅ Basic PHP Test</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: File existence
echo "<h2>File Existence Test</h2>";
$configPath = __DIR__ . '/config/config.php';
$dbPath = __DIR__ . '/config/database.php';

echo "Config file exists: " . (file_exists($configPath) ? "✅ Yes" : "❌ No") . "<br>";
echo "Database file exists: " . (file_exists($dbPath) ? "✅ Yes" : "❌ No") . "<br>";

// Test 3: Include config
echo "<h2>Config Include Test</h2>";
try {
    if (file_exists($configPath)) {
        require_once $configPath;
        echo "✅ Config included successfully<br>";
        echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'Not defined') . "<br>";
    } else {
        echo "❌ Config file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
}

// Test 4: Database class
echo "<h2>Database Class Test</h2>";
try {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        echo "✅ Database file included<br>";
        
        if (class_exists('Database')) {
            echo "✅ Database class exists<br>";
            
            try {
                $db = new Database();
                echo "✅ Database instance created<br>";
                
                $pdo = $db->getConnection();
                echo "✅ Database connection successful<br>";
                
                $stmt = $pdo->query("SELECT 1 as test");
                $result = $stmt->fetch();
                echo "✅ Database query test: " . $result['test'] . "<br>";
                
            } catch (Exception $e) {
                echo "❌ Database connection error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ Database class not found<br>";
        }
    } else {
        echo "❌ Database file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";
echo "<p><a href='/admin/dashboard'>Test Dashboard</a></p>";
?>
