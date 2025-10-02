<?php
/**
 * Test Runner
 * Execute all tests and generate report
 */

require_once 'bootstrap.php';
require_once 'TestCase.php';
require_once 'UserModelTest.php';
require_once 'ApplicationModelTest.php';
require_once 'SecurityTest.php';

class TestRunner {
    private $tests = [];
    private $results = [];
    
    public function __construct() {
        $this->registerTests();
    }
    
    private function registerTests() {
        $this->tests = [
            new UserModelTest(),
            new ApplicationModelTest(),
            new SecurityTest()
        ];
    }
    
    public function runAll() {
        echo "Running Comprehensive Admissions Management System Tests\n";
        echo "====================================================\n\n";
        
        $totalTests = count($this->tests);
        $passedTests = 0;
        $totalAssertions = 0;
        $totalFailures = 0;
        
        foreach ($this->tests as $test) {
            echo "Running " . get_class($test) . "...\n";
            
            $result = $test->run();
            $this->results[] = $result;
            
            $totalAssertions += $result['assertions'];
            $totalFailures += count($result['failures']);
            
            if ($result['passed']) {
                $passedTests++;
                echo "✓ PASSED ({$result['assertions']} assertions)\n";
            } else {
                echo "✗ FAILED ({$result['assertions']} assertions, " . count($result['failures']) . " failures)\n";
                foreach ($result['failures'] as $failure) {
                    echo "  - $failure\n";
                }
            }
            echo "\n";
        }
        
        // Summary
        echo "Test Summary\n";
        echo "============\n";
        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: " . ($totalTests - $passedTests) . "\n";
        echo "Total Assertions: $totalAssertions\n";
        echo "Total Failures: $totalFailures\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";
        
        if ($totalFailures === 0) {
            echo "🎉 All tests passed! System is ready for production.\n";
        } else {
            echo "⚠️  Some tests failed. Please review and fix issues.\n";
        }
        
        return $this->results;
    }
    
    public function generateReport($filename = 'test-report.json') {
        $report = [
            'timestamp' => date('c'),
            'summary' => [
                'total_tests' => count($this->results),
                'passed_tests' => count(array_filter($this->results, function($r) { return $r['passed']; })),
                'total_assertions' => array_sum(array_column($this->results, 'assertions')),
                'total_failures' => array_sum(array_map(function($r) { return count($r['failures']); }, $this->results))
            ],
            'results' => $this->results
        ];
        
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
        echo "Test report saved to: $filename\n";
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new TestRunner();
    $results = $runner->runAll();
    $runner->generateReport();
}
?>
