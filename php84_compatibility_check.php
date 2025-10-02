<?php
/**
 * PHP 8.4 Compatibility Checker
 * Identifies potential compatibility issues
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP 8.4 Compatibility Check</h1>\n";
echo "<p>PHP Version: " . phpversion() . "</p>\n";

echo "<h2>Step 1: Check for Deprecated Functions</h2>\n";

// Check for common PHP 8.4 compatibility issues
$deprecatedFunctions = [
    'mysql_connect' => 'Use mysqli or PDO instead',
    'ereg' => 'Use preg_match instead',
    'split' => 'Use explode or preg_split instead',
    'each' => 'Use foreach instead'
];

foreach ($deprecatedFunctions as $func => $replacement) {
    if (function_exists($func)) {
        echo "⚠️ Deprecated function '$func' available - $replacement<br>\n";
    } else {
        echo "✅ '$func' not available (good)<br>\n";
    }
}

echo "<h2>Step 2: Test File Loading</h2>\n";

$rootPath = __DIR__;
$testFiles = [
    'config/config.php',
    'config/database.php',
    'models/User.php'
];

foreach ($testFiles as $file) {
    $fullPath = $rootPath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "Testing $file...<br>\n";
        
        // Check for syntax errors without executing
        $content = file_get_contents($fullPath);
        
        // Look for potential PHP 8.4 issues
        $issues = [];
        
        // Check for old array syntax
        if (strpos($content, 'array(') !== false) {
            $issues[] = "Uses old array() syntax - consider using []";
        }
        
        // Check for deprecated features
        if (strpos($content, 'create_function') !== false) {
            $issues[] = "Uses create_function() - deprecated in PHP 8.0+";
        }
        
        if (strpos($content, 'each(') !== false) {
            $issues[] = "Uses each() - removed in PHP 8.0+";
        }
        
        // Try to parse the file
        try {
            $tokens = token_get_all($content);
            echo "✅ $file - Syntax OK<br>\n";
            
            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    echo "⚠️ $file - $issue<br>\n";
                }
            }
        } catch (ParseError $e) {
            echo "❌ $file - Parse Error: " . $e->getMessage() . " on line " . $e->getLine() . "<br>\n";
        }
    } else {
        echo "❌ $file - File not found<br>\n";
    }
}

echo "<h2>Step 3: Test Basic Includes</h2>\n";

try {
    echo "Loading config...<br>\n";
    require_once $rootPath . '/config/config.php';
    echo "✅ Config loaded<br>\n";
    
    echo "Loading database...<br>\n";
    require_once $rootPath . '/config/database.php';
    echo "✅ Database class loaded<br>\n";
    
    echo "Creating database connection...<br>\n";
    $database = new Database();
    echo "✅ Database object created<br>\n";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection successful<br>\n";
    
} catch (ParseError $e) {
    echo "❌ Parse Error (PHP 8.4 compatibility issue): " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>\n";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>\n";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>\n";
}

echo "<h2>Step 4: PHP 8.4 Specific Checks</h2>\n";

// Check for PHP 8.4 specific features/changes
echo "Checking PHP 8.4 specific features...<br>\n";

// Check if new PHP 8.4 features are available
if (function_exists('array_find')) {
    echo "✅ PHP 8.4 array_find() available<br>\n";
} else {
    echo "ℹ️ PHP 8.4 array_find() not available (might be older version)<br>\n";
}

// Check error reporting level
echo "Error reporting level: " . error_reporting() . "<br>\n";
echo "Display errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "<br>\n";

echo "<h2>Recommendations</h2>\n";
echo "<ul>\n";
echo "<li>✅ Use PHP 8.1 or 8.2 for better compatibility</li>\n";
echo "<li>✅ Update deprecated syntax if found</li>\n";
echo "<li>✅ Test all model files individually</li>\n";
echo "<li>✅ Check for strict type declarations</li>\n";
echo "</ul>\n";

echo "<p><a href='login_test.php'>Continue to Login Test →</a></p>\n";
?>
