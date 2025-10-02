<?php
/**
 * Emergency Debug Script - Minimal error detection
 * This will show exactly what's causing the 500 error
 */

// Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🚨 Emergency Debug - Dashboard 500 Error</h1>\n";
echo "<p>Testing each component step by step...</p>\n";

echo "<h2>Step 1: Basic PHP Test</h2>\n";
echo "✅ PHP is working - you can see this message<br>\n";
echo "PHP Version: " . phpversion() . "<br>\n";

echo "<h2>Step 2: File Existence Check</h2>\n";
$criticalFiles = [
    'config/config.php',
    'config/database.php', 
    'config/installed.lock',
    'classes/Security.php',
    'classes/Validator.php',
    'models/User.php',
    'admin/dashboard_working.php',
    'student/dashboard.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Found: $file<br>\n";
    } else {
        echo "❌ <span style='color:red;'>MISSING: $file</span><br>\n";
    }
}

echo "<h2>Step 3: Config Loading Test</h2>\n";
try {
    require_once 'config/config.php';
    echo "✅ config.php loaded successfully<br>\n";
    echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'NOT DEFINED') . "<br>\n";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "<br>\n";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "<br>\n";
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Config error: " . $e->getMessage() . "</span><br>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Config fatal error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 4: Database Class Test</h2>\n";
try {
    require_once 'config/database.php';
    echo "✅ database.php loaded<br>\n";
    
    $database = new Database();
    echo "✅ Database class instantiated<br>\n";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection established<br>\n";
    
    // Test query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result && $result['test'] == 1) {
        echo "✅ Database query successful<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Database error: " . $e->getMessage() . "</span><br>\n";
    echo "<strong>This is likely your main issue!</strong><br>\n";
    echo "<strong>Solution:</strong> Check database exists and credentials are correct<br>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Database fatal error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 5: Session Test</h2>\n";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started<br>\n";
    echo "Session ID: " . session_id() . "<br>\n";
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Session error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 6: Security Class Test</h2>\n";
try {
    require_once 'classes/Security.php';
    echo "✅ Security.php loaded<br>\n";
    
    if (isset($database) && $database) {
        $security = new Security($database);
        echo "✅ Security class instantiated<br>\n";
    } else {
        echo "⚠️ Skipping Security test - database not available<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Security error: " . $e->getMessage() . "</span><br>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Security fatal error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 7: Model Class Test</h2>\n";
try {
    require_once 'models/User.php';
    echo "✅ User.php loaded<br>\n";
    
    if (isset($pdo) && $pdo) {
        $userModel = new User($pdo);
        echo "✅ User model instantiated<br>\n";
    } else {
        echo "⚠️ Skipping User model test - database not available<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>User model error: " . $e->getMessage() . "</span><br>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>User model fatal error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 8: Dashboard File Test</h2>\n";
try {
    if (file_exists('admin/dashboard_working.php')) {
        echo "✅ admin/dashboard_working.php exists<br>\n";
        
        // Try to include just the beginning to test for syntax errors
        $content = file_get_contents('admin/dashboard_working.php', false, null, 0, 500);
        if (strpos($content, '<?php') !== false) {
            echo "✅ Dashboard file has PHP opening tag<br>\n";
        } else {
            echo "❌ <span style='color:red;'>Dashboard file doesn't start with PHP tag</span><br>\n";
        }
    } else {
        echo "❌ <span style='color:red;'>admin/dashboard_working.php NOT FOUND</span><br>\n";
    }
    
    if (file_exists('student/dashboard.php')) {
        echo "✅ student/dashboard.php exists<br>\n";
    } else {
        echo "❌ <span style='color:red;'>student/dashboard.php NOT FOUND</span><br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Dashboard test error: " . $e->getMessage() . "</span><br>\n";
}

echo "<h2>Step 9: Index.php Simulation</h2>\n";
echo "<p>Now testing what happens when we try to load index.php...</p>\n";

try {
    // Simulate the index.php logic step by step
    echo "Testing session start...<br>\n";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session OK<br>\n";
    
    echo "Testing config includes...<br>\n";
    // Config already loaded above
    echo "✅ Config OK<br>\n";
    
    echo "Testing class includes...<br>\n";
    if (!class_exists('Validator')) {
        require_once 'classes/Validator.php';
    }
    if (!class_exists('FileUpload')) {
        require_once 'classes/FileUpload.php';
    }
    echo "✅ Classes OK<br>\n";
    
    echo "Testing database initialization...<br>\n";
    if (isset($database) && $pdo) {
        echo "✅ Database already initialized<br>\n";
    } else {
        echo "❌ <span style='color:red;'>Database not initialized - this is your problem!</span><br>\n";
    }
    
    echo "Testing installation lock...<br>\n";
    if (file_exists('config/installed.lock')) {
        echo "✅ Installation lock exists<br>\n";
    } else {
        echo "❌ <span style='color:red;'>Installation lock missing - would redirect to installer</span><br>\n";
    }
    
    echo "Testing session user check...<br>\n";
    if (!isset($_SESSION['user_id'])) {
        echo "ℹ️ No user logged in - would redirect to login.php (this is normal)<br>\n";
    } else {
        echo "ℹ️ User logged in: " . $_SESSION['user_id'] . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Index simulation error: " . $e->getMessage() . "</span><br>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Index simulation fatal error: " . $e->getMessage() . "</span><br>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
}

echo "<h2>🎯 DIAGNOSIS SUMMARY</h2>\n";
echo "<p>Look for any ❌ red errors above. The first red error is usually the cause of your 500 error.</p>\n";

echo "<h3>Most Common Issues:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Database connection failed:</strong> Check if database exists and credentials are correct</li>\n";
echo "<li><strong>Missing files:</strong> Upload all files to Hostinger</li>\n";
echo "<li><strong>File permissions:</strong> Set directories to 755</li>\n";
echo "<li><strong>PHP syntax errors:</strong> Check for missing semicolons, brackets</li>\n";
echo "</ul>\n";

echo "<p><strong>⚠️ DELETE THIS FILE after diagnosis for security!</strong></p>\n";
?>
