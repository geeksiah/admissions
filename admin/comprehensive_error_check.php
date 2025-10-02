<?php
/**
 * Comprehensive Error Check - Find ALL potential issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Comprehensive Error Check</h1>\n";

// Test 1: Basic PHP functionality
echo "<h2>1. Basic PHP Test</h2>\n";
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Current directory: " . __DIR__ . "\n";
echo "✅ Parent directory: " . dirname(__DIR__) . "\n";

// Test 2: Session functionality
echo "<h2>2. Session Test</h2>\n";
session_start();
echo "✅ Session started\n";
echo "✅ Session ID: " . session_id() . "\n";

// Test 3: Directory structure check
echo "<h2>3. Directory Structure Check</h2>\n";
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

$requiredDirs = ['config', 'models', 'classes', 'includes', 'admin', 'student'];
foreach ($requiredDirs as $dir) {
    $dirPath = $rootPath . '/' . $dir;
    $exists = file_exists($dirPath);
    $writable = is_writable($dirPath);
    echo ($exists ? '✅' : '❌') . " $dir exists: " . ($exists ? 'YES' : 'NO') . "\n";
    if ($exists) {
        echo ($writable ? '✅' : '⚠️') . " $dir writable: " . ($writable ? 'YES' : 'NO') . "\n";
    }
}

// Test 4: Critical files check
echo "<h2>4. Critical Files Check</h2>\n";
$criticalFiles = [
    'config/config.php',
    'config/database.php',
    'config/installed.lock',
    'models/User.php',
    'models/Student.php',
    'models/Application.php',
    'models/Program.php',
    'models/Payment.php',
    'models/Report.php',
    'classes/Security.php'
];

foreach ($criticalFiles as $file) {
    $filePath = $rootPath . '/' . $file;
    $exists = file_exists($filePath);
    $readable = is_readable($filePath);
    echo ($exists ? '✅' : '❌') . " $file exists: " . ($exists ? 'YES' : 'NO') . "\n";
    if ($exists) {
        echo ($readable ? '✅' : '❌') . " $file readable: " . ($readable ? 'YES' : 'NO') . "\n";
        
        // Check for syntax errors
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            $output = shell_exec("php -l " . escapeshellarg($filePath) . " 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                echo "✅ $file syntax: OK\n";
            } else {
                echo "❌ $file syntax: ERROR\n";
                echo "Error: " . htmlspecialchars($output) . "\n";
            }
        }
    }
}

// Test 5: Database connection
echo "<h2>5. Database Connection Test</h2>\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u279576488_admissions', 'u279576488_lapaz', '7uVV;OEX|');
    echo "✅ Direct PDO connection: SUCCESS\n";
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Database query test: " . $result['count'] . " users found\n";
    
} catch (Exception $e) {
    echo "❌ Database connection: FAILED\n";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "\n";
}

// Test 6: Config file loading
echo "<h2>6. Config File Loading Test</h2>\n";
$configPath = $rootPath . '/config/config.php';
if (file_exists($configPath)) {
    try {
        ob_start();
        include $configPath;
        $output = ob_get_clean();
        
        if (defined('APP_NAME')) {
            echo "✅ Config loaded: " . APP_NAME . "\n";
        } else {
            echo "❌ Config loaded but APP_NAME not defined\n";
        }
        
        if (!empty($output)) {
            echo "⚠️ Config output: " . htmlspecialchars($output) . "\n";
        }
        
    } catch (Error $e) {
        echo "❌ Config loading error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    } catch (Exception $e) {
        echo "❌ Config loading exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Config file not found\n";
}

// Test 7: Database class loading
echo "<h2>7. Database Class Loading Test</h2>\n";
$dbPath = $rootPath . '/config/database.php';
if (file_exists($dbPath)) {
    try {
        include $dbPath;
        
        if (class_exists('Database')) {
            echo "✅ Database class loaded\n";
            
            // Test Database instantiation
            try {
                $database = new Database();
                echo "✅ Database object created\n";
                
                $pdo = $database->getConnection();
                echo "✅ Database connection obtained\n";
                
            } catch (Exception $e) {
                echo "❌ Database instantiation error: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "❌ Database class not found\n";
        }
        
    } catch (Error $e) {
        echo "❌ Database class loading error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    }
} else {
    echo "❌ Database file not found\n";
}

// Test 8: Model loading test
echo "<h2>8. Model Loading Test</h2>\n";
$models = ['User', 'Student', 'Application', 'Program', 'Payment', 'Report'];

foreach ($models as $model) {
    $modelPath = $rootPath . '/models/' . $model . '.php';
    
    if (file_exists($modelPath)) {
        try {
            include $modelPath;
            
            if (class_exists($model)) {
                echo "✅ $model class loaded\n";
                
                // Test instantiation if we have a database connection
                if (isset($pdo)) {
                    try {
                        $instance = new $model($pdo);
                        echo "✅ $model instantiated\n";
                    } catch (Exception $e) {
                        echo "❌ $model instantiation error: " . $e->getMessage() . "\n";
                    }
                }
                
            } else {
                echo "❌ $model class not found after loading\n";
            }
            
        } catch (Error $e) {
            echo "❌ $model loading error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        } catch (Exception $e) {
            echo "❌ $model loading exception: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ $model file not found\n";
    }
}

// Test 9: Header file test
echo "<h2>9. Header File Test</h2>\n";
$headerPath = $rootPath . '/includes/header.php';
if (file_exists($headerPath)) {
    try {
        // Create test session data
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['first_name'] = 'Test';
        $_SESSION['last_name'] = 'Admin';
        $_SESSION['username'] = 'testadmin';
        $_SESSION['email'] = 'test@example.com';
        
        ob_start();
        include $headerPath;
        $output = ob_get_clean();
        
        if (strpos($output, '<!DOCTYPE html>') !== false) {
            echo "✅ Header file loads successfully\n";
        } else {
            echo "⚠️ Header file loads but may have issues\n";
        }
        
        if (!empty($output) && strlen($output) > 100) {
            echo "✅ Header output length: " . strlen($output) . " characters\n";
        }
        
    } catch (Error $e) {
        echo "❌ Header loading error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    } catch (Exception $e) {
        echo "❌ Header loading exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Header file not found\n";
}

// Test 10: PHP 8.4 specific checks
echo "<h2>10. PHP 8.4 Compatibility Check</h2>\n";

// Check for deprecated functions
$deprecatedFunctions = ['mysql_connect', 'mysql_query', 'ereg', 'split', 'each'];
foreach ($deprecatedFunctions as $func) {
    if (function_exists($func)) {
        echo "⚠️ Deprecated function $func is available\n";
    } else {
        echo "✅ Deprecated function $func not available (good)\n";
    }
}

// Check for PHP 8.4 specific issues
if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
    echo "⚠️ Running PHP 8.4 - this may cause compatibility issues\n";
    echo "Recommendation: Switch to PHP 8.1 or 8.2\n";
} else {
    echo "✅ PHP version is compatible\n";
}

// Test 11: Memory and execution limits
echo "<h2>11. System Limits Check</h2>\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";

// Test 12: Error reporting
echo "<h2>12. Error Reporting Check</h2>\n";
echo "Error reporting level: " . ini_get('error_reporting') . "\n";
echo "Display errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
echo "Log errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "\n";

echo "<h2>🎯 Summary</h2>\n";
echo "<p>This comprehensive check should reveal any issues preventing your dashboard from loading.</p>\n";
echo "<p>Look for ❌ errors and ⚠️ warnings above.</p>\n";

echo "<h3>Next Steps:</h3>\n";
echo "<ul>\n";
echo "<li>Fix any ❌ errors found above</li>\n";
echo "<li>Address any ⚠️ warnings</li>\n";
echo "<li>Consider switching from PHP 8.4 to PHP 8.1 or 8.2</li>\n";
echo "<li>Check file permissions if files are not readable</li>\n";
echo "</ul>\n";
?>
