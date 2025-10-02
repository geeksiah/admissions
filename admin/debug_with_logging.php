<?php
/**
 * Debug Dashboard with Comprehensive Error Logging
 * This will log EVERY step and capture ALL errors
 */

// Start error logging IMMEDIATELY
require_once dirname(__DIR__) . '/classes/ErrorLogger.php';
$logger = ErrorLogger::getInstance();

$logger->logDebug("=== DEBUG SESSION STARTED ===");
$logger->logDebug("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown'));
$logger->logDebug("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown'));
$logger->logDebug("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));

// Disable all output buffering and error display
ini_set('output_buffering', 0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logger->logDebug("Step 1: Basic PHP functionality check");
$logger->logDebug("PHP Version: " . phpversion());
$logger->logDebug("Current directory: " . __DIR__);
$logger->logDebug("Parent directory: " . dirname(__DIR__));

$logger->logDebug("Step 2: Session start");
session_start();
$logger->logDebug("Session started successfully");
$logger->logDebug("Session ID: " . session_id());

// Create test session if needed
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
    $_SESSION['username'] = 'testadmin';
    $_SESSION['email'] = 'test@example.com';
    $logger->logDebug("Test session created");
} else {
    $logger->logDebug("Existing session found", $_SESSION);
}

$logger->logDebug("Step 3: Directory structure check");
$rootPath = dirname(__DIR__);
$logger->logDebug("Root path: $rootPath");

$requiredDirs = ['config', 'models', 'classes', 'includes', 'admin', 'student', 'logs'];
foreach ($requiredDirs as $dir) {
    $dirPath = $rootPath . '/' . $dir;
    $exists = file_exists($dirPath);
    $writable = is_writable($dirPath);
    $logger->logDebug("Directory $dir: exists=$exists, writable=$writable");
}

$logger->logDebug("Step 4: Critical files check");
$criticalFiles = [
    'config/config.php',
    'config/database.php',
    'config/installed.lock',
    'models/User.php',
    'models/Student.php',
    'models/Application.php',
    'models/Program.php',
    'models/Payment.php',
    'models/Report.php',
    'classes/Security.php',
    'classes/ErrorLogger.php'
];

foreach ($criticalFiles as $file) {
    $filePath = $rootPath . '/' . $file;
    $exists = file_exists($filePath);
    $readable = is_readable($filePath);
    $logger->logDebug("File $file: exists=$exists, readable=$readable");
    
    if ($exists && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        $output = shell_exec("php -l " . escapeshellarg($filePath) . " 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        $logger->logDebug("File $file syntax check: " . ($syntaxOk ? 'OK' : 'ERROR'));
        if (!$syntaxOk) {
            $logger->logError([
                'type' => 'SYNTAX_ERROR',
                'severity' => 'ERROR',
                'message' => "Syntax error in $file",
                'file' => $filePath,
                'line' => 0,
                'context' => ['output' => $output]
            ]);
        }
    }
}

$logger->logDebug("Step 5: Database connection test");
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u279576488_admissions', 'u279576488_lapaz', '7uVV;OEX|');
    $logger->logDebug("Direct PDO connection successful");
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $logger->logDebug("Database query test successful", ['user_count' => $result['count']]);
    
} catch (Exception $e) {
    $logger->logError([
        'type' => 'DATABASE_ERROR',
        'severity' => 'ERROR',
        'message' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ]);
}

$logger->logDebug("Step 6: Config file loading test");
$configPath = $rootPath . '/config/config.php';
if (file_exists($configPath)) {
    try {
        ob_start();
        include $configPath;
        $output = ob_get_clean();
        $logger->logDebug("Config file included successfully");
        
        if (defined('APP_NAME')) {
            $logger->logDebug("APP_NAME constant defined: " . APP_NAME);
        } else {
            $logger->logDebug("APP_NAME constant NOT defined");
        }
        
        if (!empty($output)) {
            $logger->logDebug("Config file produced output", ['output' => $output]);
        }
        
    } catch (Error $e) {
        $logger->logError([
            'type' => 'CONFIG_ERROR',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } catch (Exception $e) {
        $logger->logError([
            'type' => 'CONFIG_EXCEPTION',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
} else {
    $logger->logDebug("Config file not found at: $configPath");
}

$logger->logDebug("Step 7: Database class loading test");
$dbPath = $rootPath . '/config/database.php';
if (file_exists($dbPath)) {
    try {
        include $dbPath;
        $logger->logDebug("Database class file included");
        
        if (class_exists('Database')) {
            $logger->logDebug("Database class exists");
            
            try {
                $database = new Database();
                $logger->logDebug("Database object created successfully");
                
                $pdo = $database->getConnection();
                $logger->logDebug("Database connection obtained successfully");
                
            } catch (Exception $e) {
                $logger->logError([
                    'type' => 'DATABASE_INSTANTIATION_ERROR',
                    'severity' => 'ERROR',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            
        } else {
            $logger->logDebug("Database class NOT found after loading");
        }
        
    } catch (Error $e) {
        $logger->logError([
            'type' => 'DATABASE_CLASS_ERROR',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
} else {
    $logger->logDebug("Database file not found at: $dbPath");
}

$logger->logDebug("Step 8: Model loading test");
$models = ['User', 'Student', 'Application', 'Program', 'Payment', 'Report'];

foreach ($models as $model) {
    $modelPath = $rootPath . '/models/' . $model . '.php';
    $logger->logDebug("Loading model: $model");
    
    if (file_exists($modelPath)) {
        try {
            include $modelPath;
            $logger->logDebug("Model file $model included");
            
            if (class_exists($model)) {
                $logger->logDebug("Model class $model exists");
                
                if (isset($pdo)) {
                    try {
                        $instance = new $model($pdo);
                        $logger->logDebug("Model $model instantiated successfully");
                    } catch (Exception $e) {
                        $logger->logError([
                            'type' => 'MODEL_INSTANTIATION_ERROR',
                            'severity' => 'ERROR',
                            'message' => "Failed to instantiate $model: " . $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                }
                
            } else {
                $logger->logDebug("Model class $model NOT found after loading");
            }
            
        } catch (Error $e) {
            $logger->logError([
                'type' => 'MODEL_LOADING_ERROR',
                'severity' => 'ERROR',
                'message' => "Error loading $model: " . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (Exception $e) {
            $logger->logError([
                'type' => 'MODEL_LOADING_EXCEPTION',
                'severity' => 'ERROR',
                'message' => "Exception loading $model: " . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    } else {
        $logger->logDebug("Model file $model not found at: $modelPath");
    }
}

$logger->logDebug("Step 9: System information");
$logger->logSystem("PHP Version: " . phpversion());
$logger->logSystem("Memory Limit: " . ini_get('memory_limit'));
$logger->logSystem("Max Execution Time: " . ini_get('max_execution_time'));
$logger->logSystem("Upload Max Filesize: " . ini_get('upload_max_filesize'));
$logger->logSystem("Error Reporting: " . ini_get('error_reporting'));
$logger->logSystem("Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off'));

$logger->logDebug("Step 10: Test dashboard data retrieval");
if (isset($reportModel)) {
    try {
        $stats = $reportModel->getDashboardStats();
        $logger->logDebug("Dashboard stats retrieved successfully", $stats);
    } catch (Exception $e) {
        $logger->logError([
            'type' => 'DASHBOARD_STATS_ERROR',
            'severity' => 'ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

$logger->logDebug("=== DEBUG SESSION COMPLETED ===");

// Now try to display a simple dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard - Error Logging Active</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .log-entry { background: #f8f9fa; border-left: 4px solid #007bff; padding: 10px; margin: 5px 0; }
        .error-entry { background: #fff5f5; border-left: 4px solid #dc3545; }
        .success-entry { background: #f0fff4; border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔍 Debug Dashboard with Error Logging</h1>
        
        <div class="alert alert-success">
            <h4>✅ Error Logging System Active</h4>
            <p>All errors, exceptions, and system events are being logged to the <code>logs/</code> directory.</p>
            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
            <p><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>📁 Log Files</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $logFiles = $logger->getLogFiles();
                        if (!empty($logFiles)) {
                            echo "<ul class='list-unstyled'>";
                            foreach ($logFiles as $file) {
                                echo "<li>";
                                echo "<strong>" . htmlspecialchars($file['name']) . "</strong><br>";
                                echo "<small class='text-muted'>";
                                echo "Size: " . number_format($file['size']) . " bytes | ";
                                echo "Modified: " . date('Y-m-d H:i:s', $file['modified']);
                                echo "</small>";
                                echo "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<p class='text-muted'>No log files found.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>📊 System Status</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><strong>Memory Usage:</strong> <?php echo number_format(memory_get_usage(true)); ?> bytes</li>
                            <li><strong>Peak Memory:</strong> <?php echo number_format(memory_get_peak_usage(true)); ?> bytes</li>
                            <li><strong>Session ID:</strong> <?php echo session_id(); ?></li>
                            <li><strong>Root Path:</strong> <?php echo htmlspecialchars($rootPath); ?></li>
                            <li><strong>Logs Directory:</strong> <?php echo htmlspecialchars($rootPath . '/logs/'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>📋 Latest Debug Entries</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $latestErrors = $logger->getLatestErrors(5);
                        if (!empty($latestErrors)) {
                            foreach ($latestErrors as $error) {
                                echo "<div class='log-entry'>";
                                echo "<pre>" . htmlspecialchars($error) . "</pre>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p class='text-muted'>No errors logged yet.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>🔧 Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="?action=clear_logs" class="btn btn-warning me-2">Clear All Logs</a>
                        <a href="?action=download_logs" class="btn btn-info me-2">Download Latest Log</a>
                        <a href="?action=test_error" class="btn btn-danger">Test Error Logging</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_GET['action'])): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>⚡ Action Result</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            switch ($_GET['action']) {
                                case 'clear_logs':
                                    $count = $logger->clearLogs();
                                    echo "<div class='alert alert-success'>Cleared $count log files.</div>";
                                    break;
                                    
                                case 'download_logs':
                                    if (file_exists($logger->logFile)) {
                                        header('Content-Type: text/plain');
                                        header('Content-Disposition: attachment; filename="error_log_' . date('Y-m-d') . '.txt"');
                                        readfile($logger->logFile);
                                        exit;
                                    } else {
                                        echo "<div class='alert alert-warning'>No log file found.</div>";
                                    }
                                    break;
                                    
                                case 'test_error':
                                    $logger->logError([
                                        'type' => 'TEST_ERROR',
                                        'severity' => 'WARNING',
                                        'message' => 'This is a test error to verify logging is working',
                                        'file' => __FILE__,
                                        'line' => __LINE__
                                    ]);
                                    echo "<div class='alert alert-success'>Test error logged successfully!</div>";
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
