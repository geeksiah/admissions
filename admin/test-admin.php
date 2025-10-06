<?php
echo "<h1>Admin Test Page</h1>";
echo "<p>This is a test admin page to check if admin files work.</p>";

// Test basic includes
echo "<h2>Testing Includes</h2>";

// Test config
if (file_exists('../config/config.php')) {
    echo "<p>✅ config/config.php exists</p>";
    try {
        require_once '../config/config.php';
        echo "<p>✅ config/config.php loaded successfully</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error loading config: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ config/config.php not found</p>";
}

// Test database
if (file_exists('../config/database.php')) {
    echo "<p>✅ config/database.php exists</p>";
    try {
        require_once '../config/database.php';
        $database = new Database();
        echo "<p>✅ Database class loaded successfully</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error loading database: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ config/database.php not found</p>";
}

// Test session
session_start();
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session data: " . print_r($_SESSION, true) . "</p>";

echo "<h2>Links</h2>";
echo "<p><a href='applications.php'>Applications (Direct)</a></p>";
echo "<p><a href='students.php'>Students (Direct)</a></p>";
echo "<p><a href='programs.php'>Programs (Direct)</a></p>";
echo "<p><a href='../dashboard'>Dashboard (Clean URL)</a></p>";
?>
