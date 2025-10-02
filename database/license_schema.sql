-- License Management Database Schema
-- For the central license server

CREATE DATABASE IF NOT EXISTS license_management;
USE license_management;

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) UNIQUE NOT NULL,
    customer_phone VARCHAR(50),
    company_name VARCHAR(255),
    address TEXT,
    country VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_email (customer_email),
    INDEX idx_company_name (company_name)
);

-- Licenses table
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    license_type ENUM('basic', 'professional', 'enterprise') NOT NULL,
    max_users INT NOT NULL DEFAULT 10,
    max_applications INT NOT NULL DEFAULT 1000,
    expiry_date DATE NOT NULL,
    features JSON,
    allowed_domains JSON,
    hardware_id VARCHAR(255),
    domain VARCHAR(255),
    version VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    deactivated_at TIMESTAMP NULL,
    last_validation TIMESTAMP NULL,
    last_heartbeat TIMESTAMP NULL,
    validation_count INT DEFAULT 0,
    heartbeat_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_license_key (license_key),
    INDEX idx_customer_id (customer_id),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
);

-- Validation logs
CREATE TABLE validation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) NOT NULL,
    hardware_id VARCHAR(255),
    domain VARCHAR(255),
    version VARCHAR(20),
    success BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at)
);

-- Activation logs
CREATE TABLE activation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) NOT NULL,
    hardware_id VARCHAR(255),
    domain VARCHAR(255),
    version VARCHAR(20),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Deactivation logs
CREATE TABLE deactivation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) NOT NULL,
    hardware_id VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Performance logs
CREATE TABLE performance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) NOT NULL,
    hardware_id VARCHAR(255),
    domain VARCHAR(255),
    version VARCHAR(20),
    execution_time DECIMAL(10,4),
    memory_usage BIGINT,
    query_count INT,
    slow_queries INT,
    error_count INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Usage logs
CREATE TABLE usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) NOT NULL,
    hardware_id VARCHAR(255),
    domain VARCHAR(255),
    version VARCHAR(20),
    active_users INT,
    total_applications INT,
    new_applications INT,
    storage_used BIGINT,
    bandwidth_used BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Tampering logs
CREATE TABLE tampering_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500),
    hardware_id VARCHAR(255),
    attempt_type VARCHAR(100) NOT NULL,
    details TEXT,
    actual_value TEXT,
    expected_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_attempt_type (attempt_type),
    INDEX idx_timestamp (timestamp)
);

-- File integrity table
CREATE TABLE file_integrity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(128) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_file_path (file_path),
    INDEX idx_status (status)
);

-- License events
CREATE TABLE license_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500),
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    hardware_id VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_event_type (event_type),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Insert sample customer
INSERT INTO customers (customer_name, customer_email, customer_phone, company_name, address, country) VALUES
('Sample University', 'admin@sampleuniversity.edu', '+1234567890', 'Sample University', '123 University Ave, City, State', 'United States');

-- Insert sample license
INSERT INTO licenses (
    license_key, customer_id, customer_name, license_type, max_users, max_applications,
    expiry_date, features, status
) VALUES (
    'SAMPLE-LICENSE-KEY-12345', 1, 'Sample University', 'professional', 50, 10000,
    DATE_ADD(NOW(), INTERVAL 1 YEAR), '["vouchers", "offline_apps", "api", "reports"]', 'active'
);
