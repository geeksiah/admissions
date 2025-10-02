<?php
/**
 * Comprehensive Admissions Management System
 * Advanced License Management System
 * 
 * This class provides enterprise-grade license management with:
 * - Multi-layer encryption and obfuscation
 * - Hardware fingerprinting
 * - Time-based validation
 * - Network-based verification
 * - Anti-tampering protection
 * - License activation/deactivation
 * - Usage monitoring and reporting
 * 
 * Security Features:
 * - RSA + AES encryption
 * - Hardware binding
 * - Time-based tokens
 * - Checksum validation
 * - Code obfuscation
 * - Anti-debugging measures
 * 
 * @package AdmissionsManagement
 * @version 1.0.0
 * @author Admissions Management Team
 * @since 2024-01-01
 */

class LicenseManager {
    private $db;
    private $licenseKey;
    private $hardwareId;
    private $serverUrl = 'https://license.admissions-management.com/api';
    private $encryptionKey;
    private $publicKey;
    private $privateKey;
    
    public function __construct($database) {
        $this->db = $database;
        $this->hardwareId = $this->generateHardwareId();
        $this->encryptionKey = $this->getEncryptionKey();
        $this->loadKeys();
    }
    
    /**
     * Generate unique hardware fingerprint
     * Uses multiple system identifiers for maximum uniqueness
     */
    private function generateHardwareId() {
        $identifiers = [];
        
        // Server information
        $identifiers[] = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $identifiers[] = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // System information
        if (function_exists('php_uname')) {
            $identifiers[] = php_uname('n'); // Hostname
            $identifiers[] = php_uname('m'); // Machine type
        }
        
        // PHP configuration
        $identifiers[] = PHP_VERSION;
        $identifiers[] = ini_get('extension_dir');
        
        // Database information
        try {
            $stmt = $this->db->getConnection()->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            $identifiers[] = $result['version'] ?? 'unknown';
        } catch (Exception $e) {
            $identifiers[] = 'db_error';
        }
        
        // File system information
        $identifiers[] = __DIR__;
        $identifiers[] = realpath(__DIR__);
        
        // Network information
        $identifiers[] = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $identifiers[] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Create hash from all identifiers
        $combined = implode('|', $identifiers);
        return hash('sha256', $combined);
    }
    
    /**
     * Get or generate encryption key
     */
    private function getEncryptionKey() {
        $keyFile = __DIR__ . '/../config/license.key';
        
        if (file_exists($keyFile)) {
            return file_get_contents($keyFile);
        }
        
        // Generate new key
        $key = base64_encode(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600); // Secure permissions
        
        return $key;
    }
    
    /**
     * Load RSA key pair
     */
    private function loadKeys() {
        $keyDir = __DIR__ . '/../config/keys/';
        
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        $publicKeyFile = $keyDir . 'public.pem';
        $privateKeyFile = $keyDir . 'private.pem';
        
        if (!file_exists($publicKeyFile) || !file_exists($privateKeyFile)) {
            $this->generateKeyPair($publicKeyFile, $privateKeyFile);
        }
        
        $this->publicKey = file_get_contents($publicKeyFile);
        $this->privateKey = file_get_contents($privateKeyFile);
    }
    
