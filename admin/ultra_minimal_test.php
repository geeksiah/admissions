<?php
echo "Step 1: Basic PHP working\n";
echo "PHP Version: " . phpversion() . "\n";

echo "Step 2: Testing session\n";
session_start();
echo "Session started\n";

echo "Step 3: Testing basic config load\n";
$rootPath = dirname(__DIR__);
echo "Root path: $rootPath\n";

echo "Step 4: Testing config file existence\n";
$configPath = $rootPath . '/config/config_php84.php';
echo "Config path: $configPath\n";
echo "File exists: " . (file_exists($configPath) ? 'YES' : 'NO') . "\n";

echo "Step 5: Testing config file include\n";
try {
    include $configPath;
    echo "Config included successfully\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Step 6: Testing database file\n";
$dbPath = $rootPath . '/config/database.php';
echo "Database path: $dbPath\n";
echo "File exists: " . (file_exists($dbPath) ? 'YES' : 'NO') . "\n";

echo "Step 7: Testing database include\n";
try {
    include $dbPath;
    echo "Database class included successfully\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Step 8: Testing database instantiation\n";
try {
    $database = new Database();
    echo "Database object created successfully\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Step 9: Testing database connection\n";
try {
    $pdo = $database->getConnection();
    echo "Database connection successful\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "ALL TESTS COMPLETED\n";
?>
