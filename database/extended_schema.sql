-- Extended Database Schema for Comprehensive Admissions Management System
-- Additional tables for payments, vouchers, offline applications, and advanced features

-- Payment transactions table
CREATE TABLE payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method ENUM('credit_card', 'debit_card', 'bank_transfer', 'cash', 'check', 'online') NOT NULL,
    payment_gateway VARCHAR(50),
    gateway_transaction_id VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    processed_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_application_id (application_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_date (payment_date)
);

-- Vouchers and waiver codes table
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_code VARCHAR(50) UNIQUE NOT NULL,
    pin VARCHAR(20) UNIQUE NOT NULL,
    serial VARCHAR(20) UNIQUE NOT NULL,
    voucher_type ENUM('percentage', 'fixed_amount', 'full_waiver') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    max_uses INT DEFAULT 1,
    used_count INT DEFAULT 0,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    applicable_programs JSON,
    applicable_users JSON,
    description TEXT,
    created_by INT NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_voucher_code (voucher_code),
    INDEX idx_pin (pin),
    INDEX idx_serial (serial),
    INDEX idx_status (status),
    INDEX idx_valid_dates (valid_from, valid_until)
);

-- Voucher usage tracking
CREATE TABLE voucher_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    application_id INT NOT NULL,
    used_by INT,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_voucher_id (voucher_id),
    INDEX idx_application_id (application_id)
);

-- Offline applications table
CREATE TABLE offline_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(20) UNIQUE NOT NULL,
    student_first_name VARCHAR(50) NOT NULL,
    student_last_name VARCHAR(50) NOT NULL,
    student_email VARCHAR(100),
    student_phone VARCHAR(20),
    program_id INT NOT NULL,
    application_date DATE NOT NULL,
    entry_method ENUM('walk_in', 'mail', 'fax', 'email', 'phone', 'other') NOT NULL,
    received_by INT NOT NULL,
    status ENUM('received', 'processing', 'converted', 'rejected') DEFAULT 'received',
    conversion_notes TEXT,
    converted_to_online INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (converted_to_online) REFERENCES applications(id) ON DELETE SET NULL,
    INDEX idx_application_number (application_number),
    INDEX idx_status (status),
    INDEX idx_entry_method (entry_method)
);

-- Offline application documents
CREATE TABLE offline_application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offline_application_id INT NOT NULL,
    document_type ENUM('transcript', 'diploma', 'recommendation', 'statement', 'portfolio', 'test_score', 'other') NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(500),
    physical_location VARCHAR(200),
    received_date DATE,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offline_application_id) REFERENCES offline_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_offline_application_id (offline_application_id),
    INDEX idx_document_type (document_type)
);

-- Email templates table
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    template_key VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_key (template_key),
    INDEX idx_is_active (is_active)
);

-- Email queue for batch processing
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(100),
    from_email VARCHAR(100),
    from_name VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    body_html TEXT,
    body_text TEXT,
    template_id INT,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_priority (priority)
);

-- SMS templates and queue
CREATE TABLE sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    template_key VARCHAR(50) UNIQUE NOT NULL,
    message TEXT NOT NULL,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_key (template_key),
    INDEX idx_is_active (is_active)
);

CREATE TABLE sms_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    template_id INT,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES sms_templates(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- Internal messaging system
CREATE TABLE internal_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT,
    recipient_role ENUM('admin', 'admissions_officer', 'reviewer', 'student', 'all') DEFAULT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('info', 'warning', 'error', 'success', 'urgent') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    is_important BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    application_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_recipient_role (recipient_role),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Document verification workflow
CREATE TABLE document_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_id INT NOT NULL,
    verifier_id INT NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected', 'requires_resubmission') DEFAULT 'pending',
    verification_notes TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES application_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (verifier_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_application_id (application_id),
    INDEX idx_verification_status (verification_status)
);

-- Advanced reporting and analytics
CREATE TABLE analytics_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    user_id INT,
    session_id VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Academic Levels Table
CREATE TABLE academic_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(100) NOT NULL,
    level_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_level_code (level_code),
    INDEX idx_is_active (is_active)
);