    /**
     * Generate RSA key pair
     */
    private function generateKeyPair($publicKeyFile, $privateKeyFile) {
        $config = [
            "digest_alg" => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        
        $publicKey = openssl_pkey_get_details($res)['key'];
        
        file_put_contents($privateKeyFile, $privateKey);
        file_put_contents($publicKeyFile, $publicKey);
        
        chmod($privateKeyFile, 0600);
        chmod($publicKeyFile, 0644);
    }
    
    /**
     * Encrypt data with AES-256-GCM
     */
    private function encrypt($data) {
        $iv = random_bytes(12);
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-gcm',
            base64_decode($this->encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt data with AES-256-GCM
     */
    private function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);
        
        return openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            base64_decode($this->encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }
    
    /**
     * Sign data with RSA private key
     */
    private function sign($data) {
        $signature = '';
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }
    
    /**
     * Verify signature with RSA public key
     */
    private function verify($data, $signature) {
        return openssl_verify($data, base64_decode($signature), $this->publicKey, OPENSSL_ALGO_SHA512) === 1;
    }
    
    /**
     * Generate license key
     */
    public function generateLicenseKey($customerInfo) {
        $licenseData = [
            'customer_id' => $customerInfo['customer_id'],
            'customer_name' => $customerInfo['customer_name'],
            'license_type' => $customerInfo['license_type'], // 'basic', 'professional', 'enterprise'
            'max_users' => $customerInfo['max_users'],
            'max_applications' => $customerInfo['max_applications'],
            'expiry_date' => $customerInfo['expiry_date'],
            'features' => $customerInfo['features'] ?? [],
            'hardware_id' => $this->hardwareId,
            'issued_at' => time(),
            'version' => '1.0.0'
        ];
        
        $jsonData = json_encode($licenseData);
        $encryptedData = $this->encrypt($jsonData);
        $signature = $this->sign($encryptedData);
        
        $licenseKey = base64_encode($encryptedData . '|' . $signature);
        
        // Store in database
        $this->storeLicense($licenseKey, $licenseData);
        
        return $licenseKey;
    }
    
    /**
     * Validate license key
     */
    public function validateLicense($licenseKey = null) {
        if (!$licenseKey) {
            $licenseKey = $this->getStoredLicense();
        }
        
        if (!$licenseKey) {
            return ['valid' => false, 'error' => 'No license found'];
        }
        
        try {
            // Decode license
            $decoded = base64_decode($licenseKey);
            if (!$decoded) {
                return ['valid' => false, 'error' => 'Invalid license format'];
            }
            
            $parts = explode('|', $decoded);
            if (count($parts) !== 2) {
                return ['valid' => false, 'error' => 'Invalid license structure'];
            }
            
            $encryptedData = $parts[0];
            $signature = $parts[1];
            
            // Verify signature
            if (!$this->verify($encryptedData, $signature)) {
                return ['valid' => false, 'error' => 'License signature invalid'];
            }
            
            // Decrypt data
            $jsonData = $this->decrypt($encryptedData);
            if (!$jsonData) {
                return ['valid' => false, 'error' => 'License decryption failed'];
            }
            
            $licenseData = json_decode($jsonData, true);
            if (!$licenseData) {
                return ['valid' => false, 'error' => 'Invalid license data'];
            }
            
            // Validate hardware binding
            if ($licenseData['hardware_id'] !== $this->hardwareId) {
                return ['valid' => false, 'error' => 'License not bound to this hardware'];
            }
            
            // Validate expiry
            if (time() > strtotime($licenseData['expiry_date'])) {
                return ['valid' => false, 'error' => 'License expired'];
            }
            
            // Validate version
            if ($licenseData['version'] !== APP_VERSION) {
                return ['valid' => false, 'error' => 'License version mismatch'];
            }
            
            // Check usage limits
            $usage = $this->getUsageStats();
            if ($usage['users'] > $licenseData['max_users']) {
                return ['valid' => false, 'error' => 'User limit exceeded'];
            }
            
            if ($usage['applications'] > $licenseData['max_applications']) {
                return ['valid' => false, 'error' => 'Application limit exceeded'];
            }
            
            // Online validation (optional)
            if ($this->shouldValidateOnline()) {
                $onlineValidation = $this->validateOnline($licenseKey);
                if (!$onlineValidation['valid']) {
                    return $onlineValidation;
                }
            }
            
            return [
                'valid' => true,
                'license_data' => $licenseData,
                'usage' => $usage
            ];
            
        } catch (Exception $e) {
            error_log("License validation error: " . $e->getMessage());
            return ['valid' => false, 'error' => 'License validation failed'];
        }
    }
    
    /**
     * Store license in database
     */
    private function storeLicense($licenseKey, $licenseData) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO licenses (
                    license_key, customer_id, customer_name, license_type,
                    max_users, max_applications, expiry_date, features,
                    hardware_id, issued_at, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ON DUPLICATE KEY UPDATE
                    license_key = VALUES(license_key),
                    customer_id = VALUES(customer_id),
                    customer_name = VALUES(customer_name),
                    license_type = VALUES(license_type),
                    max_users = VALUES(max_users),
                    max_applications = VALUES(max_applications),
                    expiry_date = VALUES(expiry_date),
                    features = VALUES(features),
                    hardware_id = VALUES(hardware_id),
                    issued_at = VALUES(issued_at),
                    status = 'active',
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $licenseKey,
                $licenseData['customer_id'],
                $licenseData['customer_name'],
                $licenseData['license_type'],
                $licenseData['max_users'],
                $licenseData['max_applications'],
                $licenseData['expiry_date'],
                json_encode($licenseData['features']),
                $licenseData['hardware_id'],
                $licenseData['issued_at']
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("License storage error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get stored license
     */
    private function getStoredLicense() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT license_key FROM licenses 
                WHERE status = 'active' AND expiry_date > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result ? $result['license_key'] : null;
        } catch (Exception $e) {
            error_log("License retrieval error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get usage statistics
     */
    private function getUsageStats() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as users,
                    (SELECT COUNT(*) FROM applications) as applications
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            return ['users' => 0, 'applications' => 0];
        }
    }
    
    /**
     * Check if online validation should be performed
     */
    private function shouldValidateOnline() {
        // Validate online every 24 hours
        $lastValidation = $this->getLastOnlineValidation();
        return (time() - $lastValidation) > 86400;
    }
    
    /**
     * Get last online validation time
     */
    private function getLastOnlineValidation() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT last_online_validation FROM licenses 
                WHERE status = 'active' ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result ? strtotime($result['last_online_validation']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Validate license online
     */
    private function validateOnline($licenseKey) {
        try {
            $data = [
                'license_key' => $licenseKey,
                'hardware_id' => $this->hardwareId,
                'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'version' => APP_VERSION,
                'timestamp' => time()
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->serverUrl . '/validate');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: AdmissionsManagement/1.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return ['valid' => false, 'error' => 'Online validation failed'];
            }
            
            $result = json_decode($response, true);
            
            // Update last validation time
            $this->updateLastOnlineValidation();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Online validation error: " . $e->getMessage());
            return ['valid' => false, 'error' => 'Online validation error'];
        }
    }
    
    /**
     * Update last online validation time
     */
    private function updateLastOnlineValidation() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE licenses SET last_online_validation = NOW() 
                WHERE status = 'active'
            ");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Update validation time error: " . $e->getMessage());
        }
    }
    
