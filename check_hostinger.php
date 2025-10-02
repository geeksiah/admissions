<?php
/**
 * Hostinger Database Checker
 * This will help identify your exact database configuration
 */

echo "<h2>Hostinger Database Configuration Checker</h2>";

// Try to connect without specifying a database
try {
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'u279576488_lapaz', 'YOUR_PASSWORD_HERE', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p style='color: green;'>✅ Successfully connected to MySQL server!</p>";
    
    // Show all databases
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available Databases:</h3>";
    echo "<ul>";
    foreach ($databases as $db) {
        if (strpos($db, 'u279576488') === 0) {
            echo "<li style='color: blue; font-weight: bold;'>$db (YOUR DATABASE)</li>";
        } else {
            echo "<li>$db</li>";
        }
    }
    echo "</ul>";
    
    // Show current user
    $stmt = $pdo->query("SELECT USER() as current_user");
    $user = $stmt->fetch();
    echo "<h3>Current User:</h3>";
    echo "<p><strong>" . $user['current_user'] . "</strong></p>";
    
    // Show user privileges
    $stmt = $pdo->query("SHOW GRANTS");
    $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>User Privileges:</h3>";
    echo "<ul>";
    foreach ($grants as $grant) {
        echo "<li>$grant</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    
    echo "<h3>Possible Issues:</h3>";
    echo "<ul>";
    echo "<li>Wrong username or password</li>";
    echo "<li>User doesn't exist</li>";
    echo "<li>User doesn't have permission to connect</li>";
    echo "</ul>";
    
    echo "<h3>What to check in Hostinger:</h3>";
    echo "<ol>";
    echo "<li>Go to 'Databases' → 'MySQL Databases'</li>";
    echo "<li>Check the exact username (should be u279576488_lapaz)</li>";
    echo "<li>Check if the user exists</li>";
    echo "<li>Check if the user has any databases assigned</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Replace 'YOUR_PASSWORD_HERE' with your actual database password</li>";
echo "<li>Upload this file to your Hostinger hosting</li>";
echo "<li>Run it in your browser</li>";
echo "<li>Check the results and use the correct database name in the installer</li>";
echo "<li>Delete this file after use for security</li>";
echo "</ol>";
?>
