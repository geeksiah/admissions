<?php
/**
 * Test Bootstrap
 * Initialize testing environment
 */

// Set test environment
define('APP_ENV', 'testing');
define('APP_DEBUG', true);

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Create test database connection
class TestDatabase extends Database {
    public function __construct() {
        $this->host = 'localhost';
        $this->db_name = 'admissions_management_test';
        $this->username = 'root';
        $this->password = '';
        $this->charset = 'utf8mb4';
        $this->connect();
    }
}

// Test helper functions
function createTestUser($role = 'student') {
    global $database;
    $userModel = new User($database);
    
    $userData = [
        'username' => 'test_user_' . uniqid(),
        'email' => 'test_' . uniqid() . '@example.com',
        'password_hash' => password_hash('testpassword', PASSWORD_DEFAULT),
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => $role,
        'status' => 'active'
    ];
    
    return $userModel->create($userData);
}

function createTestStudent() {
    global $database;
    $studentModel = new Student($database);
    
    $studentData = [
        'user_id' => createTestUser('student'),
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'test_student_' . uniqid() . '@example.com',
        'phone' => '+1234567890',
        'date_of_birth' => '1995-01-01',
        'nationality' => 'US',
        'address' => '123 Test Street'
    ];
    
    return $studentModel->create($studentData);
}

function createTestProgram() {
    global $database;
    $programModel = new Program($database);
    
    $programData = [
        'program_name' => 'Test Program ' . uniqid(),
        'program_code' => 'TEST' . uniqid(),
        'level_name' => 'Undergraduate',
        'department' => 'Computer Science',
        'description' => 'Test program description',
        'requirements' => 'Test requirements',
        'duration' => 48,
        'credits' => 120,
        'application_fee' => 50.00,
        'is_active' => 1,
        'created_by' => 1
    ];
    
    return $programModel->create($programData);
}

function cleanupTestData() {
    global $database;
    
    // Clean up test data
    $tables = ['applications', 'students', 'users', 'programs'];
    foreach ($tables as $table) {
        $database->getConnection()->exec("DELETE FROM $table WHERE email LIKE '%test_%' OR username LIKE '%test_%'");
    }
}

// Initialize test database
$database = new TestDatabase();
?>
