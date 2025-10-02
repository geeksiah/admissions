<?php
/**
 * Test Dashboard - Isolate dashboard issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Dashboard Test</h1>\n";

try {
    echo "<h2>Step 1: Session & Config</h2>\n";
    session_start();
    require_once 'config/config.php';
    require_once 'config/database.php';
    echo "✅ Config loaded<br>\n";
    
    echo "<h2>Step 2: Database Connection</h2>\n";
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✅ Database connected<br>\n";
    
    echo "<h2>Step 3: Load Models</h2>\n";
    require_once 'models/User.php';
    require_once 'models/Student.php';  
    require_once 'models/Application.php';
    require_once 'models/Program.php';
    require_once 'models/Payment.php';
    require_once 'models/Report.php';
    echo "✅ Models loaded<br>\n";
    
    echo "<h2>Step 4: Instantiate Models</h2>\n";
    $userModel = new User($pdo);
    echo "✅ User model<br>\n";
    
    $studentModel = new Student($pdo);
    echo "✅ Student model<br>\n";
    
    $applicationModel = new Application($pdo);
    echo "✅ Application model<br>\n";
    
    $programModel = new Program($pdo);
    echo "✅ Program model<br>\n";
    
    $paymentModel = new Payment($pdo);
    echo "✅ Payment model<br>\n";
    
    $reportModel = new Report($pdo);
    echo "✅ Report model<br>\n";
    
    echo "<h2>Step 5: Test Model Methods</h2>\n";
    
    // Test dashboard stats
    try {
        $stats = $reportModel->getDashboardStats();
        echo "✅ Dashboard stats: " . json_encode($stats) . "<br>\n";
    } catch (Exception $e) {
        echo "❌ Dashboard stats error: " . $e->getMessage() . "<br>\n";
    }
    
    // Test recent applications
    try {
        $recentApplications = $applicationModel->getRecent(5);
        echo "✅ Recent applications count: " . count($recentApplications) . "<br>\n";
    } catch (Exception $e) {
        echo "❌ Recent applications error: " . $e->getMessage() . "<br>\n";
    }
    
    // Test recent students
    try {
        $recentStudents = $studentModel->getRecent(5);
        echo "✅ Recent students count: " . count($recentStudents) . "<br>\n";
    } catch (Exception $e) {
        echo "❌ Recent students error: " . $e->getMessage() . "<br>\n";
    }
    
    // Test active programs
    try {
        $activePrograms = $programModel->getActive();
        echo "✅ Active programs count: " . count($activePrograms) . "<br>\n";
    } catch (Exception $e) {
        echo "❌ Active programs error: " . $e->getMessage() . "<br>\n";
    }
    
    echo "<h2>Step 6: Simulate User Session</h2>\n";
    
    // Set a dummy session for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    echo "✅ Set dummy session<br>\n";
    
    // Test user lookup
    try {
        $currentUser = $userModel->getById($_SESSION['user_id']);
        if ($currentUser) {
            echo "✅ Found user: " . ($currentUser['first_name'] ?? 'Unknown') . "<br>\n";
        } else {
            echo "⚠️ No user found with ID 1 (normal if no users exist)<br>\n";
        }
    } catch (Exception $e) {
        echo "❌ User lookup error: " . $e->getMessage() . "<br>\n";
    }
    
    echo "<h2>✅ Dashboard Test Complete</h2>\n";
    echo "<p><strong>If all tests pass, the dashboard should work. Check database for missing data.</strong></p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Test Failed</h2>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
} catch (Error $e) {
    echo "<h2>❌ Fatal Error</h2>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
