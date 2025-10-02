<?php
/**
 * Comprehensive Error Logger
 * Captures ALL possible errors, exceptions, and system issues
 */

class ErrorLogger {
    private static $instance = null;
    private $logDir;
    private $logFile;
    private $debugFile;
    private $systemFile;
    
    private function __construct() {
        $this->logDir = __DIR__ . '/../logs/';
        
        // Ensure logs directory exists and is writable
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $this->logFile = $this->logDir . 'errors_' . date('Y-m-d') . '.txt';
        $this->debugFile = $this->logDir . 'debug_' . date('Y-m-d') . '.txt';
        $this->systemFile = $this->logDir . 'system_' . date('Y-m-d') . '.txt';
        
        // Set up error handlers
        $this->setupErrorHandlers();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function setupErrorHandlers() {
        // Set custom error handler
        set_error_handler([$this, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([$this, 'handleException']);
        
        // Set shutdown handler to catch fatal errors
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    public function handleError($severity, $message, $file, $line, $context = null) {
        $errorType = $this->getErrorType($severity);
        
        $errorData = [
            'type' => 'ERROR',
            'severity' => $errorType,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
        
        $this->logError($errorData);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    public function handleException($exception) {
        $errorData = [
            'type' => 'EXCEPTION',
            'severity' => 'FATAL',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
        
        $this->logError($errorData);
    }
    
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'SHUTDOWN',
                'severity' => 'FATAL',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            ];
            
            $this->logError($errorData);
        }
    }
    
    private function getErrorType($severity) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $types[$severity] ?? 'UNKNOWN';
    }
    
    public function logError($errorData) {
        $logEntry = $this->formatLogEntry($errorData);
        
        // Log to main error file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Log to system file for critical errors
        if (in_array($errorData['severity'], ['FATAL', 'ERROR', 'PARSE', 'CORE_ERROR', 'COMPILE_ERROR'])) {
            file_put_contents($this->systemFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    public function logDebug($message, $data = null) {
        $debugEntry = "[" . date('Y-m-d H:i:s') . "] DEBUG: $message";
        if ($data !== null) {
            $debugEntry .= " | Data: " . json_encode($data);
        }
        $debugEntry .= " | Memory: " . memory_get_usage(true) . " bytes\n";
        
        file_put_contents($this->debugFile, $debugEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function logSystem($message, $data = null) {
        $systemEntry = "[" . date('Y-m-d H:i:s') . "] SYSTEM: $message";
        if ($data !== null) {
            $systemEntry .= " | Data: " . json_encode($data);
        }
        $systemEntry .= " | Memory: " . memory_get_usage(true) . " bytes\n";
        
        file_put_contents($this->systemFile, $systemEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function formatLogEntry($errorData) {
        $entry = "========================================\n";
        $entry .= "TIMESTAMP: " . $errorData['timestamp'] . "\n";
        $entry .= "TYPE: " . $errorData['type'] . "\n";
        $entry .= "SEVERITY: " . $errorData['severity'] . "\n";
        $entry .= "MESSAGE: " . $errorData['message'] . "\n";
        $entry .= "FILE: " . $errorData['file'] . "\n";
        $entry .= "LINE: " . $errorData['line'] . "\n";
        
        if (isset($errorData['trace'])) {
            $entry .= "STACK TRACE:\n" . $errorData['trace'] . "\n";
        }
        
        if (isset($errorData['context']) && $errorData['context']) {
            $entry .= "CONTEXT: " . json_encode($errorData['context']) . "\n";
        }
        
        if (isset($errorData['code'])) {
            $entry .= "CODE: " . $errorData['code'] . "\n";
        }
        
        $entry .= "MEMORY USAGE: " . $errorData['memory_usage'] . " bytes\n";
        $entry .= "PEAK MEMORY: " . $errorData['memory_peak'] . " bytes\n";
        $entry .= "PHP VERSION: " . phpversion() . "\n";
        $entry .= "SERVER: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
        $entry .= "REQUEST URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
        $entry .= "REQUEST METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
        $entry .= "========================================\n\n";
        
        return $entry;
    }
    
    public function getLogFiles() {
        $files = [];
        $logFiles = glob($this->logDir . '*.txt');
        
        foreach ($logFiles as $file) {
            $files[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
        
        return $files;
    }
    
    public function getLatestErrors($limit = 10) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $errors = explode('========================================', $content);
        
        // Remove empty entries and limit results
        $errors = array_filter($errors, function($error) {
            return trim($error) !== '';
        });
        
        return array_slice($errors, -$limit);
    }
    
    public function clearLogs() {
        $files = glob($this->logDir . '*.txt');
        foreach ($files as $file) {
            unlink($file);
        }
        return count($files);
    }
}
?>