-- Application Requirements Table
CREATE TABLE application_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requirement_name VARCHAR(100) NOT NULL,
    requirement_type ENUM('document', 'qualification', 'experience', 'test_score', 'interview', 'portfolio', 'other') NOT NULL,
    description TEXT,
    is_mandatory BOOLEAN DEFAULT FALSE,
    program_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_requirement_type (requirement_type),
    INDEX idx_program_id (program_id),
    INDEX idx_is_mandatory (is_mandatory)
);

-- Bulk Operations Log Table
CREATE TABLE bulk_operations_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(50) NOT NULL,
    record_count INT NOT NULL,
    operation_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operation_type (operation_type),
    INDEX idx_created_at (created_at)
);

-- Receipts Table
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    payment_id INT,
    receipt_type ENUM('payment', 'refund', 'adjustment', 'other') DEFAULT 'payment',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    student_name VARCHAR(255) NOT NULL,
    student_email VARCHAR(255) NOT NULL,
    program_name VARCHAR(255),
    gateway_name VARCHAR(100),
    pdf_path VARCHAR(500),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT,
    FOREIGN KEY (payment_id) REFERENCES payment_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_payment_id (payment_id),
    INDEX idx_student_email (student_email),
    INDEX idx_generated_at (generated_at)
);

-- Receipt Actions Log Table
CREATE TABLE receipt_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE,
    INDEX idx_receipt_id (receipt_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- Email Verifications Table
CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    verification_token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_verification_token (verification_token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_verified_at (verified_at)
);

-- License management tables
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(500) UNIQUE NOT NULL,
    customer_id VARCHAR(100) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    license_type ENUM('basic', 'professional', 'enterprise') NOT NULL,
    max_users INT NOT NULL DEFAULT 10,
    max_applications INT NOT NULL DEFAULT 1000,
    expiry_date DATE NOT NULL,
    features JSON,
    hardware_id VARCHAR(255),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    deactivated_at TIMESTAMP NULL,
    last_validation TIMESTAMP NULL,
    last_heartbeat TIMESTAMP NULL,
    validation_count INT DEFAULT 0,
    heartbeat_count INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_customer_id (customer_id),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
);

-- License events table
CREATE TABLE license_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    hardware_id VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_hardware_id (hardware_id),
    INDEX idx_created_at (created_at)
);

-- Tampering logs table
CREATE TABLE tampering_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_type VARCHAR(100) NOT NULL,
    details TEXT,
    actual_value TEXT,
    expected_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

-- System configuration and settings
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    config_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_config_key (config_key),
    INDEX idx_is_public (is_public)
);

-- Notification providers configuration
CREATE TABLE notification_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) UNIQUE NOT NULL,
    provider_type ENUM('email', 'sms') NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    config_data JSON,
    test_mode BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    daily_limit INT DEFAULT 1000,
    monthly_limit INT DEFAULT 30000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_name (provider_name),
    INDEX idx_provider_type (provider_type),
    INDEX idx_is_active (is_active),
    INDEX idx_is_default (is_default)
);

-- Workflow stages and automation
CREATE TABLE workflow_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(100) NOT NULL,
    stage_key VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    auto_actions JSON,
    required_actions JSON,
    notification_template VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stage_key (stage_key),
    INDEX idx_is_active (is_active)
);

CREATE TABLE application_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    stage_id INT NOT NULL,
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completed_by INT,
    notes TEXT,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES workflow_stages(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_application_id (application_id),
    INDEX idx_stage_id (stage_id)
);

-- Payment gateway configurations
CREATE TABLE payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) UNIQUE NOT NULL,
    gateway_type ENUM('stripe', 'paypal', 'square', 'paystack', 'hubtel', 'flutterwave', 'razorpay', 'custom') NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    config_data JSON,
    test_mode BOOLEAN DEFAULT TRUE,
    supported_currencies JSON,
    supported_countries JSON,
    processing_fee_percentage DECIMAL(5,2) DEFAULT 0.00,
    processing_fee_fixed DECIMAL(10,2) DEFAULT 0.00,
    min_amount DECIMAL(10,2) DEFAULT 0.00,
    max_amount DECIMAL(10,2) DEFAULT 999999.99,
    webhook_url VARCHAR(500),
    return_url VARCHAR(500),
    cancel_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gateway_name (gateway_name),
    INDEX idx_gateway_type (gateway_type),
    INDEX idx_is_active (is_active),
    INDEX idx_is_default (is_default)
);

