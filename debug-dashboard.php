<?php
/**
 * Dashboard Debug - Find the 500 Error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Dashboard Debug - Finding 500 Error</h1>";

echo "<h2>Step 1: Basic Test</h2>";
echo "✅ PHP Working<br>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>Step 2: Config Test</h2>";
try {
    require_once 'config/config.php';
    echo "✅ Config loaded<br>";
    echo "APP_NAME: " . APP_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>Step 3: Database Test</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    echo "✅ Database class loaded<br>";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>Step 4: Session Test</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started<br>";
    
    // Set test session data
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
    $_SESSION['email'] = 'admin@test.com';
    $_SESSION['role'] = 'admin';
    
    echo "✅ Session data set<br>";
} catch (Exception $e) {
    echo "❌ Session error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>Step 5: Dashboard File Test</h2>";
try {
    // Test if the dashboard file exists and is readable
    $dashboardFile = 'admin/dashboard-working.php';
    if (file_exists($dashboardFile)) {
        echo "✅ Dashboard file exists<br>";
        
        // Test basic PHP syntax
        $content = file_get_contents($dashboardFile);
        if (strpos($content, '<?php') !== false) {
            echo "✅ Dashboard file has PHP content<br>";
        }
        
        // Try to include it (this will show any errors)
        echo "<h3>Including dashboard file...</h3>";
        ob_start();
        include $dashboardFile;
        $output = ob_get_clean();
        echo "✅ Dashboard included successfully<br>";
        
    } else {
        echo "❌ Dashboard file not found: $dashboardFile<br>";
    }
} catch (ParseError $e) {
    echo "❌ Parse error: " . $e->getMessage() . " on line " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal error: " . $e->getMessage() . " on line " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<h2>Step 6: Direct Database Queries Test</h2>";
try {
    $stmt = $database->prepare("SELECT COUNT(*) FROM applications");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✅ Applications table query successful: $count records<br>";
} catch (Exception $e) {
    echo "❌ Applications query error: " . $e->getMessage() . "<br>";
}

try {
    $stmt = $database->prepare("SELECT COUNT(*) FROM students");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✅ Students table query successful: $count records<br>";
} catch (Exception $e) {
    echo "❌ Students query error: " . $e->getMessage() . "<br>";
}

try {
    $stmt = $database->prepare("SELECT COUNT(*) FROM programs");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✅ Programs table query successful: $count records<br>";
} catch (Exception $e) {
    echo "❌ Programs query error: " . $e->getMessage() . "<br>";
}

echo "<h2>🎯 Test Results</h2>";
echo "<p>If all steps above show ✅, then the issue might be:</p>";
echo "<ul>";
echo "<li>Server configuration issue</li>";
echo "<li>File permissions</li>";
echo "<li>Memory limit</li>";
echo "<li>Timeout issues</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
