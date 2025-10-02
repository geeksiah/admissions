<?php
/**
 * License Server
 * Central license management and validation
 */

class LicenseServer {
    private $db;
    private $encryptionKey;
    
    public function __construct() {
        $this->db = new PDO(
            'mysql:host=' . LICENSE_DB_HOST . ';dbname=' . LICENSE_DB_NAME,
            LICENSE_DB_USER,
            LICENSE_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $this->encryptionKey = LICENSE_ENCRYPTION_KEY;
    }
    
    /**
     * Validate license
     */
    public function validateLicense($data) {
        try {
            $licenseKey = $data['license_key'] ?? '';
            $hardwareId = $data['hardware_id'] ?? '';
            $domain = $data['domain'] ?? '';
            $version = $data['version'] ?? '';
            
            if (empty($licenseKey) || empty($hardwareId)) {
                return ['valid' => false, 'error' => 'Missing required parameters'];
            }
            
            // Get license from database
            $stmt = $this->db->prepare("
                SELECT * FROM licenses 
                WHERE license_key = ? AND status = 'active'
            ");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                return ['valid' => false, 'error' => 'License not found'];
            }
            
            // Check expiry
            if (strtotime($license['expiry_date']) < time()) {
                return ['valid' => false, 'error' => 'License expired'];
            }
            
            // Check hardware binding
            if ($license['hardware_id'] !== $hardwareId) {
                return ['valid' => false, 'error' => 'Hardware mismatch'];
            }
            
            // Check domain binding (if applicable)
            if (!empty($license['allowed_domains'])) {
                $allowedDomains = json_decode($license['allowed_domains'], true);
                if (!in_array($domain, $allowedDomains)) {
                    return ['valid' => false, 'error' => 'Domain not authorized'];
                }
            }
            
            // Update last validation
            $stmt = $this->db->prepare("
                UPDATE licenses SET 
                    last_validation = NOW(),
                    validation_count = validation_count + 1
                WHERE license_key = ?
            ");
            $stmt->execute([$licenseKey]);
            
            // Log validation
            $this->logValidation($licenseKey, $hardwareId, $domain, $version, true);
            
            return [
                'valid' => true,
                'license_data' => [
                    'customer_id' => $license['customer_id'],
                    'customer_name' => $license['customer_name'],
                    'license_type' => $license['license_type'],
                    'max_users' => $license['max_users'],
                    'max_applications' => $license['max_applications'],
                    'expiry_date' => $license['expiry_date'],
                    'features' => json_decode($license['features'], true)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("License validation error: " . $e->getMessage());
            return ['valid' => false, 'error' => 'Validation failed'];
        }
    }
    
    /**
     * Activate license
     */
    public function activateLicense($data) {
        try {
            $licenseKey = $data['license_key'] ?? '';
            $hardwareId = $data['hardware_id'] ?? '';
            $domain = $data['domain'] ?? '';
            $version = $data['version'] ?? '';
            
            if (empty($licenseKey) || empty($hardwareId)) {
                return ['success' => false, 'error' => 'Missing required parameters'];
            }
            
            // Check if license exists and is available
            $stmt = $this->db->prepare("
                SELECT * FROM licenses 
                WHERE license_key = ? AND status = 'active'
            ");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                return ['success' => false, 'error' => 'License not found'];
            }
            
            // Check if already activated on different hardware
            if (!empty($license['hardware_id']) && $license['hardware_id'] !== $hardwareId) {
                return ['success' => false, 'error' => 'License already activated on different hardware'];
            }
            
            // Update license with hardware binding
            $stmt = $this->db->prepare("
                UPDATE licenses SET 
                    hardware_id = ?,
                    domain = ?,
                    version = ?,
                    activated_at = NOW(),
                    last_validation = NOW()
                WHERE license_key = ?
            ");
            $stmt->execute([$hardwareId, $domain, $version, $licenseKey]);
            
            // Log activation
            $this->logActivation($licenseKey, $hardwareId, $domain, $version);
            
            return ['success' => true, 'message' => 'License activated successfully'];
            
        } catch (Exception $e) {
            error_log("License activation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Activation failed'];
        }
    }
    
    /**
     * Deactivate license
     */
    public function deactivateLicense($data) {
        try {
            $licenseKey = $data['license_key'] ?? '';
            $hardwareId = $data['hardware_id'] ?? '';
            
            if (empty($licenseKey) || empty($hardwareId)) {
                return ['success' => false, 'error' => 'Missing required parameters'];
            }
            
            // Verify hardware binding
            $stmt = $this->db->prepare("
                SELECT * FROM licenses 
                WHERE license_key = ? AND hardware_id = ?
            ");
            $stmt->execute([$licenseKey, $hardwareId]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                return ['success' => false, 'error' => 'License not found or hardware mismatch'];
            }
            
            // Clear hardware binding
            $stmt = $this->db->prepare("
                UPDATE licenses SET 
                    hardware_id = NULL,
                    domain = NULL,
                    deactivated_at = NOW()
                WHERE license_key = ?
            ");
            $stmt->execute([$licenseKey]);
            
            // Log deactivation
            $this->logDeactivation($licenseKey, $hardwareId);
            
            return ['success' => true, 'message' => 'License deactivated successfully'];
            
        } catch (Exception $e) {
            error_log("License deactivation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Deactivation failed'];
        }
    }
    
    /**
     * Get license status
     */
    public function getLicenseStatus($licenseKey) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    l.*,
                    c.customer_name,
                    c.customer_email,
                    c.customer_phone
                FROM licenses l
                LEFT JOIN customers c ON l.customer_id = c.id
                WHERE l.license_key = ?
            ");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                return ['error' => 'License not found'];
            }
            
            return [
                'license_key' => $license['license_key'],
                'customer_name' => $license['customer_name'],
                'customer_email' => $license['customer_email'],
                'license_type' => $license['license_type'],
                'status' => $license['status'],
                'expiry_date' => $license['expiry_date'],
                'hardware_id' => $license['hardware_id'],
                'domain' => $license['domain'],
                'activated_at' => $license['activated_at'],
                'last_validation' => $license['last_validation'],
                'validation_count' => $license['validation_count']
            ];
            
        } catch (Exception $e) {
            error_log("License status error: " . $e->getMessage());
            return ['error' => 'Failed to get license status'];
        }
    }
    
    /**
     * Process heartbeat
     */
    public function processHeartbeat($data) {
        try {
            $licenseKey = $data['license_key'] ?? '';
            $hardwareId = $data['hardware_id'] ?? '';
            $domain = $data['domain'] ?? '';
            $version = $data['version'] ?? '';
            $performance = $data['performance'] ?? [];
            $usage = $data['usage'] ?? [];
            
            if (empty($licenseKey) || empty($hardwareId)) {
                return ['success' => false, 'error' => 'Missing required parameters'];
            }
            
            // Update license heartbeat
            $stmt = $this->db->prepare("
                UPDATE licenses SET 
                    last_heartbeat = NOW(),
                    heartbeat_count = heartbeat_count + 1
                WHERE license_key = ? AND hardware_id = ?
            ");
            $stmt->execute([$licenseKey, $hardwareId]);
            
            // Store performance data
            if (!empty($performance)) {
                $stmt = $this->db->prepare("
                    INSERT INTO performance_logs (
                        license_key, hardware_id, domain, version,
                        execution_time, memory_usage, query_count,
                        slow_queries, error_count, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $licenseKey,
                    $hardwareId,
                    $domain,
                    $version,
                    $performance['execution_time'] ?? 0,
                    $performance['memory_usage'] ?? 0,
                    $performance['query_count'] ?? 0,
                    $performance['slow_queries'] ?? 0,
                    $performance['error_count'] ?? 0
                ]);
            }
            
            // Store usage data
            if (!empty($usage)) {
                $stmt = $this->db->prepare("
                    INSERT INTO usage_logs (
                        license_key, hardware_id, domain, version,
                        active_users, total_applications, new_applications,
                        storage_used, bandwidth_used, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $licenseKey,
                    $hardwareId,
                    $domain,
                    $version,
                    $usage['active_users'] ?? 0,
                    $usage['total_applications'] ?? 0,
                    $usage['new_applications'] ?? 0,
                    $usage['storage_used'] ?? 0,
                    $usage['bandwidth_used'] ?? 0
                ]);
            }
            
            return ['success' => true, 'message' => 'Heartbeat processed'];
            
        } catch (Exception $e) {
            error_log("Heartbeat processing error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Heartbeat processing failed'];
        }
    }
    
    /**
     * Get installations
     */
    public function getInstallations() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    l.license_key,
                    l.customer_id,
                    c.customer_name,
                    c.customer_email,
                    l.license_type,
                    l.status,
                    l.domain,
                    l.hardware_id,
                    l.activated_at,
                    l.last_heartbeat,
                    l.expiry_date
                FROM licenses l
                LEFT JOIN customers c ON l.customer_id = c.id
                WHERE l.hardware_id IS NOT NULL
                ORDER BY l.last_heartbeat DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get installations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get analytics
     */
    public function getAnalytics() {
        try {
            // License statistics
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_licenses,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_licenses,
                    SUM(CASE WHEN hardware_id IS NOT NULL THEN 1 ELSE 0 END) as activated_licenses,
                    SUM(CASE WHEN expiry_date < NOW() THEN 1 ELSE 0 END) as expired_licenses
                FROM licenses
            ");
            $stmt->execute();
            $licenseStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Performance statistics
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(execution_time) as avg_execution_time,
                    AVG(memory_usage) as avg_memory_usage,
                    AVG(query_count) as avg_query_count,
                    SUM(slow_queries) as total_slow_queries,
                    SUM(error_count) as total_errors
                FROM performance_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $performanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Usage statistics
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(active_users) as avg_active_users,
                    SUM(total_applications) as total_applications,
                    SUM(new_applications) as new_applications_today,
                    AVG(storage_used) as avg_storage_used,
                    AVG(bandwidth_used) as avg_bandwidth_used
                FROM usage_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $usageStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'license_stats' => $licenseStats,
                'performance_stats' => $performanceStats,
                'usage_stats' => $usageStats,
                'generated_at' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log validation
     */
    private function logValidation($licenseKey, $hardwareId, $domain, $version, $success) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO validation_logs (
                    license_key, hardware_id, domain, version, success,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $licenseKey,
                $hardwareId,
                $domain,
                $version,
                $success ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("Validation logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Log activation
     */
    private function logActivation($licenseKey, $hardwareId, $domain, $version) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activation_logs (
                    license_key, hardware_id, domain, version,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $licenseKey,
                $hardwareId,
                $domain,
                $version,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("Activation logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Log deactivation
     */
    private function logDeactivation($licenseKey, $hardwareId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO deactivation_logs (
                    license_key, hardware_id, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $licenseKey,
                $hardwareId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("Deactivation logging error: " . $e->getMessage());
        }
    }
}
?>