-- Insert default email templates
INSERT INTO email_templates (template_name, template_key, subject, body_html, body_text, variables) VALUES
('Application Submitted', 'application_submitted', 'Application Submitted Successfully - {application_number}', 
'<h2>Application Submitted Successfully</h2><p>Dear {student_name},</p><p>Your application has been submitted successfully with application number: <strong>{application_number}</strong></p><p>You can track your application status by logging into your account.</p>',
'Application Submitted Successfully\n\nDear {student_name},\n\nYour application has been submitted successfully with application number: {application_number}\n\nYou can track your application status by logging into your account.',
'["student_name", "application_number"]'),

('Application Approved', 'application_approved', 'Congratulations! Your Application Has Been Approved - {application_number}',
'<h2>Congratulations!</h2><p>Dear {student_name},</p><p>We are pleased to inform you that your application for {program_name} has been approved!</p><p>Application Number: <strong>{application_number}</strong></p><p>Next steps will be communicated to you shortly.</p>',
'Congratulations!\n\nDear {student_name},\n\nWe are pleased to inform you that your application for {program_name} has been approved!\n\nApplication Number: {application_number}\n\nNext steps will be communicated to you shortly.',
'["student_name", "application_number", "program_name"]'),

('Payment Required', 'payment_required', 'Payment Required for Application - {application_number}',
'<h2>Payment Required</h2><p>Dear {student_name},</p><p>Your application requires payment of ${amount} to proceed.</p><p>Application Number: <strong>{application_number}</strong></p><p>Please complete your payment through our secure payment portal.</p>',
'Payment Required\n\nDear {student_name},\n\nYour application requires payment of ${amount} to proceed.\n\nApplication Number: {application_number}\n\nPlease complete your payment through our secure payment portal.',
'["student_name", "application_number", "amount"]');

-- Insert default SMS templates
INSERT INTO sms_templates (template_name, template_key, message, variables) VALUES
('Application Submitted SMS', 'app_submitted_sms', 'Your application {application_number} has been submitted successfully. Track status at our website.',
'["application_number"]'),

('Payment Reminder SMS', 'payment_reminder_sms', 'Payment of ${amount} required for application {application_number}. Please complete payment to proceed.',
'["amount", "application_number"]');

-- Insert default workflow stages
INSERT INTO workflow_stages (stage_name, stage_key, description, auto_actions, required_actions) VALUES
('Application Received', 'application_received', 'Initial application submission', '["send_confirmation_email"]', '["review_documents"]'),
('Payment Processing', 'payment_processing', 'Payment verification and processing', '["verify_payment"]', '["confirm_payment"]'),
('Document Review', 'document_review', 'Review and verify submitted documents', '["assign_reviewer"]', '["verify_documents"]'),
('Academic Review', 'academic_review', 'Academic qualifications review', '["assign_academic_reviewer"]', '["review_academic_record"]'),
('Final Decision', 'final_decision', 'Final admission decision', '["send_decision_notification"]', '["make_decision"]');

-- Create fee structure table
CREATE TABLE fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_name VARCHAR(100) NOT NULL,
    fee_type ENUM('application', 'acceptance', 'tuition', 'late', 'processing', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    is_percentage BOOLEAN DEFAULT FALSE,
    percentage_of DECIMAL(10,2) DEFAULT NULL,
    program_id INT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    due_date DATE,
    late_fee_amount DECIMAL(10,2) DEFAULT 0.00,
    late_fee_grace_days INT DEFAULT 0,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_fee_type (fee_type),
    INDEX idx_program_id (program_id),
    INDEX idx_is_active (is_active)
);

-- Insert initial academic levels
INSERT INTO academic_levels (level_name, level_code, description, is_active, created_by) VALUES
('Undergraduate', 'UG', 'Bachelor degree programs', TRUE, 1),
('Postgraduate', 'PG', 'Master degree programs', TRUE, 1),
('Doctorate', 'PHD', 'PhD and doctoral programs', TRUE, 1),
('Diploma', 'DIP', 'Diploma programs', TRUE, 1),
('Certificate', 'CERT', 'Certificate programs', TRUE, 1);

