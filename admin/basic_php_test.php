<?php
echo "BASIC PHP TEST\n";
echo "==============\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current directory: " . __DIR__ . "\n";
echo "Parent directory: " . dirname(__DIR__) . "\n";

echo "\nTesting session:\n";
session_start();
echo "Session started\n";
echo "Session ID: " . session_id() . "\n";

echo "\nTesting basic database connection:\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u279576488_admissions', 'u279576488_lapaz', '7uVV;OEX|');
    echo "Direct PDO connection: SUCCESS\n";
} catch (Exception $e) {
    echo "Direct PDO connection: FAILED - " . $e->getMessage() . "\n";
}

echo "\nTesting file paths:\n";
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

$files = [
    'config/config_php84.php',
    'config/database.php',
    'models/User.php',
    'models/Student.php'
];

foreach ($files as $file) {
    $fullPath = $rootPath . '/' . $file;
    echo "File: $file - " . (file_exists($fullPath) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\nBASIC TEST COMPLETE\n";
?>
