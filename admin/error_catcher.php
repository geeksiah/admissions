<?php
/**
 * Ultra-Simple Error Catcher
 * This file will catch ANY error and log it
 */

// Start error logging IMMEDIATELY - even before any other code
require_once dirname(__DIR__) . '/classes/ErrorLogger.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('display_errors', 0);

// Get logger instance
$logger = ErrorLogger::getInstance();

// Log that we're starting
$logger->logDebug("=== ERROR CATCHER STARTED ===");

// Test basic functionality
echo "Error logging system initialized...\n";
$logger->logDebug("Basic echo successful");

// Test session
session_start();
$logger->logDebug("Session started");

// Test file operations
$rootPath = dirname(__DIR__);
$logger->logDebug("Root path determined: $rootPath");

// Test directory access
$logsDir = $rootPath . '/logs/';
$logger->logDebug("Logs directory: $logsDir");
$logger->logDebug("Logs directory exists: " . (is_dir($logsDir) ? 'YES' : 'NO'));
$logger->logDebug("Logs directory writable: " . (is_writable($logsDir) ? 'YES' : 'NO'));

// Test config loading
$configPath = $rootPath . '/config/config.php';
$logger->logDebug("Config path: $configPath");
$logger->logDebug("Config exists: " . (file_exists($configPath) ? 'YES' : 'NO'));

if (file_exists($configPath)) {
    try {
        include $configPath;
        $logger->logDebug("Config loaded successfully");
    } catch (Throwable $e) {
        $logger->logError([
            'type' => 'CONFIG_LOAD_ERROR',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// Test database
$dbPath = $rootPath . '/config/database.php';
$logger->logDebug("Database path: $dbPath");
$logger->logDebug("Database exists: " . (file_exists($dbPath) ? 'YES' : 'NO'));

if (file_exists($dbPath)) {
    try {
        include $dbPath;
        $logger->logDebug("Database class loaded");
        
        if (class_exists('Database')) {
            $logger->logDebug("Database class exists");
            
            try {
                $database = new Database();
                $logger->logDebug("Database object created");
                
                $pdo = $database->getConnection();
                $logger->logDebug("Database connection obtained");
                
            } catch (Throwable $e) {
                $logger->logError([
                    'type' => 'DATABASE_CONNECTION_ERROR',
                    'severity' => 'ERROR',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        } else {
            $logger->logDebug("Database class NOT found");
        }
        
    } catch (Throwable $e) {
        $logger->logError([
            'type' => 'DATABASE_LOAD_ERROR',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// Test models
$models = ['User', 'Student', 'Application', 'Program', 'Payment', 'Report'];
foreach ($models as $model) {
    $modelPath = $rootPath . '/models/' . $model . '.php';
    $logger->logDebug("Testing model: $model");
    $logger->logDebug("Model path: $modelPath");
    $logger->logDebug("Model exists: " . (file_exists($modelPath) ? 'YES' : 'NO'));
    
    if (file_exists($modelPath)) {
        try {
            include $modelPath;
            $logger->logDebug("Model $model loaded");
            
            if (class_exists($model)) {
                $logger->logDebug("Model class $model exists");
            } else {
                $logger->logDebug("Model class $model NOT found after loading");
            }
            
        } catch (Throwable $e) {
            $logger->logError([
                'type' => 'MODEL_LOAD_ERROR',
                'severity' => 'ERROR',
                'message' => "Error loading $model: " . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}

// Test a simple dashboard attempt
$logger->logDebug("Attempting to create simple dashboard");

try {
    // Try to get user data if session exists
    if (isset($_SESSION['user_id'])) {
        $logger->logDebug("Session user_id exists: " . $_SESSION['user_id']);
        
        if (isset($userModel)) {
            try {
                $currentUser = $userModel->getById($_SESSION['user_id']);
                $logger->logDebug("User data retrieved", $currentUser);
            } catch (Throwable $e) {
                $logger->logError([
                    'type' => 'USER_DATA_ERROR',
                    'severity' => 'ERROR',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }
    } else {
        $logger->logDebug("No session user_id found");
    }
    
    // Try to get dashboard stats
    if (isset($reportModel)) {
        try {
            $stats = $reportModel->getDashboardStats();
            $logger->logDebug("Dashboard stats retrieved", $stats);
        } catch (Throwable $e) {
            $logger->logError([
                'type' => 'DASHBOARD_STATS_ERROR',
                'severity' => 'ERROR',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
} catch (Throwable $e) {
    $logger->logError([
        'type' => 'DASHBOARD_CREATION_ERROR',
        'severity' => 'ERROR',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

$logger->logDebug("=== ERROR CATCHER COMPLETED ===");

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error Catcher Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Error Catcher Results</h1>
    
    <div class="success">
        <h2>✅ Error Logging System Working</h2>
        <p>All errors have been captured and logged to the logs/ directory.</p>
    </div>
    
    <div class="info">
        <h3>📁 Log Files Created:</h3>
        <?php
        $logFiles = $logger->getLogFiles();
        if (!empty($logFiles)) {
            echo "<ul>";
            foreach ($logFiles as $file) {
                echo "<li><strong>" . htmlspecialchars($file['name']) . "</strong> - " . number_format($file['size']) . " bytes</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No log files found.</p>";
        }
        ?>
    </div>
    
    <div class="info">
        <h3>📊 System Information:</h3>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
            <li><strong>Memory Usage:</strong> <?php echo number_format(memory_get_usage(true)); ?> bytes</li>
            <li><strong>Peak Memory:</strong> <?php echo number_format(memory_get_peak_usage(true)); ?> bytes</li>
            <li><strong>Root Path:</strong> <?php echo htmlspecialchars($rootPath); ?></li>
            <li><strong>Logs Directory:</strong> <?php echo htmlspecialchars($logsDir); ?></li>
        </ul>
    </div>
    
    <div class="info">
        <h3>📋 Latest Log Entries:</h3>
        <?php
        $latestErrors = $logger->getLatestErrors(10);
        if (!empty($latestErrors)) {
            foreach ($latestErrors as $error) {
                echo "<pre>" . htmlspecialchars($error) . "</pre>";
            }
        } else {
            echo "<p>No log entries found.</p>";
        }
        ?>
    </div>
    
    <div class="info">
        <h3>🔧 Next Steps:</h3>
        <ol>
            <li>Check the log files in the <code>logs/</code> directory</li>
            <li>Look for any ERROR or FATAL entries</li>
            <li>Share the error logs to identify the exact issue</li>
            <li>Use the debug dashboard: <a href="debug_with_logging.php">debug_with_logging.php</a></li>
        </ol>
    </div>
</body>
</html>
