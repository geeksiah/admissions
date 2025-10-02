<?php
/**
 * Ultra Basic Error Catcher - No Dependencies
 * This will catch errors even if everything else fails
 */

// Enable error logging to a simple file
ini_set('log_errors', 1);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Create a simple error handler that writes to a file
function simpleErrorHandler($severity, $message, $file, $line) {
    $logFile = dirname(__DIR__) . '/logs/basic_errors.txt';
    
    $errorData = "[" . date('Y-m-d H:i:s') . "] ERROR\n";
    $errorData .= "Severity: $severity\n";
    $errorData .= "Message: $message\n";
    $errorData .= "File: $file\n";
    $errorData .= "Line: $line\n";
    $errorData .= "PHP Version: " . phpversion() . "\n";
    $errorData .= "Memory: " . memory_get_usage(true) . " bytes\n";
    $errorData .= "---\n";
    
    file_put_contents($logFile, $errorData, FILE_APPEND | LOCK_EX);
    
    return true; // Don't execute PHP internal error handler
}

// Set the error handler
set_error_handler('simpleErrorHandler');

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs/';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Write initial log entry
$logFile = $logsDir . 'basic_errors.txt';
$initialLog = "[" . date('Y-m-d H:i:s') . "] BASIC ERROR CATCHER STARTED\n";
$initialLog .= "PHP Version: " . phpversion() . "\n";
$initialLog .= "Current Directory: " . __DIR__ . "\n";
$initialLog .= "Parent Directory: " . dirname(__DIR__) . "\n";
$initialLog .= "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
$initialLog .= "---\n";

file_put_contents($logFile, $initialLog, FILE_APPEND | LOCK_EX);

echo "Basic error catcher initialized...\n";

// Test basic functionality
echo "Testing basic operations...\n";

// Test 1: File operations
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

// Test 2: Directory checks
$dirs = ['config', 'models', 'classes', 'includes', 'logs'];
foreach ($dirs as $dir) {
    $dirPath = $rootPath . '/' . $dir;
    $exists = file_exists($dirPath);
    $writable = is_writable($dirPath);
    echo "Directory $dir: exists=" . ($exists ? 'YES' : 'NO') . ", writable=" . ($writable ? 'YES' : 'NO') . "\n";
    
    // Log this to file
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Directory $dir: exists=$exists, writable=$writable\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Test 3: Critical files
$files = [
    'config/config.php',
    'config/database.php',
    'models/User.php',
    'classes/ErrorLogger.php'
];

foreach ($files as $file) {
    $filePath = $rootPath . '/' . $file;
    $exists = file_exists($filePath);
    $readable = is_readable($filePath);
    echo "File $file: exists=" . ($exists ? 'YES' : 'NO') . ", readable=" . ($readable ? 'YES' : 'NO') . "\n";
    
    // Log this to file
    $logEntry = "[" . date('Y-m-d H:i:s') . "] File $file: exists=$exists, readable=$readable\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Test 4: Session
echo "Testing session...\n";
session_start();
echo "Session started successfully\n";

// Test 5: Try to include config
echo "Testing config inclusion...\n";
$configPath = $rootPath . '/config/config.php';
if (file_exists($configPath)) {
    try {
        include $configPath;
        echo "Config included successfully\n";
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Config included successfully\n";
    } catch (Throwable $e) {
        echo "Config inclusion failed: " . $e->getMessage() . "\n";
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Config inclusion failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Config file not found\n";
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Config file not found\n";
}
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Test 6: Try to include database
echo "Testing database inclusion...\n";
$dbPath = $rootPath . '/config/database.php';
if (file_exists($dbPath)) {
    try {
        include $dbPath;
        echo "Database class included\n";
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Database class included\n";
        
        if (class_exists('Database')) {
            echo "Database class exists\n";
            $logEntry = "[" . date('Y-m-d H:i:s') . "] Database class exists\n";
            
            try {
                $database = new Database();
                echo "Database object created\n";
                $logEntry = "[" . date('Y-m-d H:i:s') . "] Database object created\n";
            } catch (Throwable $e) {
                echo "Database object creation failed: " . $e->getMessage() . "\n";
                $logEntry = "[" . date('Y-m-d H:i:s') . "] Database object creation failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Database class not found\n";
            $logEntry = "[" . date('Y-m-d H:i:s') . "] Database class not found\n";
        }
    } catch (Throwable $e) {
        echo "Database inclusion failed: " . $e->getMessage() . "\n";
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Database inclusion failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database file not found\n";
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Database file not found\n";
}
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Final log entry
$finalLog = "[" . date('Y-m-d H:i:s') . "] BASIC ERROR CATCHER COMPLETED\n";
$finalLog .= "Memory Usage: " . memory_get_usage(true) . " bytes\n";
$finalLog .= "Peak Memory: " . memory_get_peak_usage(true) . " bytes\n";
$finalLog .= "========================================\n\n";

file_put_contents($logFile, $finalLog, FILE_APPEND | LOCK_EX);

echo "\nBasic error catcher completed!\n";
echo "Check the log file: " . $logFile . "\n";
echo "Log file size: " . (file_exists($logFile) ? filesize($logFile) : 0) . " bytes\n";
?>
