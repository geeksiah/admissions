<?php
/**
 * Simple Test - Just the basics
 */

// Create logs directory
$logsDir = dirname(__DIR__) . '/logs/';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Simple error logging
$logFile = $logsDir . 'simple_test.txt';
$log = "[" . date('Y-m-d H:i:s') . "] Simple test started\n";

echo "Simple test starting...\n";

// Test 1: Basic PHP
$log .= "[" . date('Y-m-d H:i:s') . "] PHP Version: " . phpversion() . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Test 2: Directories
$rootPath = dirname(__DIR__);
$log .= "[" . date('Y-m-d H:i:s') . "] Root path: $rootPath\n";
echo "Root path: $rootPath\n";

// Test 3: File checks
$files = [
    'config/config.php',
    'config/database.php',
    'models/User.php'
];

foreach ($files as $file) {
    $filePath = $rootPath . '/' . $file;
    $exists = file_exists($filePath);
    $log .= "[" . date('Y-m-d H:i:s') . "] File $file exists: " . ($exists ? 'YES' : 'NO') . "\n";
    echo "File $file exists: " . ($exists ? 'YES' : 'NO') . "\n";
}

// Test 4: Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u279576488_admissions', 'u279576488_lapaz', '7uVV;OEX|');
    $log .= "[" . date('Y-m-d H:i:s') . "] Direct PDO connection: SUCCESS\n";
    echo "Database connection: SUCCESS\n";
} catch (Exception $e) {
    $log .= "[" . date('Y-m-d H:i:s') . "] Database connection: FAILED - " . $e->getMessage() . "\n";
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}

// Write log
file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);

echo "Test completed. Log written to: $logFile\n";
echo "Log size: " . (file_exists($logFile) ? filesize($logFile) : 0) . " bytes\n";
?>
