# Comprehensive Admissions Management System - Developer Guide

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Installation & Setup](#installation--setup)
3. [Database Schema](#database-schema)
4. [Code Structure](#code-structure)
5. [Security Implementation](#security-implementation)
6. [API Development](#api-development)
7. [Testing](#testing)
8. [Performance Optimization](#performance-optimization)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

## System Architecture

### Overview
The Admissions Management System follows a **Model-View-Controller (MVC)** architecture pattern with the following components:

```
├── config/          # Configuration files
├── classes/         # Core business logic classes
├── models/          # Data access layer (MVC Models)
├── admin/           # Admin interface (MVC Views)
├── student/         # Student interface (MVC Views)
├── api/             # RESTful API endpoints
├── includes/        # Shared components
├── assets/          # Static assets (CSS, JS, images)
├── uploads/         # File uploads
├── database/        # Database schema and migrations
├── tests/           # Unit and integration tests
└── docs/            # Documentation
```

### Technology Stack
- **Backend**: PHP 8.1+ (Pure PHP, no frameworks)
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Bootstrap 5 + Custom CSS + Vanilla JavaScript
- **Security**: CSRF protection, XSS prevention, SQL injection prevention
- **File Storage**: Local filesystem (cPanel compatible)
- **Email**: PHPMailer with SMTP support
- **SMS**: Multiple provider support (Twilio, etc.)

## Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite
- cPanel/shared hosting compatible

### Required PHP Extensions
```bash
# Check required extensions
php -m | grep -E "(mysqli|pdo_mysql|gd|mbstring|curl|zip|openssl|json|fileinfo)"
```

### Installation Steps

1. **Upload Files**
   ```bash
   # Upload to web directory
   scp -r admissions-management/ user@server:/public_html/
   ```

2. **Set Permissions**
   ```bash
   chmod 755 uploads/ backups/ logs/ cache/
   chmod 644 .htaccess config/*.php
   ```

3. **Run Web Installer**
   ```
   Navigate to: https://yourdomain.com/install
   ```

4. **Configure Cron Jobs**
   ```bash
   # Add to cPanel cron jobs
   */5 * * * * php /home/username/public_html/cron.php
   ```

## Database Schema

### Core Tables

#### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'admissions_officer', 'reviewer', 'student') DEFAULT 'student',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Applications Table
```sql
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    program_id INT NOT NULL,
    application_number VARCHAR(20) UNIQUE NOT NULL,
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'waitlisted') DEFAULT 'submitted',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);
```

### Extended Tables
The system includes extended tables for advanced features:
- `payment_transactions` - Payment processing
- `vouchers` - Voucher system
- `email_queue` - Email notifications
- `sms_queue` - SMS notifications
- `system_config` - System configuration
- `audit_logs` - Security audit trails

## Code Structure

### Model Classes
All models extend a base pattern and include:

```php
class ModelName {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    // CRUD operations
    public function create($data) { }
    public function getById($id) { }
    public function getAll($filters = [], $page = 1, $limit = 20) { }
    public function update($id, $data) { }
    public function delete($id) { }
}
```

### Security Implementation
The Security class provides:

```php
class Security {
    // Authentication
    public function authenticate($username, $password) { }
    public function isAuthenticated() { }
    
    // Password handling
    public function hashPassword($password) { }
    public function verifyPassword($password, $hash) { }
    
    // CSRF protection
    public function generateCSRFToken() { }
    public function validateCSRFToken($token) { }
    
    // Input sanitization
    public function sanitizeInput($input) { }
    
    // Rate limiting
    public function checkRateLimit($identifier, $maxAttempts, $timeWindow) { }
}
```

### Helper Functions
Global helper functions in `config/config.php`:

```php
// Authentication helpers
function isLoggedIn() { }
function requireRole($roles) { }
function redirect($url) { }

// Security helpers
function validateCSRFToken($token) { }
function sanitizeInput($input) { }
function generateCSRFToken() { }

// Utility helpers
function formatDate($date, $format = 'Y-m-d') { }
function formatCurrency($amount, $currency = 'USD') { }
```

## API Development

### Endpoint Structure
API endpoints follow RESTful conventions:

```
GET    /api/resource          # List resources
GET    /api/resource/{id}     # Get specific resource
POST   /api/resource          # Create resource
PUT    /api/resource/{id}     # Update resource
DELETE /api/resource/{id}     # Delete resource
```

### Response Format
All API responses use consistent format:

```json
{
    "status": "success|error",
    "message": "Human readable message",
    "data": "Response data or null",
    "timestamp": "2024-01-01T12:00:00+00:00"
}
```

### Error Handling
```php
function apiResponse($data = null, $status = 200, $message = 'Success') {
    http_response_code($status);
    echo json_encode([
        'status' => $status < 400 ? 'success' : 'error',
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}
```

## Testing

### Running Tests
```bash
# Run all tests
php tests/TestRunner.php

# Run specific test
php tests/UserModelTest.php
```

### Test Structure
```php
class ModelTest extends TestCase {
    protected function setUp() {
        // Setup test data
    }
    
    protected function test() {
        // Test methods
        $this->testCreate();
        $this->testRead();
        $this->testUpdate();
        $this->testDelete();
    }
    
    protected function tearDown() {
        // Cleanup test data
    }
}
```

### Test Database
Tests use a separate test database:
```php
class TestDatabase extends Database {
    public function __construct() {
        $this->db_name = 'admissions_management_test';
        $this->connect();
    }
}
```

## Performance Optimization

### Database Optimization
- Use prepared statements for all queries
- Implement proper indexing
- Use pagination for large datasets
- Cache frequently accessed data

### Caching Strategy
```php
// File-based caching
$cacheFile = 'cache/data_' . md5($key) . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
    return json_decode(file_get_contents($cacheFile), true);
}
```

### Performance Monitoring
```php
$monitor = new PerformanceMonitor();
$monitor->startQuery($sql);
// ... execute query ...
$monitor->endQuery($sql, $resultCount);
$monitor->logPerformance('page_load');
```

## Deployment

### Production Checklist
- [ ] Update `APP_DEBUG` to `false`
- [ ] Set secure database credentials
- [ ] Configure email/SMS settings
- [ ] Set up SSL certificate
- [ ] Configure backup system
- [ ] Set up monitoring
- [ ] Test all functionality

### Environment Configuration
```php
// Production settings
define('APP_DEBUG', false);
define('APP_URL', 'https://yourdomain.com');
define('DB_HOST', 'localhost');
define('DB_NAME', 'production_db');
define('DB_USER', 'secure_user');
define('DB_PASS', 'secure_password');
```

### Backup Strategy
```bash
# Automated daily backups
0 2 * * * php /path/to/cron/backup-cron.php

# Manual backup
php admin/backup-management.php
```

## Troubleshooting

### Common Issues

#### Database Connection Errors
```php
// Check database credentials
$pdo = new PDO($dsn, $username, $password, $options);
```

#### File Upload Issues
```php
// Check file permissions
chmod 755 uploads/
chmod 644 uploads/*.php
```

#### Email Not Sending
```php
// Test SMTP configuration
$mailer = new PHPMailer();
$mailer->isSMTP();
$mailer->Host = SMTP_HOST;
$mailer->SMTPAuth = true;
$mailer->Username = SMTP_USERNAME;
$mailer->Password = SMTP_PASSWORD;
```

### Debug Mode
Enable debug mode for development:
```php
define('APP_DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Files
Check log files for errors:
- `logs/error.log` - PHP errors
- `logs/performance.log` - Performance metrics
- `logs/security.log` - Security events
- `logs/cron.log` - Cron job logs

### Performance Issues
1. Check database query performance
2. Monitor memory usage
3. Review file upload limits
4. Check server resources

## Contributing

### Code Standards
- Follow PSR-12 coding standards
- Use meaningful variable names
- Add comprehensive comments
- Write unit tests for new features

### Git Workflow
```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes and commit
git add .
git commit -m "Add new feature"

# Push and create pull request
git push origin feature/new-feature
```

### Documentation
- Update API documentation for new endpoints
- Add inline comments for complex logic
- Update README for new features
- Maintain changelog

## Support

For developer support:
- Email: dev-support@yourdomain.com
- Documentation: https://yourdomain.com/docs
- Issue Tracker: https://github.com/yourorg/admissions-management/issues
