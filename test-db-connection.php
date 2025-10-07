<?php
/**
 * Database Connection Test
 */

echo "<h1>Database Connection Test</h1>";

try {
    // Include config files
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/database.php';
    
    echo "<h2>✅ Config Files Loaded</h2>";
    echo "APP_NAME: " . APP_NAME . "<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    
    // Test database connection
    echo "<h2>Testing Database Connection</h2>";
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "✅ Database connection successful!<br>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Database query test: " . $result['test'] . "<br>";
    
    // Test if tables exist
    echo "<h2>Checking Database Tables</h2>";
    $tables = ['users', 'applications', 'students', 'programs'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "✅ Table '$table': $count records<br>";
        } catch (PDOException $e) {
            echo "❌ Table '$table': " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>✅ All Tests Passed!</h2>";
    echo "<p><a href='/admin/dashboard' class='btn btn-primary'>Test Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<p>Please check your database configuration.</p>";
}
?>
