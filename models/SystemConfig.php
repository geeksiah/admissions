<?php
/**
 * System Configuration Model
 * Handles system settings and configuration
 */

class SystemConfig {
    private $db;
    
    public function __construct($database) {
        // Handle both Database object and PDO connection
        if ($database instanceof PDO) {
            $this->db = $database;
        } else {
            $this->db = $database->getConnection();
        }
    }
    
    /**
     * Get configuration by category
     */
    public function getByCategory($category) {
        try {
            $stmt = $this->db->prepare("
                SELECT config_key, config_value 
                FROM system_config 
                WHERE category = ?
            ");
            $stmt->execute([$category]);
            $results = $stmt->fetchAll();
            
            $config = [];
            foreach ($results as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            
            return $config;
        } catch (Exception $e) {
            error_log("System config error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set configuration value
     */
    public function set($category, $key, $value) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_config (category, config_key, config_value, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value), 
                updated_at = NOW()
            ");
            return $stmt->execute([$category, $key, $value]);
        } catch (Exception $e) {
            error_log("System config set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all configuration
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT category, config_key, config_value, updated_at 
                FROM system_config 
                ORDER BY category, config_key
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("System config get all error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete configuration
     */
    public function delete($category, $key) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM system_config 
                WHERE category = ? AND config_key = ?
            ");
            return $stmt->execute([$category, $key]);
        } catch (Exception $e) {
            error_log("System config delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get branding settings
     */
    public function getBrandingSettings() {
        $branding = $this->getByCategory('branding');
        
        // Set defaults if not exists
        $defaults = [
            'logo_url' => null,
            'admin_avatar' => null,
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'institution_name' => APP_NAME,
            'institution_address' => '',
            'institution_phone' => '',
            'institution_email' => '',
            'institution_website' => ''
        ];
        
        return array_merge($defaults, $branding);
    }
    
    /**
     * Save branding settings
     */
    public function saveBrandingSettings($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!$this->set('branding', $key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get email settings
     */
    public function getEmailSettings() {
        $email = $this->getByCategory('email');
        
        $defaults = [
            'smtp_host' => 'localhost',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => 'noreply@university.edu',
            'from_name' => APP_NAME
        ];
        
        return array_merge($defaults, $email);
    }
    
    /**
     * Save email settings
     */
    public function saveEmailSettings($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!$this->set('email', $key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get payment settings
     */
    public function getPaymentSettings() {
        $payment = $this->getByCategory('payment');
        
        $defaults = [
            'currency' => 'USD',
            'currency_symbol' => '$',
            'currency_position' => 'before', // before, after
            'decimal_places' => '2',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'paystack_public_key' => '',
            'paystack_secret_key' => '',
            'flutterwave_public_key' => '',
            'flutterwave_secret_key' => '',
            'stripe_public_key' => '',
            'stripe_secret_key' => ''
        ];
        
        return array_merge($defaults, $payment);
    }
    
    /**
     * Get currency settings
     */
    public function getCurrencySettings() {
        $currency = $this->getByCategory('currency');
        
        $defaults = [
            'default_currency' => 'USD',
            'available_currencies' => 'USD,EUR,GBP,NGN',
            'exchange_rate_api' => 'free', // free, premium
            'auto_update_rates' => '1',
            'rate_update_frequency' => 'daily' // daily, weekly, monthly
        ];
        
        return array_merge($defaults, $currency);
    }
    
    /**
     * Save currency settings
     */
    public function saveCurrencySettings($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!$this->set('currency', $key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Save payment settings
     */
    public function savePaymentSettings($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!$this->set('payment', $key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
}
?>