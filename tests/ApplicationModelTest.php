<?php
/**
 * Application Model Tests
 */

require_once 'TestCase.php';
require_once '../models/Application.php';
require_once '../models/Student.php';
require_once '../models/Program.php';

class ApplicationModelTest extends TestCase {
    private $applicationModel;
    private $studentModel;
    private $programModel;
    private $testApplicationId;
    private $testStudentId;
    private $testProgramId;
    
    protected function setUp() {
        $this->applicationModel = new Application($this->database);
        $this->studentModel = new Student($this->database);
        $this->programModel = new Program($this->database);
        
        // Create test student
        $studentData = [
            'user_id' => createTestUser('student'),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test_student_' . uniqid() . '@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1995-01-01',
            'nationality' => 'US'
        ];
        $this->testStudentId = $this->studentModel->create($studentData);
        
        // Create test program
        $programData = [
            'program_name' => 'Test Program ' . uniqid(),
            'program_code' => 'TEST' . uniqid(),
            'level_name' => 'Undergraduate',
            'department' => 'Computer Science',
            'description' => 'Test program',
            'requirements' => 'Test requirements',
            'duration' => 48,
            'credits' => 120,
            'application_fee' => 50.00,
            'is_active' => 1,
            'created_by' => 1
        ];
        $this->testProgramId = $this->programModel->create($programData);
    }
    
    protected function test() {
        $this->testCreateApplication();
        $this->testGetApplicationById();
        $this->testUpdateApplication();
        $this->testGetApplicationsByStudent();
        $this->testGetApplicationsByProgram();
        $this->testDeleteApplication();
    }
    
    private function testCreateApplication() {
        $applicationData = [
            'student_id' => $this->testStudentId,
            'program_id' => $this->testProgramId,
            'status' => 'submitted',
            'priority' => 'medium',
            'notes' => 'Test application'
        ];
        
        $result = $this->applicationModel->create($applicationData);
        $this->assertTrue($result !== false, 'Application creation should succeed');
        
        if ($result) {
            $this->testApplicationId = $result;
        }
    }
    
    private function testGetApplicationById() {
        if (!$this->testApplicationId) {
            $this->failures[] = 'Cannot test getById - no test application created';
            return;
        }
        
        $application = $this->applicationModel->getById($this->testApplicationId);
        $this->assertNotNull($application, 'Application should be found by ID');
        $this->assertEquals($this->testApplicationId, $application['id'], 'Application ID should match');
        $this->assertEquals('submitted', $application['status'], 'Status should match');
    }
    
    private function testUpdateApplication() {
        if (!$this->testApplicationId) {
            $this->failures[] = 'Cannot test update - no test application created';
            return;
        }
        
        $updateData = [
            'status' => 'under_review',
            'notes' => 'Updated notes'
        ];
        
        $result = $this->applicationModel->update($this->testApplicationId, $updateData);
        $this->assertTrue($result, 'Application update should succeed');
        
        $application = $this->applicationModel->getById($this->testApplicationId);
        $this->assertEquals('under_review', $application['status'], 'Status should be updated');
    }
    
    private function testGetApplicationsByStudent() {
        if (!$this->testStudentId) {
            $this->failures[] = 'Cannot test getByStudent - no test student created';
            return;
        }
        
        $applications = $this->applicationModel->getByStudentId($this->testStudentId);
        $this->assertIsArray($applications, 'getByStudentId should return an array');
    }
    
    private function testGetApplicationsByProgram() {
        if (!$this->testProgramId) {
            $this->failures[] = 'Cannot test getByProgram - no test program created';
            return;
        }
        
        $applications = $this->applicationModel->getByProgramId($this->testProgramId);
        $this->assertIsArray($applications, 'getByProgramId should return an array');
    }
    
    private function testDeleteApplication() {
        if (!$this->testApplicationId) {
            $this->failures[] = 'Cannot test delete - no test application created';
            return;
        }
        
        $result = $this->applicationModel->delete($this->testApplicationId);
        $this->assertTrue($result, 'Application deletion should succeed');
        
        $application = $this->applicationModel->getById($this->testApplicationId);
        $this->assertNull($application, 'Application should not be found after deletion');
    }
    
    protected function tearDown() {
        if ($this->testApplicationId) {
            $this->applicationModel->delete($this->testApplicationId);
        }
        if ($this->testStudentId) {
            $this->studentModel->delete($this->testStudentId);
        }
        if ($this->testProgramId) {
            $this->programModel->delete($this->testProgramId);
        }
    }
}
?>
