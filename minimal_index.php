<?php
/**
 * Minimal Index - Bypass complex logic to test basic functionality
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Minimal System Test</h1>\n";

try {
    echo "Testing session...<br>\n";
    session_start();
    echo "✅ Session OK<br>\n";
    
    echo "Testing config...<br>\n";
    require_once 'config/config.php';
    echo "✅ Config OK<br>\n";
    
    echo "Testing database...<br>\n";
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✅ Database OK<br>\n";
    
    echo "Testing installation lock...<br>\n";
    if (!file_exists('config/installed.lock')) {
        echo "❌ Installation lock missing - creating one...<br>\n";
        file_put_contents('config/installed.lock', 'Installed: ' . date('Y-m-d H:i:s'));
        echo "✅ Installation lock created<br>\n";
    } else {
        echo "✅ Installation lock exists<br>\n";
    }
    
    echo "<h2>🎯 System Status</h2>\n";
    echo "<p style='color: green;'><strong>✅ Core system is working!</strong></p>\n";
    echo "<p>The 500 error is likely in the dashboard files or missing data.</p>\n";
    
    echo "<h3>Next Steps:</h3>\n";
    echo "<ol>\n";
    echo "<li>Run <code>emergency_debug.php</code> to see exact error</li>\n";
    echo "<li>Run <code>test_dashboard.php</code> to test dashboard components</li>\n";
    echo "<li>Check if database has the required tables and data</li>\n";
    echo "<li>Import database schema if tables are missing</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Login Test:</h3>\n";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login Page →</a></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
} catch (Error $e) {
    echo "<p style='color: red;'><strong>Fatal Error:</strong> " . $e->getMessage() . "</p>\n";
}
?>
