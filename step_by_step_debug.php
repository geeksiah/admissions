<?php
/**
 * Step-by-step debug to find exact error location
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Step-by-Step Application Debug</h1>\n";

try {
    echo "<h2>Step 1: Session</h2>\n";
    session_start();
    echo "✅ Session started<br>\n";
    
    echo "<h2>Step 2: Check Session Data</h2>\n";
    if (isset($_SESSION['user_id'])) {
        echo "✅ User ID: " . $_SESSION['user_id'] . "<br>\n";
        echo "✅ Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>\n";
    } else {
        echo "❌ No user session - need to login first<br>\n";
        echo "<p><a href='login.php'>Go to Login</a></p>\n";
        exit;
    }
    
    echo "<h2>Step 3: File Paths</h2>\n";
    $rootPath = dirname(__DIR__);
    echo "Root path: " . $rootPath . "<br>\n";
    
    $configFile = $rootPath . '/config/config.php';
    $dbFile = $rootPath . '/config/database.php';
    
    if (file_exists($configFile)) {
        echo "✅ Config file exists<br>\n";
    } else {
        echo "❌ Config file missing: " . $configFile . "<br>\n";
        exit;
    }
    
    if (file_exists($dbFile)) {
        echo "✅ Database file exists<br>\n";
    } else {
        echo "❌ Database file missing: " . $dbFile . "<br>\n";
        exit;
    }
    
    echo "<h2>Step 4: Load Config</h2>\n";
    require_once $configFile;
    echo "✅ Config loaded successfully<br>\n";
    echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'NOT DEFINED') . "<br>\n";
    
    echo "<h2>Step 5: Load Database Class</h2>\n";
    require_once $dbFile;
    echo "✅ Database class loaded<br>\n";
    
    echo "<h2>Step 6: Create Database Connection</h2>\n";
    $database = new Database();
    echo "✅ Database object created<br>\n";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection established<br>\n";
    
    echo "<h2>Step 7: Test Database Query</h2>\n";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result && $result['test'] == 1) {
        echo "✅ Database query successful<br>\n";
    }
    
    echo "<h2>Step 8: Load Models One by One</h2>\n";
    
    $models = [
        'User' => $rootPath . '/models/User.php',
        'Student' => $rootPath . '/models/Student.php',
        'Application' => $rootPath . '/models/Application.php',
        'Program' => $rootPath . '/models/Program.php',
        'Payment' => $rootPath . '/models/Payment.php',
        'Report' => $rootPath . '/models/Report.php'
    ];
    
    foreach ($models as $modelName => $modelPath) {
        if (file_exists($modelPath)) {
            echo "Loading $modelName model...<br>\n";
            require_once $modelPath;
            echo "✅ $modelName model loaded<br>\n";
            
            // Try to instantiate
            $modelInstance = new $modelName($pdo);
            echo "✅ $modelName instance created<br>\n";
        } else {
            echo "❌ $modelName model missing: $modelPath<br>\n";
        }
    }
    
    echo "<h2>Step 9: Test Model Methods</h2>\n";
    
    if (isset($modelInstance) && class_exists('User')) {
        $userModel = new User($pdo);
        echo "Testing User model...<br>\n";
        
        $currentUser = $userModel->getById($_SESSION['user_id']);
        if ($currentUser) {
            echo "✅ User found: " . $currentUser['first_name'] . "<br>\n";
        } else {
            echo "⚠️ No user found with ID " . $_SESSION['user_id'] . "<br>\n";
        }
    }
    
    if (class_exists('Report')) {
        $reportModel = new Report($pdo);
        echo "Testing Report model...<br>\n";
        
        $stats = $reportModel->getDashboardStats();
        echo "✅ Dashboard stats retrieved<br>\n";
        echo "Total applications: " . ($stats['total_applications'] ?? 0) . "<br>\n";
    }
    
    echo "<h2>🎉 SUCCESS!</h2>\n";
    echo "<p>All components loaded successfully. The dashboard should work now.</p>\n";
    echo "<p><a href='admin/dashboard_absolute.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Dashboard →</a></p>\n";
    
} catch (ParseError $e) {
    echo "<h2>❌ PHP PARSE ERROR!</h2>\n";
    echo "<p><strong>This is a PHP 8.4 syntax compatibility issue!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    
} catch (Error $e) {
    echo "<h2>❌ PHP FATAL ERROR!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ EXCEPTION!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}
?>
