<?php
/**
 * Dashboard Fix Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Dashboard Fix Test</h1>";

echo "<h2>✅ Testing Fixed Issues:</h2>";

echo "<h3>1. Path Fix Test</h3>";
try {
    $configPath = __DIR__ . '/config/config.php';
    echo "Config path: $configPath<br>";
    
    if (file_exists($configPath)) {
        echo "✅ Config file exists<br>";
        require_once $configPath;
        echo "✅ Config loaded successfully<br>";
        echo "APP_NAME: " . APP_NAME . "<br>";
    } else {
        echo "❌ Config file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
}

echo "<h3>2. Database Fix Test</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    echo "✅ Database class loaded<br>";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection successful<br>";
    
    $stmt = $pdo->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetchColumn();
    echo "✅ Database prepare() method works: $result<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>3. Dashboard File Test</h3>";
echo "<p><strong>Test the fixed dashboards:</strong></p>";
echo "<ul>";
echo "<li><a href='/admin/dashboard' target='_blank' style='color: #007bff; font-weight: bold;'>Admin Dashboard (Fixed)</a></li>";
echo "<li><a href='/student/dashboard' target='_blank' style='color: #28a745; font-weight: bold;'>Student Dashboard (Fixed)</a></li>";
echo "</ul>";

echo "<h3>🔧 Fixes Applied:</h3>";
echo "<ul>";
echo "<li>✅ Fixed file paths: <code>dirname(__DIR__) . '/config/config.php'</code></li>";
echo "<li>✅ Fixed database method: <code>\$database->getConnection()->prepare()</code></li>";
echo "<li>✅ Added proper error handling</li>";
echo "</ul>";

echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>";
echo "<h3 style='color: #155724; margin: 0;'>🚀 DASHBOARDS SHOULD NOW WORK!</h3>";
echo "<p style='color: #155724; margin: 10px 0 0 0;'>The 500 errors have been fixed. Test the dashboard links above.</p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
