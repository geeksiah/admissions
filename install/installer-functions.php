<?php
/**
 * Comprehensive Admissions Management System
 * Installer Functions
 */

function testDatabaseConnection($host, $name, $user, $pass) {
    try {
        // For shared hosting (like Hostinger), try direct connection to the database
        // Don't try to create database as user may not have CREATE privileges
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        return true;
    } catch (PDOException $e) {
        // If direct connection fails, try without database name to test credentials
        try {
            $pdo = new PDO(
                "mysql:host=$host;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // If we can connect without database, the issue is database doesn't exist
            // or user doesn't have access to it
            return false;
        } catch (PDOException $e2) {
            return false;
        }
    }
}

function validateAdminData($data) {
    return !empty($data['full_name']) && 
           !empty($data['email']) && 
           !empty($data['password']) && 
           filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
           strlen($data['password']) >= 8;
}

function performInstallation() {
    try {
        // Get configuration from session
        $dbConfig = $_SESSION['db_config'];
        $appConfig = $_SESSION['app_config'];
        $adminData = $_SESSION['admin_data'];
        $emailConfig = $_SESSION['email_config'] ?? [];
        
        // Log installation start
        logInstallationStep("Starting installation process");
        
        // Create database connection
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
            $dbConfig['user'],
            $dbConfig['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        logInstallationStep("Database connection established");
        
        // Create database tables
        createDatabaseTables($pdo);
        logInstallationStep("Database tables created");
        
        // Insert initial data
        insertInitialData($pdo, $appConfig, $adminData, $emailConfig);
        logInstallationStep("Initial data inserted");
        
        // Create configuration files
        createConfigFiles($dbConfig, $appConfig, $emailConfig);
        logInstallationStep("Configuration files created");
        
        // Create directories
        createDirectories();
        logInstallationStep("Directories created");
        
        // Create lock file
        file_put_contents('../config/installed.lock', date('Y-m-d H:i:s'));
        logInstallationStep("Installation completed successfully");
        
        return true;
    } catch (Exception $e) {
        $errorMsg = "Installation error: " . $e->getMessage();
        logInstallationError($errorMsg);
        error_log($errorMsg);
        return false;
    }
}

function createDatabaseTables($pdo) {
    // Read and execute schema files
    $schemaFiles = ['../database/schema.sql', '../database/extended_schema.sql'];
    
    foreach ($schemaFiles as $file) {
        if (file_exists($file)) {
            $sql = file_get_contents($file);
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // If table already exists, log it but continue
                        if (strpos($e->getMessage(), 'already exists') !== false) {
                            logInstallationStep("Table already exists, skipping: " . $statement);
                            continue;
                        }
                        // For other errors, re-throw
                        throw $e;
                    }
                }
            }
        }
    }
}

