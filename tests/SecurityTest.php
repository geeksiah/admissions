<?php
/**
 * Security Tests
 */

require_once 'TestCase.php';
require_once '../classes/Security.php';

class SecurityTest extends TestCase {
    private $security;
    
    protected function setUp() {
        $this->security = new Security($this->database);
    }
    
    protected function test() {
        $this->testPasswordHashing();
        $this->testPasswordVerification();
        $this->testCSRFTokenGeneration();
        $this->testCSRFTokenValidation();
        $this->testInputSanitization();
        $this->testRateLimiting();
    }
    
    private function testPasswordHashing() {
        $password = 'testpassword123';
        $hash = $this->security->hashPassword($password);
        
        $this->assertIsString($hash, 'Password hash should be a string');
        $this->assertNotEquals($password, $hash, 'Hash should not equal original password');
        $this->assertTrue(strlen($hash) > 50, 'Hash should be reasonably long');
    }
    
    private function testPasswordVerification() {
        $password = 'testpassword123';
        $hash = $this->security->hashPassword($password);
        
        $this->assertTrue($this->security->verifyPassword($password, $hash), 'Correct password should verify');
        $this->assertFalse($this->security->verifyPassword('wrongpassword', $hash), 'Wrong password should not verify');
    }
    
    private function testCSRFTokenGeneration() {
        $token = $this->security->generateCSRFToken();
        
        $this->assertIsString($token, 'CSRF token should be a string');
        $this->assertTrue(strlen($token) > 20, 'CSRF token should be reasonably long');
        
        // Test that tokens are unique
        $token2 = $this->security->generateCSRFToken();
        $this->assertNotEquals($token, $token2, 'CSRF tokens should be unique');
    }
    
    private function testCSRFTokenValidation() {
        $token = $this->security->generateCSRFToken();
        
        $this->assertTrue($this->security->validateCSRFToken($token), 'Valid CSRF token should validate');
        $this->assertFalse($this->security->validateCSRFToken('invalid_token'), 'Invalid CSRF token should not validate');
        $this->assertFalse($this->security->validateCSRFToken(''), 'Empty CSRF token should not validate');
    }
    
    private function testInputSanitization() {
        $maliciousInput = '<script>alert("xss")</script>';
        $sanitized = $this->security->sanitizeInput($maliciousInput);
        
        $this->assertNotContains('<script>', $sanitized, 'Script tags should be removed');
        $this->assertNotContains('alert', $sanitized, 'JavaScript should be removed');
        
        $sqlInjection = "'; DROP TABLE users; --";
        $sanitized = $this->security->sanitizeInput($sqlInjection);
        
        $this->assertNotContains('DROP', $sanitized, 'SQL injection attempts should be sanitized');
    }
    
    private function testRateLimiting() {
        $identifier = 'test_user_' . uniqid();
        
        // Test rate limiting
        for ($i = 0; $i < 5; $i++) {
            $result = $this->security->checkRateLimit($identifier, 5, 60);
            $this->assertTrue($result, "Rate limit should allow request $i");
        }
        
        // This should be rate limited
        $result = $this->security->checkRateLimit($identifier, 5, 60);
        $this->assertFalse($result, 'Rate limit should block after 5 requests');
    }
}
?>
