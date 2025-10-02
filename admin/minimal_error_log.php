<?php
/**
 * Minimal Error Log - Just log everything to a simple file
 */

// Set error handler to log to file
function logError($errno, $errstr, $errfile, $errline) {
    $logFile = dirname(__DIR__) . '/logs/minimal_errors.txt';
    $error = "[" . date('Y-m-d H:i:s') . "] Error $errno: $errstr in $errfile on line $errline\n";
    file_put_contents($logFile, $error, FILE_APPEND | LOCK_EX);
    return true;
}

set_error_handler('logError');

// Create logs directory
$logsDir = dirname(__DIR__) . '/logs/';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Log that we started
$logFile = $logsDir . 'minimal_errors.txt';
$startLog = "[" . date('Y-m-d H:i:s') . "] Minimal error log started\n";
file_put_contents($logFile, $startLog, FILE_APPEND | LOCK_EX);

echo "Minimal error logging active...\n";

// Now try to do something that might cause an error
$rootPath = dirname(__DIR__);

// Try to include config
$configPath = $rootPath . '/config/config.php';
echo "Attempting to include config: $configPath\n";

if (file_exists($configPath)) {
    echo "Config file exists, attempting to include...\n";
    include $configPath;
    echo "Config included successfully\n";
} else {
    echo "Config file not found\n";
}

// Try to include database
$dbPath = $rootPath . '/config/database.php';
echo "Attempting to include database: $dbPath\n";

if (file_exists($dbPath)) {
    echo "Database file exists, attempting to include...\n";
    include $dbPath;
    echo "Database class included\n";
    
    if (class_exists('Database')) {
        echo "Database class exists, attempting to instantiate...\n";
        $database = new Database();
        echo "Database object created\n";
    } else {
        echo "Database class not found\n";
    }
} else {
    echo "Database file not found\n";
}

echo "Minimal error log completed\n";
echo "Check log file: $logFile\n";
?>