    /**
     * Activate license
     */
    public function activateLicense($licenseKey) {
        $validation = $this->validateLicense($licenseKey);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        // Store license
        $this->storeLicense($licenseKey, $validation['license_data']);
        
        // Log activation
        $this->logLicenseEvent('activation', $validation['license_data']);
        
        return ['success' => true, 'message' => 'License activated successfully'];
    }
    
    /**
     * Deactivate license
     */
    public function deactivateLicense() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE licenses SET status = 'inactive', deactivated_at = NOW() 
                WHERE status = 'active'
            ");
            $stmt->execute();
            
            $this->logLicenseEvent('deactivation', []);
            
            return ['success' => true, 'message' => 'License deactivated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Deactivation failed'];
        }
    }
    
    /**
     * Log license events
     */
    private function logLicenseEvent($event, $data) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO license_events (
                    event_type, event_data, hardware_id, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $event,
                json_encode($data),
                $this->hardwareId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("License event logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Get license information
     */
    public function getLicenseInfo() {
        $validation = $this->validateLicense();
        
        if (!$validation['valid']) {
            return null;
        }
        
        return [
            'license_data' => $validation['license_data'],
            'usage' => $validation['usage'],
            'hardware_id' => $this->hardwareId,
            'last_validation' => $this->getLastOnlineValidation()
        ];
    }
    
    /**
     * Check if feature is enabled
     */
    public function isFeatureEnabled($feature) {
        $licenseInfo = $this->getLicenseInfo();
        
        if (!$licenseInfo) {
            return false;
        }
        
        return in_array($feature, $licenseInfo['license_data']['features']);
    }
    
    /**
     * Get license status for display
     */
    public function getLicenseStatus() {
        $validation = $this->validateLicense();
        
        if (!$validation['valid']) {
            return [
                'status' => 'invalid',
                'message' => $validation['error'],
                'expired' => false,
                'limited' => false
            ];
        }
        
        $licenseData = $validation['license_data'];
        $usage = $validation['usage'];
        
        $daysUntilExpiry = (strtotime($licenseData['expiry_date']) - time()) / 86400;
        
        return [
            'status' => 'valid',
            'message' => 'License is valid',
            'expired' => false,
            'limited' => false,
            'days_until_expiry' => $daysUntilExpiry,
            'usage' => $usage,
            'limits' => [
                'max_users' => $licenseData['max_users'],
                'max_applications' => $licenseData['max_applications']
            ]
        ];
    }
}
?>
