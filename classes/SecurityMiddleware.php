<?php
/**
 * Security Middleware for CSRF Protection and Authentication
 * Implements centralized security checks as recommended in Improvements.txt
 */

class SecurityMiddleware {
    private static $instance = null;
    private $database;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = new Database();
    }
    
    /**
     * Enforce CSRF protection on all POST requests
     * This addresses the critical security flaw mentioned in Improvements.txt
     */
    public function enforceCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_POST['csrf'] ?? '';
            if (!validateCSRFToken($token)) {
                http_response_code(403);
                die(json_encode(['error' => 'Invalid CSRF token', 'code' => 'CSRF_INVALID']));
            }
        }
    }
    
    /**
     * Centralized authentication check
     * Replaces repetitive requireLogin() calls across files
     */
    public function requireLogin($redirect = '/login') {
        if (!isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                die(json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']));
            }
            header("Location: $redirect");
            exit;
        }
        return true;
    }
    
    /**
     * Centralized role-based authorization
     * Replaces repetitive requireRole() calls across files
     */
    public function requireRole($requiredRole, $redirect = '/unauthorized') {
        $this->requireLogin();
        
        $userRole = $_SESSION['user_role'] ?? '';
        if ($userRole !== $requiredRole) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                die(json_encode(['error' => 'Insufficient permissions', 'code' => 'ROLE_INSUFFICIENT']));
            }
            header("Location: $redirect");
            exit;
        }
        return true;
    }
    
    /**
     * Sanitize and validate input data
     * Prevents SQL injection and XSS attacks
     */
    public function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        $data = trim($data);
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'int':
                return (int) $data;
            case 'float':
                return (float) $data;
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            default:
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate file uploads securely
     */
    public function validateFileUpload($file, $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Additional security: check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        
        if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
            return ['valid' => false, 'error' => 'File type mismatch'];
        }
        
        return ['valid' => true, 'file' => $file];
    }
    
    /**
     * Rate limiting for sensitive operations
     */
    public function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$action}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $rateData = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $rateData['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        if ($rateData['count'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Log security events for audit trail
     */
    public function logSecurityEvent($event, $details = []) {
        try {
            $pdo = $this->database->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $event,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Security log failed: " . $e->getMessage());
        }
    }
    
    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
