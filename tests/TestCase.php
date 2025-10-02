<?php
/**
 * Base Test Case Class
 */

abstract class TestCase {
    protected $database;
    protected $assertions = 0;
    protected $failures = [];
    
    public function __construct() {
        global $database;
        $this->database = $database;
    }
    
    /**
     * Assert that a condition is true
     */
    protected function assertTrue($condition, $message = '') {
        $this->assertions++;
        if (!$condition) {
            $this->failures[] = "Assertion failed: " . ($message ?: 'Expected true, got false');
        }
    }
    
    /**
     * Assert that a condition is false
     */
    protected function assertFalse($condition, $message = '') {
        $this->assertions++;
        if ($condition) {
            $this->failures[] = "Assertion failed: " . ($message ?: 'Expected false, got true');
        }
    }
    
    /**
     * Assert that two values are equal
     */
    protected function assertEquals($expected, $actual, $message = '') {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected '$expected', got '$actual'");
        }
    }
    
    /**
     * Assert that two values are not equal
     */
    protected function assertNotEquals($expected, $actual, $message = '') {
        $this->assertions++;
        if ($expected === $actual) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected not equal to '$expected', got '$actual'");
        }
    }
    
    /**
     * Assert that an array contains a value
     */
    protected function assertContains($needle, $haystack, $message = '') {
        $this->assertions++;
        if (!in_array($needle, $haystack)) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected array to contain '$needle'");
        }
    }
    
    /**
     * Assert that an array does not contain a value
     */
    protected function assertNotContains($needle, $haystack, $message = '') {
        $this->assertions++;
        if (in_array($needle, $haystack)) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected array not to contain '$needle'");
        }
    }
    
    /**
     * Assert that a value is null
     */
    protected function assertNull($value, $message = '') {
        $this->assertions++;
        if ($value !== null) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected null, got '$value'");
        }
    }
    
    /**
     * Assert that a value is not null
     */
    protected function assertNotNull($value, $message = '') {
        $this->assertions++;
        if ($value === null) {
            $this->failures[] = "Assertion failed: " . ($message ?: 'Expected not null, got null');
        }
    }
    
    /**
     * Assert that a value is an array
     */
    protected function assertIsArray($value, $message = '') {
        $this->assertions++;
        if (!is_array($value)) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected array, got " . gettype($value));
        }
    }
    
    /**
     * Assert that a value is a string
     */
    protected function assertIsString($value, $message = '') {
        $this->assertions++;
        if (!is_string($value)) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected string, got " . gettype($value));
        }
    }
    
    /**
     * Assert that a value is an integer
     */
    protected function assertIsInt($value, $message = '') {
        $this->assertions++;
        if (!is_int($value)) {
            $this->failures[] = "Assertion failed: " . ($message ?: "Expected integer, got " . gettype($value));
        }
    }
    
    /**
     * Run the test
     */
    public function run() {
        $this->setUp();
        $this->test();
        $this->tearDown();
        
        return [
            'name' => get_class($this),
            'assertions' => $this->assertions,
            'failures' => $this->failures,
            'passed' => empty($this->failures)
        ];
    }
    
    /**
     * Setup method - override in subclasses
     */
    protected function setUp() {
        // Override in subclasses
    }
    
    /**
     * Test method - override in subclasses
     */
    abstract protected function test();
    
    /**
     * Teardown method - override in subclasses
     */
    protected function tearDown() {
        // Override in subclasses
    }
}
?>
