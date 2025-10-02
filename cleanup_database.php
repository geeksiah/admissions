<?php
/**
 * Database Cleanup Script
 * Use this to clean up existing database tables if needed
 * 
 * WARNING: This will delete all existing data!
 */

// Database configuration - UPDATE THESE VALUES
$db_host = 'localhost';
$db_name = 'u279576488_admissions'; // Your database name
$db_user = 'u279576488_lapaz'; // Your username
$db_pass = 'your_password_here'; // Your password

echo "<h2>Database Cleanup Tool</h2>";
echo "<p><strong>WARNING:</strong> This will delete all existing data!</p>";

// Check if user wants to proceed
if (!isset($_GET['confirm'])) {
    echo "<p>To proceed with cleanup, click the button below:</p>";
    echo "<a href='?confirm=yes' style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>CLEANUP DATABASE</a>";
    exit;
}

if ($_GET['confirm'] !== 'yes') {
    echo "<p>Cleanup cancelled.</p>";
    exit;
}

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";
    
    // Get list of tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Found " . count($tables) . " tables:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Drop all tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "<p style='color: orange;'>🗑️ Dropped table: $table</p>";
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<p style='color: green;'>✅ Database cleanup completed!</p>";
    echo "<p>You can now run the installer again.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database credentials and try again.</p>";
}

echo "<p><strong>Remember to delete this file after use for security!</strong></p>";
?>
