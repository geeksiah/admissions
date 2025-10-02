<?php
/**
 * System Configuration Model
 * Manages system-wide configuration settings
 */

class SystemConfig {
    private $db;
    private $cache = [];
    
    public function __construct($database) {
        // Handle both Database object and PDO connection
        if ($database instanceof PDO) {
            $this->db = $database;
        } else {
            $this->db = $database->getConnection();
        }
    }
    
    /**
     * Get configuration value by key
     */
    public function get($key, $default = null) {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT config_value, config_type 
                FROM system_config 
                WHERE config_key = ?
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                $value = $this->castValue($result['config_value'], $result['config_type']);
                $this->cache[$key] = $value;
                return $value;
            }
            
            return $default;
        } catch (Exception $e) {
            error_log("SystemConfig get error: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Set configuration value
     */
    public function set($key, $value, $type = 'string', $description = null, $isPublic = false, $updatedBy = null) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO system_config (config_key, config_value, config_type, description, is_public, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                config_type = VALUES(config_type),
                description = COALESCE(VALUES(description), description),
                is_public = VALUES(is_public),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([
                $key,
                $this->formatValue($value, $type),
                $type,
                $description,
                $isPublic ? 1 : 0,
                $updatedBy
            ]);
            
            if ($result) {
                // Update cache
                $this->cache[$key] = $value;
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("SystemConfig set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all configuration settings
     */
    public function getAll($publicOnly = false) {
        try {
            $sql = "SELECT * FROM system_config";
            $params = [];
            
            if ($publicOnly) {
                $sql .= " WHERE is_public = 1";
            }
            
            $sql .= " ORDER BY config_key";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            $config = [];
            foreach ($results as $row) {
                $config[$row['config_key']] = [
                    'value' => $this->castValue($row['config_value'], $row['config_type']),
                    'type' => $row['config_type'],
                    'description' => $row['description'],
                    'is_public' => (bool)$row['is_public'],
                    'updated_at' => $row['updated_at']
                ];
            }
            
            return $config;
        } catch (Exception $e) {
            error_log("SystemConfig getAll error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get public configuration settings
     */
    public function getPublic() {
        return $this->getAll(true);
    }
    
    /**
     * Delete configuration setting
     */
    public function delete($key) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM system_config WHERE config_key = ?
            ");
            $result = $stmt->execute([$key]);
            
            if ($result) {
                // Remove from cache
                unset($this->cache[$key]);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("SystemConfig delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if application access requires voucher
     */
    public function isVoucherRequired() {
        return $this->get('voucher_required_for_application', false);
    }
    
    /**
     * Get application access mode
     */
    public function getApplicationAccessMode() {
        return $this->get('application_access_mode', 'payment');
    }
    
    /**
     * Check if payment is enabled
     */
    public function isPaymentEnabled() {
        return $this->get('payment_enabled', true);
    }
    
    /**
     * Check if voucher system is enabled
     */
    public function isVoucherSystemEnabled() {
        return $this->get('voucher_system_enabled', true);
    }
    
    /**
     * Check if multiple fee structure is enabled
     */
    public function isMultipleFeeStructureEnabled() {
        return $this->get('multiple_fee_structure', false);
    }
    
    /**
     * Get default application fee
     */
    public function getDefaultApplicationFee() {
        return (float)$this->get('application_fee_default', 50.00);
    }
    
    /**
     * Get maximum file upload size
     */
    public function getMaxFileUploadSize() {
        return (int)$this->get('max_file_upload_size', 10485760);
    }
    
    /**
     * Get allowed file types
     */
    public function getAllowedFileTypes() {
        $types = $this->get('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png');
        return explode(',', $types);
    }
    
    /**
     * Check if email notifications are enabled
     */
    public function isEmailNotificationsEnabled() {
        return $this->get('email_notifications_enabled', true);
    }
    
    /**
     * Check if SMS notifications are enabled
     */
    public function isSMSNotificationsEnabled() {
        return $this->get('sms_notifications_enabled', false);
    }
    
    /**
     * Get application deadline reminder days
     */
    public function getApplicationDeadlineReminderDays() {
        $days = $this->get('application_deadline_reminder_days', '7,3,1');
        return array_map('intval', explode(',', $days));
    }
    
    /**
     * Get payment reminder days
     */
    public function getPaymentReminderDays() {
        $days = $this->get('payment_reminder_days', '7,3,1');
        return array_map('intval', explode(',', $days));
    }
    
    /**
     * Cast value to appropriate type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'json':
                return json_decode($value, true);
            case 'array':
                return explode(',', $value);
            default:
                return $value;
        }
    }
    
    /**
     * Format value for storage
     */
    private function formatValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return json_encode($value);
            case 'array':
                return is_array($value) ? implode(',', $value) : $value;
            default:
                return (string)$value;
        }
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Get configuration for admin interface
     */
    public function getAdminConfig() {
        return [
            'application_access_mode' => $this->getApplicationAccessMode(),
            'voucher_required_for_application' => $this->isVoucherRequired(),
            'payment_enabled' => $this->isPaymentEnabled(),
            'voucher_system_enabled' => $this->isVoucherSystemEnabled(),
            'multiple_fee_structure' => $this->isMultipleFeeStructureEnabled(),
            'email_notifications_enabled' => $this->isEmailNotificationsEnabled(),
            'sms_notifications_enabled' => $this->isSMSNotificationsEnabled(),
            'default_application_fee' => $this->getDefaultApplicationFee(),
            'max_file_upload_size' => $this->getMaxFileUploadSize(),
            'allowed_file_types' => $this->getAllowedFileTypes()
        ];
    }
}
