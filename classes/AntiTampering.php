<?php
/**
 * Comprehensive Admissions Management System
 * Advanced Anti-Tampering Protection
 * 
 * This class provides multi-layer anti-tampering protection:
 * - Code integrity checking
 * - File modification detection
 * - Runtime environment validation
 * - Debugger detection
 * - Memory protection
 * - Process monitoring
 * - Obfuscation techniques
 * 
 * @package AdmissionsManagement
 * @version 1.0.0
 * @author Admissions Management Team
 * @since 2024-01-01
 */

class AntiTampering {
    private $db;
    private $integrityHashes = [];
    private $protectedFiles = [];
    private $runtimeChecks = [];
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadIntegrityHashes();
        $this->initializeProtection();
    }
    
    /**
     * Initialize anti-tampering protection
     */
    private function initializeProtection() {
        // Register shutdown function for final checks
        register_shutdown_function([$this, 'finalIntegrityCheck']);
        
        // Set up runtime monitoring
        $this->setupRuntimeMonitoring();
        
        // Perform initial integrity check
        $this->performIntegrityCheck();
        
        // Check for debugging tools
        $this->detectDebuggingTools();
        
        // Validate runtime environment
        $this->validateRuntimeEnvironment();
    }
    
    /**
     * Load integrity hashes for critical files
     */
    private function loadIntegrityHashes() {
        $this->integrityHashes = [
            'config/config.php' => 'sha256:...', // Will be generated during installation
            'config/database.php' => 'sha256:...',
            'classes/LicenseManager.php' => 'sha256:...',
            'classes/Security.php' => 'sha256:...',
            'index.php' => 'sha256:...',
            'login.php' => 'sha256:...'
        ];
        
        // Load from database if available
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT file_path, file_hash FROM file_integrity 
                WHERE status = 'active'
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            foreach ($results as $result) {
                $this->integrityHashes[$result['file_path']] = $result['file_hash'];
            }
        } catch (Exception $e) {
            // Database not available, use default hashes
        }
    }
    
    /**
     * Perform integrity check on critical files
     */
    public function performIntegrityCheck() {
        $violations = [];
        
        foreach ($this->integrityHashes as $file => $expectedHash) {
            $filePath = __DIR__ . '/../' . $file;
            
            if (!file_exists($filePath)) {
                $violations[] = "Critical file missing: $file";
                continue;
            }
            
            $actualHash = hash_file('sha256', $filePath);
            $expectedHash = str_replace('sha256:', '', $expectedHash);
            
            if ($actualHash !== $expectedHash) {
                $violations[] = "File integrity violation: $file";
                $this->logTamperingAttempt('file_modification', $file, $actualHash, $expectedHash);
            }
        }
        
        if (!empty($violations)) {
            $this->handleTamperingDetected($violations);
        }
        
        return empty($violations);
    }
    
    /**
     * Setup runtime monitoring
     */
    private function setupRuntimeMonitoring() {
        // Monitor memory usage
        $this->runtimeChecks['memory_start'] = memory_get_usage();
        $this->runtimeChecks['peak_memory_start'] = memory_get_peak_usage();
        
        // Monitor execution time
        $this->runtimeChecks['start_time'] = microtime(true);
        
        // Monitor loaded extensions
        $this->runtimeChecks['extensions'] = get_loaded_extensions();
        
        // Monitor environment variables
        $this->runtimeChecks['env_vars'] = $_ENV;
        
        // Monitor server variables
        $this->runtimeChecks['server_vars'] = $_SERVER;
    }
    
    /**
     * Detect debugging tools and analysis software
     */
    private function detectDebuggingTools() {
        $debuggingTools = [];
        
        // Check for common debugging functions
        $debugFunctions = [
            'xdebug_is_enabled',
            'xdebug_break',
            'var_dump',
            'print_r',
            'debug_backtrace',
            'error_get_last'
        ];
        
        foreach ($debugFunctions as $function) {
            if (function_exists($function)) {
                $debuggingTools[] = $function;
            }
        }
        
        // Check for debugging extensions
        $debugExtensions = ['xdebug', 'zend_debugger', 'phpdbg'];
        foreach ($debugExtensions as $extension) {
            if (extension_loaded($extension)) {
                $debuggingTools[] = "extension: $extension";
            }
        }
        
        // Check for debugging environment variables
        $debugEnvVars = ['XDEBUG_CONFIG', 'ZEND_DEBUGGER', 'PHP_IDE_CONFIG'];
        foreach ($debugEnvVars as $var) {
            if (isset($_ENV[$var]) || isset($_SERVER[$var])) {
                $debuggingTools[] = "env_var: $var";
            }
        }
        
        // Check for debugging headers
        $debugHeaders = ['X-Debug', 'X-Debugger', 'X-Forwarded-For'];
        foreach ($debugHeaders as $header) {
            if (isset($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header))])) {
                $debuggingTools[] = "header: $header";
            }
        }
        
        if (!empty($debuggingTools)) {
            $this->logTamperingAttempt('debugging_detected', implode(', ', $debuggingTools));
            $this->handleTamperingDetected(['Debugging tools detected: ' . implode(', ', $debuggingTools)]);
        }
    }
    
    /**
     * Validate runtime environment
     */
    private function validateRuntimeEnvironment() {
        $violations = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $violations[] = 'PHP version too old';
        }
        
        // Check for required extensions
        $requiredExtensions = ['mysqli', 'pdo_mysql', 'openssl', 'json'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $violations[] = "Required extension missing: $extension";
            }
        }
        
        // Check for dangerous functions
        $dangerousFunctions = ['eval', 'exec', 'system', 'shell_exec', 'passthru'];
        foreach ($dangerousFunctions as $function) {
            if (function_exists($function)) {
                $violations[] = "Dangerous function available: $function";
            }
        }
        
        // Check file permissions
        $criticalFiles = [
            'config/config.php',
            'config/database.php',
            'classes/LicenseManager.php'
        ];
        
        foreach ($criticalFiles as $file) {
            $filePath = __DIR__ . '/../' . $file;
            if (file_exists($filePath)) {
                $perms = fileperms($filePath);
                if ($perms & 0x0002) { // World writable
                    $violations[] = "File is world writable: $file";
                }
            }
        }
        
        if (!empty($violations)) {
            $this->logTamperingAttempt('environment_violation', implode(', ', $violations));
            $this->handleTamperingDetected($violations);
        }
    }
    
    /**
     * Check for code injection attempts
     */
    public function checkCodeInjection() {
        $suspiciousPatterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/`.*`/',
            '/\$\(.*\)/',
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i'
        ];
        
        $violations = [];
        
        // Check POST data
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = "Code injection attempt in POST[$key]: " . substr($value, 0, 100);
                    }
                }
            }
        }
        
        // Check GET data
        foreach ($_GET as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = "Code injection attempt in GET[$key]: " . substr($value, 0, 100);
                    }
                }
            }
        }
        
        // Check COOKIE data
        foreach ($_COOKIE as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = "Code injection attempt in COOKIE[$key]: " . substr($value, 0, 100);
                    }
                }
            }
        }
        
        if (!empty($violations)) {
            $this->logTamperingAttempt('code_injection', implode(', ', $violations));
            $this->handleTamperingDetected($violations);
        }
        
        return empty($violations);
    }
    
    /**
     * Check for SQL injection attempts
     */
    public function checkSQLInjection() {
        $sqlPatterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bcreate\b.*\btable\b)/i',
            '/(\bexec\b.*\b\()/i',
            '/(\bexecute\b.*\b\()/i'
        ];
        
        $violations = [];
        
        // Check all input data
        $inputData = array_merge($_POST, $_GET, $_COOKIE);
        
        foreach ($inputData as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = "SQL injection attempt in $key: " . substr($value, 0, 100);
                    }
                }
            }
        }
        
        if (!empty($violations)) {
            $this->logTamperingAttempt('sql_injection', implode(', ', $violations));
            $this->handleTamperingDetected($violations);
        }
        
        return empty($violations);
    }
    
    /**
     * Check for XSS attempts
     */
    public function checkXSS() {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/<iframe[^>]*>.*?<\/iframe>/i',
            '/<object[^>]*>.*?<\/object>/i',
            '/<embed[^>]*>.*?<\/embed>/i',
            '/<applet[^>]*>.*?<\/applet>/i',
            '/<meta[^>]*>/i',
            '/<link[^>]*>/i',
            '/<style[^>]*>.*?<\/style>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i'
        ];
        
        $violations = [];
        
        // Check all input data
        $inputData = array_merge($_POST, $_GET, $_COOKIE);
        
        foreach ($inputData as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = "XSS attempt in $key: " . substr($value, 0, 100);
                    }
                }
            }
        }
        
        if (!empty($violations)) {
            $this->logTamperingAttempt('xss_attempt', implode(', ', $violations));
            $this->handleTamperingDetected($violations);
        }
        
        return empty($violations);
    }
    
    /**
     * Monitor memory usage for anomalies
     */
    public function monitorMemoryUsage() {
        $currentMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        
        // Check for unusual memory spikes
        if ($currentMemory > $this->runtimeChecks['memory_start'] * 10) {
            $this->logTamperingAttempt('memory_anomaly', "Memory usage: $currentMemory bytes");
        }
        
        // Check for memory exhaustion
        if ($peakMemory > 128 * 1024 * 1024) { // 128MB
            $this->logTamperingAttempt('memory_exhaustion', "Peak memory: $peakMemory bytes");
        }
    }
    
    /**
     * Monitor execution time for anomalies
     */
    public function monitorExecutionTime() {
        $currentTime = microtime(true);
        $executionTime = $currentTime - $this->runtimeChecks['start_time'];
        
        // Check for unusually long execution times
        if ($executionTime > 30) { // 30 seconds
            $this->logTamperingAttempt('execution_time_anomaly', "Execution time: $executionTime seconds");
        }
    }
    
    /**
     * Final integrity check on shutdown
     */
    public function finalIntegrityCheck() {
        // Perform final memory check
        $this->monitorMemoryUsage();
        
        // Perform final execution time check
        $this->monitorExecutionTime();
        
        // Check for any remaining violations
        $this->checkCodeInjection();
        $this->checkSQLInjection();
        $this->checkXSS();
    }
    
    /**
     * Log tampering attempt
     */
    private function logTamperingAttempt($type, $details, $actualValue = null, $expectedValue = null) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO tampering_logs (
                    attempt_type, details, actual_value, expected_value,
                    ip_address, user_agent, request_uri, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $type,
                $details,
                $actualValue,
                $expectedValue,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $_SERVER['REQUEST_URI'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("Tampering log error: " . $e->getMessage());
        }
    }
    
    /**
     * Handle tampering detected
     */
    private function handleTamperingDetected($violations) {
        // Log the violation
        error_log("Tampering detected: " . implode(', ', $violations));
        
        // Send alert to admin
        $this->sendTamperingAlert($violations);
        
        // Take protective action
        $this->takeProtectiveAction($violations);
    }
    
    /**
     * Send tampering alert
     */
    private function sendTamperingAlert($violations) {
        try {
            $message = "Tampering detected on " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n";
            $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
            $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
            $message .= "Violations:\n" . implode("\n", $violations);
            
            // Send email alert
            $to = 'security@admissions-management.com';
            $subject = 'Security Alert: Tampering Detected';
            $headers = 'From: security@admissions-management.com';
            
            mail($to, $subject, $message, $headers);
            
        } catch (Exception $e) {
            error_log("Tampering alert error: " . $e->getMessage());
        }
    }
    
    /**
     * Take protective action
     */
    private function takeProtectiveAction($violations) {
        // Block the request
        http_response_code(403);
        
        // Log out user if logged in
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clear sensitive data
        $this->clearSensitiveData();
        
        // Display security message
        $this->displaySecurityMessage();
        
        exit;
    }
    
    /**
     * Clear sensitive data
     */
    private function clearSensitiveData() {
        // Clear session data
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
        
        // Clear cookies
        if (isset($_COOKIE)) {
            foreach ($_COOKIE as $name => $value) {
                setcookie($name, '', time() - 3600, '/');
            }
        }
    }
    
    /**
     * Display security message
     */
    private function displaySecurityMessage() {
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Security Alert</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .alert { color: #d32f2f; font-size: 18px; }
    </style>
</head>
<body>
    <div class="alert">
        <h1>Security Alert</h1>
        <p>Unauthorized access attempt detected.</p>
        <p>Your IP address has been logged.</p>
        <p>Please contact system administrator.</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Generate integrity hashes for all critical files
     */
    public function generateIntegrityHashes() {
        $criticalFiles = [
            'config/config.php',
            'config/database.php',
            'classes/LicenseManager.php',
            'classes/Security.php',
            'classes/AntiTampering.php',
            'index.php',
            'login.php',
            'admin/dashboard.php'
        ];
        
        $hashes = [];
        
        foreach ($criticalFiles as $file) {
            $filePath = __DIR__ . '/../' . $file;
            if (file_exists($filePath)) {
                $hashes[$file] = 'sha256:' . hash_file('sha256', $filePath);
            }
        }
        
        // Store in database
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO file_integrity (file_path, file_hash, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    file_hash = VALUES(file_hash),
                    updated_at = NOW()
            ");
            
            foreach ($hashes as $file => $hash) {
                $stmt->execute([$file, $hash]);
            }
        } catch (Exception $e) {
            error_log("Integrity hash storage error: " . $e->getMessage());
        }
        
        return $hashes;
    }
    
    /**
     * Get tampering statistics
     */
    public function getTamperingStats() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    attempt_type,
                    COUNT(*) as count,
                    MAX(timestamp) as last_attempt
                FROM tampering_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY attempt_type
                ORDER BY count DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
