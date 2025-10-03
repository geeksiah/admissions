-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    channels JSON NOT NULL,
    status ENUM('pending', 'delivered', 'failed') DEFAULT 'pending',
    results JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_user_read (user_id, is_read),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample notification types configuration
INSERT INTO system_config (category, config_key, config_value) VALUES
('notifications', 'email_enabled', '1'),
('notifications', 'sms_enabled', '0'),
('notifications', 'push_enabled', '1'),
('notifications', 'auto_notifications', '1'),
('notifications', 'notification_templates', '{
    "application_submitted": {
        "enabled": true,
        "channels": ["email", "push"]
    },
    "application_approved": {
        "enabled": true,
        "channels": ["email", "sms", "push"]
    },
    "application_rejected": {
        "enabled": true,
        "channels": ["email", "push"]
    },
    "payment_required": {
        "enabled": true,
        "channels": ["email", "sms"]
    },
    "payment_received": {
        "enabled": true,
        "channels": ["email", "push"]
    },
    "deadline_reminder": {
        "enabled": true,
        "channels": ["email", "sms"]
    }
}')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

-- SMS settings
INSERT INTO system_config (category, config_key, config_value) VALUES
('sms', 'provider', 'twilio'),
('sms', 'api_key', ''),
('sms', 'api_secret', ''),
('sms', 'sender_id', 'UNIVERSITY'),
('sms', 'enabled', '0')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