-- Insert initial application requirements
INSERT INTO application_requirements (requirement_name, requirement_type, description, is_mandatory, program_id, created_by) VALUES
('High School Transcript', 'document', 'Official high school transcript or equivalent', TRUE, NULL, 1),
('Bachelor Degree Certificate', 'document', 'Official bachelor degree certificate for postgraduate programs', TRUE, NULL, 1),
('English Proficiency Test', 'test_score', 'IELTS, TOEFL, or equivalent English proficiency test results', TRUE, NULL, 1),
('Statement of Purpose', 'document', 'Personal statement explaining motivation and goals', TRUE, NULL, 1),
('Letters of Recommendation', 'document', 'At least two academic or professional recommendation letters', TRUE, NULL, 1),
('CV/Resume', 'document', 'Current curriculum vitae or resume', FALSE, NULL, 1),
('Portfolio', 'portfolio', 'Portfolio of work (for creative programs)', FALSE, NULL, 1),
('Interview', 'interview', 'Personal interview with admissions committee', FALSE, NULL, 1),
('Work Experience', 'experience', 'Relevant work experience documentation', FALSE, NULL, 1),
('Research Proposal', 'document', 'Research proposal for research-based programs', FALSE, NULL, 1);

-- Insert system configuration
INSERT INTO system_config (config_key, config_value, config_type, description, is_public) VALUES
('payment_enabled', 'true', 'boolean', 'Enable payment processing', true),
('offline_applications_enabled', 'true', 'boolean', 'Allow offline application processing', true),
('voucher_system_enabled', 'true', 'boolean', 'Enable voucher and waiver code system', true),
('email_notifications_enabled', 'true', 'boolean', 'Enable email notifications', true),
('sms_notifications_enabled', 'false', 'boolean', 'Enable SMS notifications', true),
('auto_assignment_enabled', 'true', 'boolean', 'Enable automatic reviewer assignment', false),
('document_verification_required', 'true', 'boolean', 'Require document verification before review', false),
('max_file_upload_size', '10485760', 'integer', 'Maximum file upload size in bytes', true),
('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png', 'string', 'Allowed file types for uploads', true),
('application_fee_default', '50.00', 'string', 'Default application fee amount', true),
('application_access_mode', 'payment', 'string', 'Application access mode: payment or voucher', true),
('voucher_required_for_application', 'false', 'boolean', 'Whether voucher is required to access application form', true),
('multiple_fee_structure', 'false', 'boolean', 'Whether multiple fee structure is enabled', true),
('payment_gateway_default', 'stripe', 'string', 'Default payment gateway', false),
('school_name', 'University Name', 'string', 'Name of the educational institution', true),
('school_address', '', 'string', 'Address of the educational institution', true),
('school_phone', '', 'string', 'Phone number of the educational institution', true),
('school_email', '', 'string', 'Email address of the educational institution', true),
('school_website', '', 'string', 'Website URL of the educational institution', true),
('copyright_text', '© 2024 University Name. All rights reserved.', 'string', 'Copyright text for the application', true),
('application_theme_color', '#007bff', 'string', 'Primary theme color for the application', true),
('application_logo', '', 'string', 'URL of the application logo', true);

