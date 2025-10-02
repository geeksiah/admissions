<?php
/**
 * Simple login test to create a valid session
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h1>Login Test</h1>\n";

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    echo "<h2>✅ Already Logged In</h2>\n";
    echo "User ID: " . $_SESSION['user_id'] . "<br>\n";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>\n";
    echo "First Name: " . ($_SESSION['first_name'] ?? 'Not set') . "<br>\n";
    echo "<p><a href='step_by_step_debug.php'>Continue to Debug →</a></p>\n";
    echo "<p><a href='admin/dashboard_absolute.php'>Try Dashboard →</a></p>\n";
    exit;
}

// Simple login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        try {
            // Load config and database
            $rootPath = __DIR__;
            require_once $rootPath . '/config/config.php';
            require_once $rootPath . '/config/database.php';
            require_once $rootPath . '/models/User.php';
            
            $database = new Database();
            $pdo = $database->getConnection();
            $userModel = new User($pdo);
            
            // Try to authenticate
            $user = $userModel->authenticate($username, $password);
            
            if ($user) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                
                echo "<h2>✅ Login Successful!</h2>\n";
                echo "Welcome, " . $user['first_name'] . "!<br>\n";
                echo "Role: " . $user['role'] . "<br>\n";
                echo "<p><a href='step_by_step_debug.php'>Continue to Debug →</a></p>\n";
                echo "<p><a href='admin/dashboard_absolute.php'>Try Dashboard →</a></p>\n";
                exit;
            } else {
                echo "<p style='color: red;'>❌ Invalid username or password</p>\n";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Login error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
}
?>

<h2>Quick Login</h2>
<form method="POST" style="max-width: 400px;">
    <div style="margin-bottom: 15px;">
        <label>Username:</label><br>
        <input type="text" name="username" value="admin" style="width: 100%; padding: 8px;">
        <small>Default: admin</small>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label>Password:</label><br>
        <input type="password" name="password" value="admin123" style="width: 100%; padding: 8px;">
        <small>Default: admin123</small>
    </div>
    
    <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
        Login
    </button>
</form>

<h3>Or Create Test Session</h3>
<p>If you don't know the login credentials, <a href="?create_test=1">click here to create a test session</a></p>

<?php
// Create test session if requested
if (isset($_GET['create_test'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
    $_SESSION['email'] = 'admin@test.com';
    
    echo "<p style='color: green;'>✅ Test session created!</p>\n";
    echo "<p><a href='step_by_step_debug.php'>Continue to Debug →</a></p>\n";
}
?>
