<?php
/**
 * Ultra-minimal debug - Find exact error point
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting debug...<br>\n";

try {
    echo "Step 2: Starting session...<br>\n";
    session_start();
    echo "✅ Session started<br>\n";
    
    echo "Step 3: Checking authentication...<br>\n";
    if (!isset($_SESSION['user_id'])) {
        echo "❌ No user_id in session. Redirecting to login...<br>\n";
        header('Location: ../login.php');
        exit;
    }
    echo "✅ User ID: " . $_SESSION['user_id'] . "<br>\n";
    
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
        echo "❌ Invalid role: " . ($_SESSION['role'] ?? 'none') . "<br>\n";
        exit;
    }
    echo "✅ Role: " . $_SESSION['role'] . "<br>\n";
    
    echo "Step 4: Getting root path...<br>\n";
    $rootPath = dirname(__DIR__);
    echo "✅ Root path: " . $rootPath . "<br>\n";
    
    echo "Step 5: Checking if config files exist...<br>\n";
    $configPath = $rootPath . '/config/config.php';
    $dbPath = $rootPath . '/config/database.php';
    
    if (file_exists($configPath)) {
        echo "✅ Config file exists: " . $configPath . "<br>\n";
    } else {
        echo "❌ Config file missing: " . $configPath . "<br>\n";
        exit;
    }
    
    if (file_exists($dbPath)) {
        echo "✅ Database file exists: " . $dbPath . "<br>\n";
    } else {
        echo "❌ Database file missing: " . $dbPath . "<br>\n";
        exit;
    }
    
    echo "Step 6: Loading config...<br>\n";
    require_once $configPath;
    echo "✅ Config loaded<br>\n";
    
    echo "Step 7: Loading database...<br>\n";
    require_once $dbPath;
    echo "✅ Database class loaded<br>\n";
    
    echo "Step 8: Creating database connection...<br>\n";
    $database = new Database();
    echo "✅ Database object created<br>\n";
    
    $pdo = $database->getConnection();
    echo "✅ Database connection established<br>\n";
    
    echo "Step 9: Testing simple query...<br>\n";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result['test'] == 1) {
        echo "✅ Database query successful<br>\n";
    }
    
    echo "Step 10: Checking model files...<br>\n";
    $modelFiles = [
        'User.php',
        'Student.php', 
        'Application.php',
        'Program.php',
        'Payment.php',
        'Report.php'
    ];
    
    foreach ($modelFiles as $file) {
        $modelPath = $rootPath . '/models/' . $file;
        if (file_exists($modelPath)) {
            echo "✅ Model exists: " . $file . "<br>\n";
        } else {
            echo "❌ Model missing: " . $file . " at " . $modelPath . "<br>\n";
        }
    }
    
    echo "Step 11: Loading User model...<br>\n";
    require_once $rootPath . '/models/User.php';
    echo "✅ User model loaded<br>\n";
    
    echo "Step 12: Creating User model instance...<br>\n";
    $userModel = new User($pdo);
    echo "✅ User model created<br>\n";
    
    echo "Step 13: Getting current user...<br>\n";
    $currentUser = $userModel->getById($_SESSION['user_id']);
    if ($currentUser) {
        echo "✅ User found: " . $currentUser['first_name'] . "<br>\n";
    } else {
        echo "⚠️ No user found with ID " . $_SESSION['user_id'] . "<br>\n";
    }
    
    echo "<h2>🎉 SUCCESS!</h2>\n";
    echo "<p>All steps completed successfully. The dashboard should work now.</p>\n";
    echo "<p><a href='dashboard_absolute.php'>Try Dashboard Again</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ EXCEPTION CAUGHT!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<h3>Stack Trace:</h3>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
} catch (Error $e) {
    echo "<h2>❌ FATAL ERROR CAUGHT!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<h3>Stack Trace:</h3>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<hr>\n";
echo "<h3>Debug Info:</h3>\n";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>\n";
echo "<p><strong>Current Directory:</strong> " . getcwd() . "</p>\n";
echo "<p><strong>Script Path:</strong> " . __FILE__ . "</p>\n";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>\n";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>\n";
echo "<p><strong>Session Data:</strong></p>\n";
echo "<pre>" . print_r($_SESSION, true) . "</pre>\n";
?>
