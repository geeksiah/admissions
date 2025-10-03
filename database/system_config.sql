-- System Configuration Table
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_config (category, config_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default branding settings
INSERT INTO system_config (category, config_key, config_value) VALUES
('branding', 'institution_name', 'Admissions Management System'),
('branding', 'primary_color', '#667eea'),
('branding', 'secondary_color', '#764ba2'),
('branding', 'institution_email', ''),
('branding', 'institution_phone', ''),
('branding', 'institution_website', ''),
('branding', 'institution_address', ''),
('branding', 'logo_url', '')

ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Insert default email settings
INSERT INTO system_config (category, config_key, config_value) VALUES
('email', 'smtp_host', 'localhost'),
('email', 'smtp_port', '587'),
('email', 'smtp_username', ''),
('email', 'smtp_password', ''),
('email', 'smtp_encryption', 'tls'),
('email', 'from_email', 'noreply@university.edu'),
('email', 'from_name', 'Admissions Management System')

ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Insert default payment settings
INSERT INTO system_config (category, config_key, config_value) VALUES
('payment', 'currency', 'USD'),
('payment', 'paystack_public_key', ''),
('payment', 'paystack_secret_key', ''),
('payment', 'flutterwave_public_key', ''),
('payment', 'flutterwave_secret_key', ''),
('payment', 'stripe_public_key', ''),
('payment', 'stripe_secret_key', '')

ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;
