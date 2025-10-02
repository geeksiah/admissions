<?php
/**
 * Test Specific Dashboard Files
 * This will test the exact dashboard files that are failing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🎯 Dashboard File Specific Test</h1>\n";

// Set up session and basic requirements
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h2>Testing Admin Dashboard Components</h2>\n";

try {
    echo "<p>Testing admin/dashboard_working.php...</p>\n";
    
    // Test just the PHP logic part of dashboard_working.php
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✅ Database connection OK<br>\n";

    require_once 'models/User.php';
    require_once 'models/Student.php';
    require_once 'models/Application.php';
    require_once 'models/Program.php';
    require_once 'models/Payment.php';
    require_once 'models/Report.php';
    echo "✅ Models loaded<br>\n";

    $userModel = new User($pdo);
    $studentModel = new Student($pdo);
    $applicationModel = new Application($pdo);
    $programModel = new Program($pdo);
    $paymentModel = new Payment($pdo);
    $reportModel = new Report($pdo);
    echo "✅ Models instantiated<br>\n";

    // Test user data
    $currentUser = $userModel->getById($_SESSION['user_id']);
    echo "✅ User lookup: " . ($currentUser ? "Found user" : "No user found") . "<br>\n";

    // Test dashboard stats
    try {
        $stats = $reportModel->getDashboardStats();
        echo "✅ Dashboard stats retrieved<br>\n";
    } catch (Exception $e) {
        echo "⚠️ Dashboard stats error (using defaults): " . $e->getMessage() . "<br>\n";
        $stats = [
            'total_applications' => 0,
            'pending_applications' => 0,
            'approved_applications' => 0,
            'rejected_applications' => 0,
            'under_review_applications' => 0
        ];
    }

    // Test recent applications
    try {
        $recentApplications = $applicationModel->getRecent(5);
        echo "✅ Recent applications retrieved (" . count($recentApplications) . ")<br>\n";
    } catch (Exception $e) {
        echo "⚠️ Recent applications error (using empty): " . $e->getMessage() . "<br>\n";
        $recentApplications = [];
    }

    // Test recent students
    try {
        $recentStudents = $studentModel->getRecent(5);
        echo "✅ Recent students retrieved (" . count($recentStudents) . ")<br>\n";
    } catch (Exception $e) {
        echo "⚠️ Recent students error (using empty): " . $e->getMessage() . "<br>\n";
        $recentStudents = [];
    }

    // Test active programs
    try {
        $activePrograms = $programModel->getActive();
        echo "✅ Active programs retrieved (" . count($activePrograms) . ")<br>\n";
    } catch (Exception $e) {
        echo "⚠️ Active programs error (using empty): " . $e->getMessage() . "<br>\n";
        $activePrograms = [];
    }

    echo "<h3>✅ All dashboard logic tests passed!</h3>\n";
    echo "<p>The issue might be in the HTML/output part of the dashboard file.</p>\n";

} catch (Exception $e) {
    echo "<h3>❌ Dashboard Logic Error Found!</h3>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
} catch (Error $e) {
    echo "<h3>❌ Dashboard Fatal Error Found!</h3>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<h2>Testing Include Files</h2>\n";

// Check if includes exist
$includeFiles = ['includes/header.php', 'includes/footer.php'];
foreach ($includeFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Found: $file<br>\n";
    } else {
        echo "❌ Missing: $file<br>\n";
    }
}

echo "<h2>Direct Dashboard Test</h2>\n";
echo "<p>Now testing actual dashboard file inclusion...</p>\n";

try {
    // Capture output to prevent any HTML from breaking our test
    ob_start();
    
    // Try to include the dashboard file
    include 'admin/dashboard_working.php';
    
    $output = ob_get_clean();
    echo "✅ Dashboard file included successfully!<br>\n";
    echo "<p>Output length: " . strlen($output) . " characters</p>\n";
    
    // Show first 200 characters of output
    echo "<h3>Dashboard Output Preview:</h3>\n";
    echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>\n";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<h3>❌ Dashboard Include Error!</h3>\n";
    echo "<p><strong>This is your 500 error source!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
} catch (Error $e) {
    ob_end_clean();
    echo "<h3>❌ Dashboard Fatal Error!</h3>\n";
    echo "<p><strong>This is your 500 error source!</strong></p>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>🔍 Analysis:</strong> If the dashboard logic tests pass but the dashboard include fails, the issue is likely in the HTML/template part of the dashboard file or missing include files.</p>\n";
?>
