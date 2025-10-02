<?php
/**
 * Production Diagnostic Script for Hostinger
 * This will help identify specific issues on the live server
 */

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Production Diagnostic Script</h1>\n";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>\n";
echo "<p><strong>Script Path:</strong> " . __FILE__ . "</p>\n";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// Test 1: PHP Environment
echo "<h2>📋 Test 1: PHP Environment</h2>\n";
echo "<p>✅ <strong>PHP Version:</strong> " . phpversion() . "</p>\n";
echo "<p>✅ <strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>\n";
echo "<p>✅ <strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>\n";
echo "<p>✅ <strong>Current Directory:</strong> " . getcwd() . "</p>\n";

// Test 2: File System Check
echo "<h2>📁 Test 2: File System Check</h2>\n";
$requiredFiles = [
    'config/config.php',
    'config/database.php',
    'config/installed.lock',
    'classes/Security.php',
    'models/User.php',
    'index.php',
    'login.php'
];

$requiredDirs = [
    'config',
    'classes',
    'models',
    'admin',
    'student'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ File exists: $file<br>\n";
    } else {
        echo "❌ <span style='color:red;'>Missing file: $file</span><br>\n";
    }
}

foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ Directory exists: $dir<br>\n";
    } else {
        echo "❌ <span style='color:red;'>Missing directory: $dir</span><br>\n";
    }
}

// Test 3: File Loading Test
echo "<h2>🔧 Test 3: File Loading Test</h2>\n";
try {
    if (file_exists('config/config.php')) {
        require_once 'config/config.php';
        echo "✅ config/config.php loaded successfully<br>\n";
        
        // Check if constants are defined
        $constants = ['APP_NAME', 'DB_HOST', 'DB_NAME', 'DB_USER'];
        foreach ($constants as $const) {
            if (defined($const)) {
                echo "✅ Constant '$const' defined<br>\n";
            } else {
                echo "❌ <span style='color:red;'>Constant '$const' not defined</span><br>\n";
            }
        }
    } else {
        echo "❌ <span style='color:red;'>config/config.php not found</span><br>\n";
    }
    
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        echo "✅ config/database.php loaded successfully<br>\n";
    } else {
        echo "❌ <span style='color:red;'>config/database.php not found</span><br>\n";
    }
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Error loading config files: " . $e->getMessage() . "</span><br>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Fatal error loading config files: " . $e->getMessage() . "</span><br>\n";
}

// Test 4: Database Connection Test
echo "<h2>🗄️ Test 4: Database Connection Test</h2>\n";
try {
    if (class_exists('Database')) {
        $database = new Database();
        echo "✅ Database class instantiated<br>\n";
        
        $pdo = $database->getConnection();
        echo "✅ Database connection established<br>\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result['test'] == 1) {
            echo "✅ Database query test successful<br>\n";
        }
        
        // Check if tables exist
        $tables = ['users', 'students', 'applications', 'programs'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo "✅ Table '$table' exists (records: $count)<br>\n";
            } catch (Exception $e) {
                echo "❌ <span style='color:red;'>Table '$table' error: " . $e->getMessage() . "</span><br>\n";
            }
        }
        
    } else {
        echo "❌ <span style='color:red;'>Database class not found</span><br>\n";
    }
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Database connection error: " . $e->getMessage() . "</span><br>\n";
    echo "<p><strong>Common solutions:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Check database credentials in config/database.php</li>\n";
    echo "<li>Ensure database exists in Hostinger control panel</li>\n";
    echo "<li>Verify database user has proper permissions</li>\n";
    echo "<li>Use full database name with username prefix (e.g., u279576488_admissions)</li>\n";
    echo "</ul>\n";
} catch (Error $e) {
    echo "❌ <span style='color:red;'>Fatal database error: " . $e->getMessage() . "</span><br>\n";
}

// Test 5: Class Loading Test
echo "<h2>🏗️ Test 5: Class Loading Test</h2>\n";
$classes = [
    'classes/Security.php' => 'Security',
    'models/User.php' => 'User',
    'models/Student.php' => 'Student',
    'models/Application.php' => 'Application'
];

foreach ($classes as $file => $className) {
    try {
        if (file_exists($file)) {
            require_once $file;
            if (class_exists($className)) {
                echo "✅ Class '$className' loaded from $file<br>\n";
            } else {
                echo "❌ <span style='color:red;'>Class '$className' not found in $file</span><br>\n";
            }
        } else {
            echo "❌ <span style='color:red;'>File $file not found</span><br>\n";
        }
    } catch (Exception $e) {
        echo "❌ <span style='color:red;'>Error loading $file: " . $e->getMessage() . "</span><br>\n";
    } catch (Error $e) {
        echo "❌ <span style='color:red;'>Fatal error loading $file: " . $e->getMessage() . "</span><br>\n";
    }
}

// Test 6: Session Test
echo "<h2>🔐 Test 6: Session Test</h2>\n";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started successfully<br>\n";
    echo "Session ID: " . session_id() . "<br>\n";
    echo "Session save path: " . session_save_path() . "<br>\n";
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Session error: " . $e->getMessage() . "</span><br>\n";
}

// Test 7: Permissions Test
echo "<h2>🔒 Test 7: Permissions Test</h2>\n";
$dirs = ['config', 'uploads', 'logs'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_readable($dir)) {
            echo "✅ Directory '$dir' is readable<br>\n";
        } else {
            echo "❌ <span style='color:red;'>Directory '$dir' is not readable</span><br>\n";
        }
        
        if (is_writable($dir)) {
            echo "✅ Directory '$dir' is writable<br>\n";
        } else {
            echo "❌ <span style='color:orange;'>Directory '$dir' is not writable (may need 755 permissions)</span><br>\n";
        }
    }
}

// Test 8: Memory and Limits
echo "<h2>⚙️ Test 8: PHP Settings</h2>\n";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds<br>\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>\n";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>\n";
echo "Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "<br>\n";

echo "<h2>🎯 Summary</h2>\n";
echo "<p>If all tests show ✅, the system should work correctly.</p>\n";
echo "<p>If you see ❌ errors, those need to be fixed first.</p>\n";
echo "<p><strong>⚠️ Important:</strong> Delete this file after use for security!</p>\n";

echo "<hr>\n";
echo "<p><small>Generated: " . date('Y-m-d H:i:s') . " | Server: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "</small></p>\n";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.success { color: green; font-weight: bold; }
</style>
