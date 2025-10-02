<?php
/**
 * Test File Permissions and Basic Operations
 */

echo "Testing file permissions and basic operations...\n";

// Create logs directory
$logsDir = dirname(__DIR__) . '/logs/';
echo "Logs directory: $logsDir\n";

if (!is_dir($logsDir)) {
    echo "Creating logs directory...\n";
    if (mkdir($logsDir, 0755, true)) {
        echo "Logs directory created successfully\n";
    } else {
        echo "Failed to create logs directory\n";
    }
} else {
    echo "Logs directory already exists\n";
}

// Test if we can write to logs directory
$testFile = $logsDir . 'test_write.txt';
echo "Testing write to: $testFile\n";

if (file_put_contents($testFile, "Test write at " . date('Y-m-d H:i:s') . "\n")) {
    echo "Write test: SUCCESS\n";
} else {
    echo "Write test: FAILED\n";
}

// Test file permissions
echo "Logs directory permissions: " . substr(sprintf('%o', fileperms($logsDir)), -4) . "\n";
echo "Logs directory writable: " . (is_writable($logsDir) ? 'YES' : 'NO') . "\n";

// Test basic file operations
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

// Check if we can read files
$files = [
    'config/config.php',
    'config/database.php',
    'models/User.php'
];

foreach ($files as $file) {
    $filePath = $rootPath . '/' . $file;
    echo "File: $file\n";
    echo "  Exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";
    echo "  Readable: " . (is_readable($filePath) ? 'YES' : 'NO') . "\n";
    echo "  Size: " . (file_exists($filePath) ? filesize($filePath) : 0) . " bytes\n";
}

// Test database connection
echo "Testing database connection...\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u279576488_admissions', 'u279576488_lapaz', '7uVV;OEX|');
    echo "Database connection: SUCCESS\n";
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}

// Test session
echo "Testing session...\n";
session_start();
echo "Session started: " . session_id() . "\n";

echo "File permission test completed\n";
?>
