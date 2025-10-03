<?php
/**
 * System Configuration Installation Script
 * Run this once to set up the system configuration table
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Read and execute the system config SQL
    $sql = file_get_contents('database/system_config.sql');
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ System configuration table installed successfully!\n";
    echo "✅ Default branding, email, and payment settings created.\n";
    echo "\nYou can now access the admin dashboard and configure your settings.\n";
    
} catch (Exception $e) {
    echo "❌ Error installing system configuration: " . $e->getMessage() . "\n";
}
?>
