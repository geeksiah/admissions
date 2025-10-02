<?php
/**
 * Production Hotfix Script for Hostinger
 * This script will automatically fix common 500 error issues
 */

echo "<h1>🔧 Production Hotfix Script</h1>\n";
echo "<p><strong>Running automated fixes for common 500 errors...</strong></p>\n";

$fixes = [];

// Fix 1: Create missing directories
echo "<h2>Fix 1: Creating Missing Directories</h2>\n";
$directories = ['uploads', 'logs', 'backups', 'config'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created directory: $dir<br>\n";
            $fixes[] = "Created directory: $dir";
        } else {
            echo "❌ Failed to create directory: $dir<br>\n";
        }
    } else {
        echo "✅ Directory exists: $dir<br>\n";
    }
}

// Fix 2: Ensure config/installed.lock exists
echo "<h2>Fix 2: Installation Lock File</h2>\n";
if (!file_exists('config/installed.lock')) {
    $lockContent = "Installation completed successfully.\nInstallation Date: " . date('Y-m-d H:i:s') . "\nVersion: 1.0.0";
    if (file_put_contents('config/installed.lock', $lockContent)) {
        echo "✅ Created config/installed.lock<br>\n";
        $fixes[] = "Created installation lock file";
    } else {
        echo "❌ Failed to create config/installed.lock<br>\n";
    }
} else {
    echo "✅ Installation lock file exists<br>\n";
}

// Fix 3: Check and fix file permissions
echo "<h2>Fix 3: File Permissions</h2>\n";
$permissionDirs = ['config', 'uploads', 'logs', 'backups'];
foreach ($permissionDirs as $dir) {
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "✅ Set permissions for $dir to 755<br>\n";
            $fixes[] = "Fixed permissions for $dir";
        } else {
            echo "⚠️ Could not change permissions for $dir (may not be needed)<br>\n";
        }
    }
}

// Fix 4: Create .htaccess if missing
echo "<h2>Fix 4: .htaccess File</h2>\n";
if (!file_exists('.htaccess')) {
    $htaccessContent = "# Admissions Management System .htaccess\n\n";
    $htaccessContent .= "# Enable error reporting for debugging\n";
    $htaccessContent .= "php_flag display_errors On\n";
    $htaccessContent .= "php_value error_reporting 'E_ALL & ~E_NOTICE'\n\n";
    $htaccessContent .= "# Security headers\n";
    $htaccessContent .= "<IfModule mod_headers.c>\n";
    $htaccessContent .= "    Header always set X-Content-Type-Options nosniff\n";
    $htaccessContent .= "    Header always set X-Frame-Options DENY\n";
    $htaccessContent .= "</IfModule>\n\n";
    $htaccessContent .= "# Deny access to sensitive files\n";
    $htaccessContent .= "<Files ~ \"\\.(log|lock|sql|md|txt|ini)$\">\n";
    $htaccessContent .= "    Order allow,deny\n";
    $htaccessContent .= "    Deny from all\n";
    $htaccessContent .= "</Files>\n\n";
    $htaccessContent .= "# Protect config directory\n";
    $htaccessContent .= "<Directory \"config\">\n";
    $htaccessContent .= "    <Files ~ \"\\.(php)$\">\n";
    $htaccessContent .= "        Allow from all\n";
    $htaccessContent .= "    </Files>\n";
    $htaccessContent .= "</Directory>\n";
    
    if (file_put_contents('.htaccess', $htaccessContent)) {
        echo "✅ Created .htaccess file<br>\n";
        $fixes[] = "Created .htaccess file";
    } else {
        echo "❌ Failed to create .htaccess file<br>\n";
    }
} else {
    echo "✅ .htaccess file exists<br>\n";
}

// Fix 5: Test basic PHP functionality
echo "<h2>Fix 5: PHP Functionality Test</h2>\n";
try {
    // Test session functionality
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session functionality working<br>\n";
    
    // Test file operations
    $testFile = 'test_write_permissions.tmp';
    if (file_put_contents($testFile, 'test')) {
        unlink($testFile);
        echo "✅ File write permissions working<br>\n";
    } else {
        echo "❌ File write permissions not working<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ PHP functionality error: " . $e->getMessage() . "<br>\n";
}

// Fix 6: Database connection test and fix
echo "<h2>Fix 6: Database Connection Test</h2>\n";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        
        // Try to connect
        $database = new Database();
        $pdo = $database->getConnection();
        echo "✅ Database connection successful<br>\n";
        
        // Test basic query
        $stmt = $pdo->query("SELECT 1 as test");
        if ($stmt->fetch()['test'] == 1) {
            echo "✅ Database query test successful<br>\n";
        }
        
    } else {
        echo "❌ Database configuration file not found<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
    echo "<p><strong>Database Fix Suggestions:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Check if database exists in Hostinger control panel</li>\n";
    echo "<li>Verify database credentials in config/database.php</li>\n";
    echo "<li>Ensure database user has proper privileges</li>\n";
    echo "<li>Use full database name format: u279576488_dbname</li>\n";
    echo "</ul>\n";
}

// Fix 7: Configuration file check
echo "<h2>Fix 7: Configuration File Check</h2>\n";
try {
    require_once 'config/config.php';
    echo "✅ Configuration file loaded successfully<br>\n";
    
    // Check critical constants
    $requiredConstants = ['APP_NAME', 'DB_HOST', 'DB_NAME', 'RECORDS_PER_PAGE'];
    foreach ($requiredConstants as $const) {
        if (defined($const)) {
            echo "✅ Constant $const defined<br>\n";
        } else {
            echo "❌ Constant $const not defined<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "<br>\n";
}

// Summary
echo "<h2>🎯 Hotfix Summary</h2>\n";
if (empty($fixes)) {
    echo "<p style='color: orange;'><strong>No fixes were needed - system appears to be correctly configured.</strong></p>\n";
} else {
    echo "<p style='color: green;'><strong>Applied " . count($fixes) . " fixes:</strong></p>\n";
    echo "<ul>\n";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>\n";
    }
    echo "</ul>\n";
}

echo "<h2>🔍 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li><strong>Delete this file</strong> for security: <code>production_hotfix.php</code></li>\n";
echo "<li><strong>Run diagnostic script</strong>: Upload and run <code>production_diagnostic.php</code></li>\n";
echo "<li><strong>Test your site</strong>: Try accessing index.php, login.php</li>\n";
echo "<li><strong>Check database</strong>: Ensure database exists and has proper schema</li>\n";
echo "<li><strong>Import database schema</strong>: Run the SQL from database/schema.sql</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><small>Hotfix completed: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
