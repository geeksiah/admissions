<?php
/**
 * Comprehensive System Test
 * This will test every component of the system
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Comprehensive System Test</h1>";
echo "<p>Testing all system components...</p>";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Basic PHP
echo "<h2>1. Basic PHP Test</h2>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    $success[] = "PHP Version: " . PHP_VERSION;
} else {
    $errors[] = "PHP Version too old: " . PHP_VERSION;
}

// Test 2: Required Extensions
echo "<h2>2. PHP Extensions Test</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'gd', 'curl', 'zip', 'openssl', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "Extension $ext: Loaded";
    } else {
        $errors[] = "Extension $ext: Missing";
    }
}

// Test 3: Configuration Files
echo "<h2>3. Configuration Files Test</h2>";
$configFiles = [
    'config/config.php',
    'config/database.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        try {
            require_once $file;
            $success[] = "Config file $file: Loaded successfully";
        } catch (Exception $e) {
            $errors[] = "Config file $file: Error - " . $e->getMessage();
        }
    } else {
        $errors[] = "Config file $file: Missing";
    }
}

// Test 4: Database Connection
echo "<h2>4. Database Connection Test</h2>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $success[] = "Database connection: Successful";
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $success[] = "Database query: Successful (Users: " . $result['count'] . ")";
} catch (Exception $e) {
    $errors[] = "Database connection: Failed - " . $e->getMessage();
}

// Test 5: Core Classes
echo "<h2>5. Core Classes Test</h2>";
$coreClasses = [
    'classes/Security.php' => 'Security',
    'classes/Validator.php' => 'Validator',
    'classes/FileUpload.php' => 'FileUpload'
];

foreach ($coreClasses as $file => $class) {
    if (file_exists($file)) {
        try {
            require_once $file;
            if (class_exists($class)) {
                $success[] = "Class $class: Loaded successfully";
            } else {
                $errors[] = "Class $class: Not found in $file";
            }
        } catch (Exception $e) {
            $errors[] = "Class $class: Error - " . $e->getMessage();
        }
    } else {
        $errors[] = "Class file $file: Missing";
    }
}

// Test 6: Model Classes
echo "<h2>6. Model Classes Test</h2>";
$modelClasses = [
    'models/User.php' => 'User',
    'models/Student.php' => 'Student',
    'models/Application.php' => 'Application',
    'models/Program.php' => 'Program',
    'models/Payment.php' => 'Payment',
    'models/Report.php' => 'Report'
];

foreach ($modelClasses as $file => $class) {
    if (file_exists($file)) {
        try {
            require_once $file;
            if (class_exists($class)) {
                // Test instantiation
                if (isset($pdo)) {
                    $instance = new $class($pdo);
                    $success[] = "Model $class: Loaded and instantiated successfully";
                } else {
                    $warnings[] = "Model $class: Loaded but cannot test instantiation (no DB connection)";
                }
            } else {
                $errors[] = "Model $class: Not found in $file";
            }
        } catch (Exception $e) {
            $errors[] = "Model $class: Error - " . $e->getMessage();
        }
    } else {
        $errors[] = "Model file $file: Missing";
    }
}

// Test 7: Admin Pages
echo "<h2>7. Admin Pages Test</h2>";
$adminPages = [
    'admin/dashboard.php',
    'admin/students.php',
    'admin/programs.php',
    'admin/applications.php'
];

foreach ($adminPages as $page) {
    if (file_exists($page)) {
        $success[] = "Admin page $page: Exists";
    } else {
        $errors[] = "Admin page $page: Missing";
    }
}

// Test 8: Include Files
echo "<h2>8. Include Files Test</h2>";
$includeFiles = [
    'includes/header.php',
    'includes/footer.php'
];

foreach ($includeFiles as $file) {
    if (file_exists($file)) {
        $success[] = "Include file $file: Exists";
    } else {
        $errors[] = "Include file $file: Missing";
    }
}

// Test 9: Directories
echo "<h2>9. Required Directories Test</h2>";
$directories = [
    'uploads',
    'uploads/applications',
    'uploads/documents',
    'uploads/receipts',
    'uploads/temp',
    'backups',
    'logs',
    'cache'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            $success[] = "Directory $dir: Exists and writable";
        } else {
            $warnings[] = "Directory $dir: Exists but not writable";
        }
    } else {
        $errors[] = "Directory $dir: Missing";
    }
}

// Test 10: Session Test
echo "<h2>10. Session Test</h2>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    $success[] = "Session: Active";
} else {
    $errors[] = "Session: Not active";
}

// Results Summary
echo "<h2>Test Results Summary</h2>";

if (!empty($success)) {
    echo "<h3 style='color: green;'>✅ Successes (" . count($success) . ")</h3>";
    echo "<ul>";
    foreach ($success as $item) {
        echo "<li style='color: green;'>$item</li>";
    }
    echo "</ul>";
}

if (!empty($warnings)) {
    echo "<h3 style='color: orange;'>⚠️ Warnings (" . count($warnings) . ")</h3>";
    echo "<ul>";
    foreach ($warnings as $item) {
        echo "<li style='color: orange;'>$item</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 style='color: red;'>❌ Errors (" . count($errors) . ")</h3>";
    echo "<ul>";
    foreach ($errors as $item) {
        echo "<li style='color: red;'>$item</li>";
    }
    echo "</ul>";
}

// Overall Status
$totalTests = count($success) + count($warnings) + count($errors);
$successRate = round((count($success) / $totalTests) * 100, 2);

echo "<h2>Overall System Status</h2>";
if (empty($errors)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<h3>🎉 System Status: EXCELLENT</h3>";
    echo "<p>Success Rate: $successRate% ($totalTests tests)</p>";
    echo "<p>All critical components are working properly!</p>";
    echo "</div>";
} elseif (count($errors) <= 3) {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
    echo "<h3>⚠️ System Status: GOOD</h3>";
    echo "<p>Success Rate: $successRate% ($totalTests tests)</p>";
    echo "<p>Minor issues detected. System should work with some limitations.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ System Status: NEEDS ATTENTION</h3>";
    echo "<p>Success Rate: $successRate% ($totalTests tests)</p>";
    echo "<p>Multiple critical issues detected. System may not function properly.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Fix any errors listed above</li>";
echo "<li>Address warnings if possible</li>";
echo "<li>Test the login and dashboard functionality</li>";
echo "<li>Delete this test file after completion</li>";
echo "</ol>";
?>