-- Insert default payment gateways
INSERT INTO payment_gateways (gateway_name, gateway_type, display_name, description, is_active, is_default, config_data, test_mode, supported_currencies, supported_countries, processing_fee_percentage, processing_fee_fixed, min_amount, max_amount) VALUES
('Stripe', 'stripe', 'Stripe', 'Accept payments from customers worldwide with Stripe', true, true, '{"publishable_key": "", "secret_key": "", "webhook_secret": ""}', true, '["USD", "EUR", "GBP", "CAD", "AUD"]', '["US", "CA", "GB", "AU", "DE", "FR"]', 2.90, 0.30, 0.50, 999999.99),
('PayPal', 'paypal', 'PayPal', 'Accept payments via PayPal', false, false, '{"client_id": "", "client_secret": "", "webhook_id": ""}', true, '["USD", "EUR", "GBP", "CAD", "AUD"]', '["US", "CA", "GB", "AU", "DE", "FR"]', 2.90, 0.30, 0.50, 999999.99),
('Square', 'square', 'Square', 'Accept payments with Square', false, false, '{"application_id": "", "access_token": "", "webhook_signature_key": ""}', true, '["USD", "CAD", "GBP", "AUD"]', '["US", "CA", "GB", "AU"]', 2.90, 0.30, 0.50, 999999.99),
('Paystack', 'paystack', 'Paystack', 'Accept payments in Africa with Paystack', false, false, '{"public_key": "", "secret_key": "", "webhook_secret": ""}', true, '["NGN", "GHS", "ZAR", "KES", "USD"]', '["NG", "GH", "ZA", "KE", "US"]', 1.50, 0.00, 1.00, 999999.99),
('Hubtel', 'hubtel', 'Hubtel', 'Accept payments in Ghana with Hubtel', false, false, '{"client_id": "", "client_secret": "", "webhook_secret": ""}', true, '["GHS", "USD"]', '["GH", "US"]', 1.50, 0.00, 1.00, 999999.99),
('Flutterwave', 'flutterwave', 'Flutterwave', 'Accept payments across Africa with Flutterwave', false, false, '{"public_key": "", "secret_key": "", "webhook_secret": ""}', true, '["NGN", "GHS", "ZAR", "KES", "USD", "EUR", "GBP"]', '["NG", "GH", "ZA", "KE", "US", "GB", "DE"]', 1.50, 0.00, 1.00, 999999.99),
('Razorpay', 'razorpay', 'Razorpay', 'Accept payments in India with Razorpay', false, false, '{"key_id": "", "key_secret": "", "webhook_secret": ""}', true, '["INR", "USD", "EUR", "GBP"]', '["IN", "US", "GB", "DE"]', 2.00, 0.00, 1.00, 999999.99);

-- Insert default notification providers
INSERT INTO notification_providers (provider_name, provider_type, display_name, description, is_active, is_default, config_data, test_mode, priority, daily_limit, monthly_limit) VALUES
-- Email Providers
('smtp', 'email', 'SMTP Server', 'Send emails via SMTP server', true, true, '{"host": "smtp.gmail.com", "port": 587, "username": "", "password": "", "encryption": "tls", "from_email": "noreply@university.edu", "from_name": "University Admissions"}', true, 1, 1000, 30000),
('sendgrid', 'email', 'SendGrid', 'Send emails via SendGrid API', false, false, '{"api_key": "", "from_email": "noreply@university.edu", "from_name": "University Admissions"}', true, 2, 1000, 30000),
('mailgun', 'email', 'Mailgun', 'Send emails via Mailgun API', false, false, '{"api_key": "", "domain": "", "from_email": "noreply@university.edu", "from_name": "University Admissions"}', true, 3, 1000, 30000),
('ses', 'email', 'Amazon SES', 'Send emails via Amazon SES', false, false, '{"access_key": "", "secret_key": "", "region": "us-east-1", "from_email": "noreply@university.edu", "from_name": "University Admissions"}', true, 4, 1000, 30000),

-- SMS Providers
('twilio', 'sms', 'Twilio', 'Send SMS via Twilio API', false, true, '{"account_sid": "", "auth_token": "", "from_number": "+1234567890"}', true, 1, 1000, 30000),
('nexmo', 'sms', 'Vonage (Nexmo)', 'Send SMS via Vonage API', false, false, '{"api_key": "", "api_secret": "", "from_number": "University"}', true, 2, 1000, 30000),
('africastalking', 'sms', 'Africa\'s Talking', 'Send SMS via Africa\'s Talking API', false, false, '{"username": "", "api_key": "", "from_number": "University"}', true, 3, 1000, 30000),
('hubtel', 'sms', 'Hubtel SMS', 'Send SMS via Hubtel API', false, false, '{"client_id": "", "client_secret": "", "from_number": "University"}', true, 4, 1000, 30000),
('bulksms', 'sms', 'BulkSMS', 'Send SMS via BulkSMS API', false, false, '{"username": "", "password": "", "from_number": "University"}', true, 5, 1000, 30000);