function insertInitialData($pdo, $appConfig, $adminData, $emailConfig) {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        $adminId = $existingAdmin['id'];
        logInstallationStep("Admin user already exists, using existing ID: $adminId");
    } else {
        // Create admin user
        $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone, role, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, 'admin', 1, NOW())
        ");
        $stmt->execute([
            $adminData['email'],
            $adminData['email'],
            $hashedPassword,
            $adminData['full_name'],
            $adminData['phone']
        ]);
        
        $adminId = $pdo->lastInsertId();
        logInstallationStep("Admin user created with ID: $adminId");
    }
    
    // Insert system configuration
    $configData = [
        ['app_name', $appConfig['app_name'], 'string'],
        ['app_url', $appConfig['app_url'], 'string'],
        ['timezone', $appConfig['timezone'], 'string'],
        ['currency', $appConfig['currency'], 'string'],
        ['default_language', $appConfig['language'], 'string'],
        ['smtp_host', $emailConfig['smtp_host'] ?? '', 'string'],
        ['smtp_port', $emailConfig['smtp_port'] ?? '587', 'string'],
        ['smtp_username', $emailConfig['smtp_user'] ?? '', 'string'],
        ['smtp_password', $emailConfig['smtp_pass'] ?? '', 'string'],
        ['smtp_encryption', $emailConfig['smtp_encryption'] ?? 'tls', 'string'],
        ['from_email', $emailConfig['from_email'] ?? '', 'string'],
        ['from_name', $emailConfig['from_name'] ?? '', 'string'],
        ['application_access_mode', 'payment', 'string'],
        ['voucher_required_for_application', '0', 'boolean'],
        ['multiple_fee_structure', '1', 'boolean'],
        ['school_name', $appConfig['app_name'], 'string'],
        ['school_email', $adminData['email'], 'string'],
        ['copyright_text', '© ' . date('Y') . ' ' . $appConfig['app_name'] . '. All rights reserved.', 'string']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO system_config (config_key, config_value, config_type, updated_by, updated_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        config_value = VALUES(config_value),
        config_type = VALUES(config_type),
        updated_by = VALUES(updated_by),
        updated_at = NOW()
    ");
    
    foreach ($configData as $config) {
        $stmt->execute([$config[0], $config[1], $config[2], $adminId]);
    }
}

function createConfigFiles($dbConfig, $appConfig, $emailConfig) {
    // Create database.php with class-based approach
    $dbConfigContent = "<?php
/**
 * Comprehensive Admissions Management System
 * Database Configuration
 * Generated by installer
 */

class Database {
    private \$host = '{$dbConfig['host']}';
    private \$db_name = '{$dbConfig['name']}';
    private \$username = '{$dbConfig['user']}';
    private \$password = '{$dbConfig['pass']}';
    private \$charset = 'utf8mb4';
    private \$pdo;
    
    public function __construct() {
        \$this->connect();
    }
    
    private function connect() {
        try {
            \$dsn = \"mysql:host={\$this->host};dbname={\$this->db_name};charset={\$this->charset}\";
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\"
            ];
            
            \$this->pdo = new PDO(\$dsn, \$this->username, \$this->password, \$options);
        } catch (PDOException \$e) {
            error_log(\"Database connection failed: \" . \$e->getMessage());
            throw new Exception(\"Database connection failed\");
        }
    }
    
    public function getConnection() {
        return \$this->pdo;
    }
    
    public function beginTransaction() {
        return \$this->pdo->beginTransaction();
    }
    
    public function commit() {
        return \$this->pdo->commit();
    }
    
    public function rollback() {
        return \$this->pdo->rollback();
    }
    
    public function lastInsertId() {
        return \$this->pdo->lastInsertId();
    }
}
?>";
    
    file_put_contents('../config/database.php', $dbConfigContent);
    
    // Update config.php with app settings
    $configContent = file_get_contents('../config/config.php');
    
    // Replace placeholders
    $configContent = str_replace("define('APP_NAME', 'Admissions Management System');", "define('APP_NAME', '{$appConfig['app_name']}');", $configContent);
    $configContent = str_replace("define('APP_URL', 'http://localhost');", "define('APP_URL', '{$appConfig['app_url']}');", $configContent);
    $configContent = str_replace("define('APP_TIMEZONE', 'UTC');", "define('APP_TIMEZONE', '{$appConfig['timezone']}');", $configContent);
    $configContent = str_replace("define('APP_CURRENCY', 'USD');", "define('APP_CURRENCY', '{$appConfig['currency']}');", $configContent);
    
    if (!empty($emailConfig['smtp_host'])) {
        $configContent = str_replace("define('SMTP_HOST', 'localhost');", "define('SMTP_HOST', '{$emailConfig['smtp_host']}');", $configContent);
        $configContent = str_replace("define('SMTP_PORT', 587);", "define('SMTP_PORT', {$emailConfig['smtp_port']});", $configContent);
        $configContent = str_replace("define('SMTP_USERNAME', '');", "define('SMTP_USERNAME', '{$emailConfig['smtp_user']}');", $configContent);
        $configContent = str_replace("define('SMTP_PASSWORD', '');", "define('SMTP_PASSWORD', '{$emailConfig['smtp_pass']}');", $configContent);
        $configContent = str_replace("define('SMTP_ENCRYPTION', 'tls');", "define('SMTP_ENCRYPTION', '{$emailConfig['smtp_encryption']}');", $configContent);
        $configContent = str_replace("define('SMTP_FROM_EMAIL', 'noreply@university.edu');", "define('SMTP_FROM_EMAIL', '{$emailConfig['from_email']}');", $configContent);
        $configContent = str_replace("define('SMTP_FROM_NAME', 'University Admissions');", "define('SMTP_FROM_NAME', '{$emailConfig['from_name']}');", $configContent);
    }
    
    file_put_contents('../config/config.php', $configContent);
}

function createDirectories() {
    $directories = [
        '../uploads',
        '../uploads/applications',
        '../uploads/documents',
        '../uploads/receipts',
        '../uploads/temp',
        '../backups',
        '../logs',
        '../cache',
        '../receipts'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function checkSystemRequirements() {
    $requirements = [
        'PHP Version' => [
            'required' => '8.1.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.1.0', '>=')
        ],
        'MySQL Extension' => [
            'required' => 'mysqli',
            'current' => extension_loaded('mysqli') ? 'Available' : 'Not Available',
            'status' => extension_loaded('mysqli')
        ],
        'PDO MySQL' => [
            'required' => 'pdo_mysql',
            'current' => extension_loaded('pdo_mysql') ? 'Available' : 'Not Available',
            'status' => extension_loaded('pdo_mysql')
        ],
        'GD Extension' => [
            'required' => 'gd',
            'current' => extension_loaded('gd') ? 'Available' : 'Not Available',
            'status' => extension_loaded('gd')
        ],
        'MBString Extension' => [
            'required' => 'mbstring',
            'current' => extension_loaded('mbstring') ? 'Available' : 'Not Available',
            'status' => extension_loaded('mbstring')
        ],
        'cURL Extension' => [
            'required' => 'curl',
            'current' => extension_loaded('curl') ? 'Available' : 'Not Available',
            'status' => extension_loaded('curl')
        ],
        'ZIP Extension' => [
            'required' => 'zip',
            'current' => extension_loaded('zip') ? 'Available' : 'Not Available',
            'status' => extension_loaded('zip')
        ],
        'OpenSSL Extension' => [
            'required' => 'openssl',
            'current' => extension_loaded('openssl') ? 'Available' : 'Not Available',
            'status' => extension_loaded('openssl')
        ]
    ];
    
    return $requirements;
}

function checkFilePermissions() {
    $directories = [
        '../config' => 'config',
        '../uploads' => 'uploads',
        '../backups' => 'backups',
        '../logs' => 'logs',
        '../cache' => 'cache'
    ];
    
    $permissions = [];
    
    foreach ($directories as $path => $name) {
        if (!is_dir($path)) {
            $permissions[$name] = [
                'path' => $path,
                'writable' => false,
                'status' => 'Directory does not exist'
            ];
        } else {
            $permissions[$name] = [
                'path' => $path,
                'writable' => is_writable($path),
                'status' => is_writable($path) ? 'Writable' : 'Not Writable'
            ];
        }
    }
    
    return $permissions;
}

function logInstallationStep($message) {
    $logFile = '../logs/install.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] STEP: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function logInstallationError($message) {
    $logFile = '../logs/install.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] ERROR: $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function getInstallationLog() {
    $logFile = '../logs/install.log';
    if (file_exists($logFile)) {
        return file_get_contents($logFile);
    }
    return 'No installation log found.';
}
?>
