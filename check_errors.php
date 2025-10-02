<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP is working<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error reporting enabled<br>";

// Check if we can write to files
if (is_writable('.')) {
    echo "Directory is writable<br>";
} else {
    echo "Directory is NOT writable<br>";
}

// Check memory limit
echo "Memory limit: " . ini_get('memory_limit') . "<br>";

// Check if we can start session
try {
    session_start();
    echo "Session started successfully<br>";
} catch (Exception $e) {
    echo "Session error: " . $e->getMessage() . "<br>";
}

echo "Test complete";
?>
