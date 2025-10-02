<?php
/**
 * Comprehensive Security Manager
 * Handles authentication, authorization, and security redirects
 */

class SecurityManager {
    private $database;
    private $sessionTimeout = 3600; // 1 hour
    
    public function __construct($database) {
        $this->database = $database;
        $this->startSecureSession();
    }
    
    private function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters before starting
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->sessionTimeout)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Check if user has required role
     */
    public function hasRole($requiredRoles) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
        $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
        
        return in_array($userRole, $roles);
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }
    }
    
    /**
     * Require specific role - redirect to unauthorized if not authorized
     */
    public function requireRole($requiredRoles) {
        $this->requireAuth();
        
        if (!$this->hasRole($requiredRoles)) {
            $this->redirectToUnauthorized();
        }
    }
    
    /**
     * Require admin access
     */
    public function requireAdmin() {
        $this->requireRole(['admin', 'super_admin']);
    }
    
    /**
     * Require student access
     */
    public function requireStudent() {
        $this->requireRole('student');
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin() {
        $loginUrl = $this->getBaseUrl() . '/login.php';
        
        // Store current URL for redirect after login
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/login.php') {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        
        $this->redirect($loginUrl);
    }
    
    /**
     * Redirect to unauthorized page
     */
    private function redirectToUnauthorized() {
        $unauthorizedUrl = $this->getBaseUrl() . '/unauthorized.php';
        $this->redirect($unauthorizedUrl);
    }
    
    /**
     * Redirect to 404 page
     */
    public function redirectTo404() {
        $notFoundUrl = $this->getBaseUrl() . '/error-pages/404.php';
        $this->redirect($notFoundUrl);
    }
    
    /**
     * Redirect to 403 page
     */
    public function redirectTo403() {
        $forbiddenUrl = $this->getBaseUrl() . '/error-pages/403.php';
        $this->redirect($forbiddenUrl);
    }
    
    /**
     * Safe redirect function
     */
    private function redirect($url) {
        // Prevent header already sent errors
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit();
        } else {
            // Fallback if headers already sent
            echo '<script>window.location.href = "' . htmlspecialchars($url) . '";</script>';
            echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">';
            exit();
        }
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Destroy session
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Clear session variables
        $_SESSION = [];
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? $_SESSION['role'] ?? ''
        ];
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event, $details = []) {
        $logFile = dirname(__DIR__) . '/logs/security.log';
        $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $event . ' - ' . json_encode($details) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>
