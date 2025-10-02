<?php
/**
 * User Model Tests
 */

require_once 'TestCase.php';
require_once '../models/User.php';

class UserModelTest extends TestCase {
    private $userModel;
    private $testUserId;
    
    protected function setUp() {
        $this->userModel = new User($this->database);
    }
    
    protected function test() {
        $this->testCreateUser();
        $this->testGetUserById();
        $this->testUpdateUser();
        $this->testDeleteUser();
        $this->testGetAllUsers();
        $this->testUserValidation();
    }
    
    private function testCreateUser() {
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password_hash' => password_hash('testpassword', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'student',
            'status' => 'active'
        ];
        
        $result = $this->userModel->create($userData);
        $this->assertTrue($result !== false, 'User creation should succeed');
        
        if ($result) {
            $this->testUserId = $result;
        }
    }
    
    private function testGetUserById() {
        if (!$this->testUserId) {
            $this->failures[] = 'Cannot test getById - no test user created';
            return;
        }
        
        $user = $this->userModel->getById($this->testUserId);
        $this->assertNotNull($user, 'User should be found by ID');
        $this->assertEquals($this->testUserId, $user['id'], 'User ID should match');
        $this->assertEquals('Test', $user['first_name'], 'First name should match');
    }
    
    private function testUpdateUser() {
        if (!$this->testUserId) {
            $this->failures[] = 'Cannot test update - no test user created';
            return;
        }
        
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name'
        ];
        
        $result = $this->userModel->update($this->testUserId, $updateData);
        $this->assertTrue($result, 'User update should succeed');
        
        $user = $this->userModel->getById($this->testUserId);
        $this->assertEquals('Updated', $user['first_name'], 'First name should be updated');
    }
    
    private function testDeleteUser() {
        if (!$this->testUserId) {
            $this->failures[] = 'Cannot test delete - no test user created';
            return;
        }
        
        $result = $this->userModel->delete($this->testUserId);
        $this->assertTrue($result, 'User deletion should succeed');
        
        $user = $this->userModel->getById($this->testUserId);
        $this->assertNull($user, 'User should not be found after deletion');
    }
    
    private function testGetAllUsers() {
        $users = $this->userModel->getAll();
        $this->assertIsArray($users, 'getAll should return an array');
    }
    
    private function testUserValidation() {
        // Test invalid email
        $invalidData = [
            'username' => 'testuser',
            'email' => 'invalid-email',
            'password_hash' => password_hash('test', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User'
        ];
        
        $result = $this->userModel->create($invalidData);
        $this->assertFalse($result, 'User creation with invalid email should fail');
    }
    
    protected function tearDown() {
        if ($this->testUserId) {
            $this->userModel->delete($this->testUserId);
        }
    }
}
?>
