<?php
/**
 * Model-by-Model Test - Find which model is causing the 500 error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Model-by-Model Loading Test</h1>\n";

try {
    echo "<h2>Step 1: Session & Auth Check</h2>\n";
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        echo "❌ No session - creating test session<br>\n";
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['first_name'] = 'Test';
        echo "✅ Test session created<br>\n";
    } else {
        echo "✅ Session exists: User ID " . $_SESSION['user_id'] . "<br>\n";
    }
    
    echo "<h2>Step 2: Load Config & Database</h2>\n";
    $rootPath = dirname(__DIR__);
    
    require_once $rootPath . '/config/config_php84.php';
    echo "✅ Config loaded<br>\n";
    
    require_once $rootPath . '/config/database.php';
    echo "✅ Database class loaded<br>\n";
    
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✅ Database connected<br>\n";
    
    echo "<h2>Step 3: Load Models One by One</h2>\n";
    
    $models = [
        'User' => $rootPath . '/models/User.php',
        'Student' => $rootPath . '/models/Student.php',
        'Application' => $rootPath . '/models/Application.php',
        'Program' => $rootPath . '/models/Program.php',
        'Payment' => $rootPath . '/models/Payment.php',
        'Report' => $rootPath . '/models/Report.php'
    ];
    
    $loadedModels = [];
    
    foreach ($models as $modelName => $modelPath) {
        echo "<h3>Loading $modelName Model</h3>\n";
        
        if (!file_exists($modelPath)) {
            echo "❌ File not found: $modelPath<br>\n";
            continue;
        }
        
        try {
            echo "Loading file: $modelPath<br>\n";
            require_once $modelPath;
            echo "✅ $modelName file loaded<br>\n";
            
            echo "Creating $modelName instance...<br>\n";
            $instance = new $modelName($pdo);
            echo "✅ $modelName instance created<br>\n";
            
            $loadedModels[$modelName] = $instance;
            
            // Test a basic method if it exists
            if ($modelName === 'User' && method_exists($instance, 'getById')) {
                echo "Testing User::getById()...<br>\n";
                $testUser = $instance->getById($_SESSION['user_id']);
                echo "✅ User method test: " . ($testUser ? "User found" : "No user") . "<br>\n";
            }
            
            if ($modelName === 'Report' && method_exists($instance, 'getDashboardStats')) {
                echo "Testing Report::getDashboardStats()...<br>\n";
                $stats = $instance->getDashboardStats();
                echo "✅ Report method test: " . count($stats) . " stats retrieved<br>\n";
            }
            
        } catch (ParseError $e) {
            echo "❌ PARSE ERROR in $modelName:<br>\n";
            echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
            echo "File: " . htmlspecialchars($e->getFile()) . "<br>\n";
            echo "Line: " . $e->getLine() . "<br>\n";
            break;
            
        } catch (Error $e) {
            echo "❌ FATAL ERROR in $modelName:<br>\n";
            echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
            echo "File: " . htmlspecialchars($e->getFile()) . "<br>\n";
            echo "Line: " . $e->getLine() . "<br>\n";
            break;
            
        } catch (Exception $e) {
            echo "❌ EXCEPTION in $modelName:<br>\n";
            echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
            echo "File: " . htmlspecialchars($e->getFile()) . "<br>\n";
            echo "Line: " . $e->getLine() . "<br>\n";
            break;
        }
    }
    
    echo "<h2>Step 4: Test Dashboard Data Retrieval</h2>\n";
    
    if (isset($loadedModels['Report'])) {
        try {
            $reportModel = $loadedModels['Report'];
            $stats = $reportModel->getDashboardStats();
            echo "✅ Dashboard stats: " . json_encode($stats) . "<br>\n";
        } catch (Exception $e) {
            echo "⚠️ Dashboard stats error: " . $e->getMessage() . "<br>\n";
        }
    }
    
    if (isset($loadedModels['Application'])) {
        try {
            $applicationModel = $loadedModels['Application'];
            $recentApps = $applicationModel->getRecent(3);
            echo "✅ Recent applications: " . count($recentApps) . " found<br>\n";
        } catch (Exception $e) {
            echo "⚠️ Recent applications error: " . $e->getMessage() . "<br>\n";
        }
    }
    
    echo "<h2>🎉 SUCCESS!</h2>\n";
    echo "<p>All models loaded successfully. Models loaded: " . implode(', ', array_keys($loadedModels)) . "</p>\n";
    
    echo "<h3>Now Test Simple Dashboard</h3>\n";
    echo "<p><a href='simple_dashboard_test.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Simple Dashboard →</a></p>\n";
    
} catch (ParseError $e) {
    echo "<h2>❌ PARSE ERROR!</h2>\n";
    echo "<p><strong>This is the exact cause of your 500 error!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    
} catch (Error $e) {
    echo "<h2>❌ FATAL ERROR!</h2>\n";
    echo "<p><strong>This is the exact cause of your 500 error!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ EXCEPTION!</h2>\n";
    echo "<p><strong>This is the exact cause of your 500 error!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
}
?>
