<?php
echo "Step 1: Basic PHP\n";
echo "PHP Version: " . phpversion() . "\n";

echo "\nStep 2: Session\n";
session_start();
echo "Session started\n";

echo "\nStep 3: Directory paths\n";
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

echo "\nStep 4: Check config file exists\n";
$configFile = $rootPath . '/config/config_php84.php';
echo "Config file path: $configFile\n";
echo "File exists: " . (file_exists($configFile) ? 'YES' : 'NO') . "\n";

echo "\nStep 5: Try to include config\n";
if (file_exists($configFile)) {
    echo "Including config file...\n";
    include_once $configFile;
    echo "Config included successfully\n";
    echo "APP_NAME defined: " . (defined('APP_NAME') ? APP_NAME : 'NO') . "\n";
} else {
    echo "Config file not found!\n";
}

echo "\nStep 6: Check database file\n";
$dbFile = $rootPath . '/config/database.php';
echo "Database file path: $dbFile\n";
echo "File exists: " . (file_exists($dbFile) ? 'YES' : 'NO') . "\n";

echo "\nStep 7: Try to include database\n";
if (file_exists($dbFile)) {
    echo "Including database file...\n";
    include_once $dbFile;
    echo "Database class included\n";
} else {
    echo "Database file not found!\n";
}

echo "\nStep 8: Try to create database object\n";
if (class_exists('Database')) {
    echo "Creating Database object...\n";
    try {
        $database = new Database();
        echo "Database object created\n";
        
        echo "Getting connection...\n";
        $pdo = $database->getConnection();
        echo "Database connection successful\n";
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database class not available\n";
}

echo "\nALL STEPS COMPLETED\n";
?>
