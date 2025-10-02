-- Comprehensive Admissions Management System Database Schema
-- Production-ready with proper indexing and constraints

-- Users table for authentication and role management
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'admissions_officer', 'reviewer', 'student') DEFAULT 'student',
    department VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- Programs table for available academic programs
CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) UNIQUE NOT NULL,
    program_name VARCHAR(200) NOT NULL,
    department VARCHAR(100) NOT NULL,
    degree_level ENUM('undergraduate', 'graduate', 'phd', 'certificate') NOT NULL,
    duration_months INT NOT NULL,
    description TEXT,
    requirements TEXT,
    tuition_fee DECIMAL(10,2),
    application_fee DECIMAL(8,2),
    application_deadline DATE,
    start_date DATE,
    max_capacity INT,
    current_enrolled INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program_code (program_code),
    INDEX idx_department (department),
    INDEX idx_status (status)
);

-- Students table for student information
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    passport_number VARCHAR(50),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(50) NOT NULL,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_email (email),
    INDEX idx_nationality (nationality)
);

-- Applications table for student applications
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    program_id INT NOT NULL,
    application_number VARCHAR(20) UNIQUE NOT NULL,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'waitlisted', 'withdrawn') DEFAULT 'submitted',
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    notes TEXT,
    reviewer_id INT,
    reviewed_at TIMESTAMP NULL,
    decision_date TIMESTAMP NULL,
    decision_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_application_number (application_number),
    INDEX idx_status (status),
    INDEX idx_application_date (application_date),
    INDEX idx_student_program (student_id, program_id)
);

-- Application documents table
CREATE TABLE application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type ENUM('transcript', 'diploma', 'recommendation', 'statement', 'portfolio', 'test_score', 'other') NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_application_id (application_id),
    INDEX idx_document_type (document_type),
    INDEX idx_verified (verified)
);

-- Application reviews table
CREATE TABLE application_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_type ENUM('initial', 'secondary', 'final') NOT NULL,
    score INT CHECK (score >= 0 AND score <= 100),
    comments TEXT,
    recommendation ENUM('approve', 'reject', 'waitlist', 'additional_info') NOT NULL,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_application_id (application_id),
    INDEX idx_reviewer_id (reviewer_id),
    INDEX idx_review_type (review_type)
);

-- Academic records table
CREATE TABLE academic_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    institution_name VARCHAR(200) NOT NULL,
    degree VARCHAR(100) NOT NULL,
    field_of_study VARCHAR(100) NOT NULL,
    gpa DECIMAL(3,2),
    graduation_date DATE,
    transcript_file VARCHAR(500),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_verified (verified)
);

-- Test scores table
CREATE TABLE test_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_type ENUM('SAT', 'ACT', 'GRE', 'GMAT', 'TOEFL', 'IELTS', 'other') NOT NULL,
    score_value VARCHAR(50) NOT NULL,
    test_date DATE NOT NULL,
    score_report_file VARCHAR(500),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_test_type (test_type),
    INDEX idx_verified (verified)
);

-- Communications log table
CREATE TABLE communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT,
    sender_id INT NOT NULL,
    recipient_id INT,
    communication_type ENUM('email', 'phone', 'in_person', 'system') NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_application_id (application_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_sent_at (sent_at)
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
);

-- Audit log table for tracking changes
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, first_name, last_name, role, status) 
VALUES ('admin', 'admin@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin', 'active');

-- Insert sample programs
INSERT INTO programs (program_code, program_name, department, degree_level, duration_months, description, requirements, tuition_fee, application_fee, application_deadline, start_date, max_capacity) VALUES
('CS-BS', 'Bachelor of Science in Computer Science', 'Computer Science', 'undergraduate', 48, 'Comprehensive computer science program covering programming, algorithms, and software engineering.', 'High school diploma, Math and Science prerequisites', 45000.00, 50.00, '2024-03-01', '2024-09-01', 100),
('MBA-FT', 'Master of Business Administration (Full-time)', 'Business School', 'graduate', 24, 'Full-time MBA program focusing on leadership and strategic management.', 'Bachelor degree, GMAT/GRE scores, Work experience', 65000.00, 75.00, '2024-02-15', '2024-08-01', 60),
('ENG-MS', 'Master of Science in Engineering', 'Engineering', 'graduate', 18, 'Advanced engineering program with specialization options.', 'Bachelor in Engineering, GRE scores', 55000.00, 60.00, '2024-04-01', '2024-09-01', 40);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'University Admissions Management System', 'Name of the admissions management system'),
('application_fee_default', '50.00', 'Default application fee amount'),
('max_file_upload_size', '10485760', 'Maximum file upload size in bytes (10MB)'),
('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png', 'Allowed file types for document uploads'),
('email_notifications', 'true', 'Enable email notifications'),
('auto_application_number', 'true', 'Automatically generate application numbers'),
('review_required', '2', 'Number of reviews required for application approval');
