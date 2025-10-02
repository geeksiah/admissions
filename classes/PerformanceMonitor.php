<?php
/**
 * Performance Monitor
 * Tracks system performance metrics
 */

class PerformanceMonitor {
    private $startTime;
    private $memoryStart;
    private $queries = [];
    private $metrics = [];
    private $logFile;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
        $this->logFile = 'logs/performance.log';
        
        // Ensure logs directory exists
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
    }
    
    /**
     * Start monitoring a database query
     */
    public function startQuery($sql) {
        $this->queries[] = [
            'sql' => $sql,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage()
        ];
    }
    
    /**
     * End monitoring a database query
     */
    public function endQuery($sql, $resultCount = 0) {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        // Find the matching query
        foreach ($this->queries as &$query) {
            if ($query['sql'] === $sql && !isset($query['end_time'])) {
                $query['end_time'] = $endTime;
                $query['end_memory'] = $endMemory;
                $query['duration'] = $endTime - $query['start_time'];
                $query['memory_used'] = $endMemory - $query['start_memory'];
                $query['result_count'] = $resultCount;
                break;
            }
        }
    }
    
    /**
     * Record a custom metric
     */
    public function recordMetric($name, $value, $unit = '') {
        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
            'unit' => $unit,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Get performance summary
     */
    public function getSummary() {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $totalTime = $endTime - $this->startTime;
        $totalMemory = $endMemory - $this->memoryStart;
        $peakMemory = memory_get_peak_usage();
        
        $completedQueries = array_filter($this->queries, function($q) {
            return isset($q['end_time']);
        });
        
        $totalQueryTime = array_sum(array_column($completedQueries, 'duration'));
        $slowQueries = array_filter($completedQueries, function($q) {
            return $q['duration'] > 0.1; // Queries taking more than 100ms
        });
        
        return [
            'total_execution_time' => round($totalTime, 4),
            'total_memory_used' => $this->formatBytes($totalMemory),
            'peak_memory_used' => $this->formatBytes($peakMemory),
            'total_queries' => count($completedQueries),
            'total_query_time' => round($totalQueryTime, 4),
            'average_query_time' => count($completedQueries) > 0 ? round($totalQueryTime / count($completedQueries), 4) : 0,
            'slow_queries' => count($slowQueries),
            'queries' => $completedQueries,
            'custom_metrics' => $this->metrics
        ];
    }
    
    /**
     * Log performance data
     */
    public function logPerformance($context = '') {
        $summary = $this->getSummary();
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context,
            'summary' => $summary
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get performance statistics
     */
    public function getStatistics($days = 7) {
        $logFile = $this->logFile;
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $recentLogs = [];
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && strtotime($data['timestamp']) > $cutoffTime) {
                $recentLogs[] = $data;
            }
        }
        
        if (empty($recentLogs)) {
            return [];
        }
        
        $executionTimes = array_column(array_column($recentLogs, 'summary'), 'total_execution_time');
        $queryCounts = array_column(array_column($recentLogs, 'summary'), 'total_queries');
        $slowQueryCounts = array_column(array_column($recentLogs, 'summary'), 'slow_queries');
        
        return [
            'average_execution_time' => round(array_sum($executionTimes) / count($executionTimes), 4),
            'max_execution_time' => max($executionTimes),
            'min_execution_time' => min($executionTimes),
            'average_queries_per_request' => round(array_sum($queryCounts) / count($queryCounts), 2),
            'average_slow_queries' => round(array_sum($slowQueryCounts) / count($slowQueryCounts), 2),
            'total_requests' => count($recentLogs),
            'performance_trend' => $this->calculateTrend($executionTimes)
        ];
    }
    
    /**
     * Check if performance is within acceptable limits
     */
    public function checkPerformanceThresholds() {
        $summary = $this->getSummary();
        $alerts = [];
        
        // Check execution time (should be under 2 seconds)
        if ($summary['total_execution_time'] > 2) {
            $alerts[] = "High execution time: {$summary['total_execution_time']}s";
        }
        
        // Check memory usage (should be under 64MB)
        $memoryMB = $this->parseBytes($summary['total_memory_used']);
        if ($memoryMB > 64) {
            $alerts[] = "High memory usage: {$summary['total_memory_used']}";
        }
        
        // Check for slow queries
        if ($summary['slow_queries'] > 0) {
            $alerts[] = "Slow queries detected: {$summary['slow_queries']}";
        }
        
        // Check average query time
        if ($summary['average_query_time'] > 0.1) {
            $alerts[] = "High average query time: {$summary['average_query_time']}s";
        }
        
        return $alerts;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Parse human readable bytes back to number
     */
    private function parseBytes($formatted) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024];
        $matches = [];
        if (preg_match('/(\d+\.?\d*)\s*([A-Z]+)/', $formatted, $matches)) {
            return floatval($matches[1]) * $units[$matches[2]];
        }
        return 0;
    }
    
    /**
     * Calculate performance trend
     */
    private function calculateTrend($values) {
        if (count($values) < 2) {
            return 'stable';
        }
        
        $firstHalf = array_slice($values, 0, floor(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;
        
        if ($change > 10) {
            return 'improving';
        } elseif ($change < -10) {
            return 'degrading';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Generate performance report
     */
    public function generateReport() {
        $summary = $this->getSummary();
        $statistics = $this->getStatistics();
        $alerts = $this->checkPerformanceThresholds();
        
        return [
            'current_performance' => $summary,
            'historical_statistics' => $statistics,
            'alerts' => $alerts,
            'recommendations' => $this->getRecommendations($summary, $statistics, $alerts)
        ];
    }
    
    /**
     * Get performance recommendations
     */
    private function getRecommendations($summary, $statistics, $alerts) {
        $recommendations = [];
        
        if (!empty($alerts)) {
            $recommendations[] = "Address performance alerts: " . implode(', ', $alerts);
        }
        
        if ($summary['average_query_time'] > 0.05) {
            $recommendations[] = "Consider optimizing database queries or adding indexes";
        }
        
        if ($summary['total_queries'] > 20) {
            $recommendations[] = "Consider reducing the number of database queries";
        }
        
        if ($this->parseBytes($summary['total_memory_used']) > 32 * 1024 * 1024) {
            $recommendations[] = "Consider optimizing memory usage or increasing PHP memory limit";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Performance is within acceptable limits";
        }
        
        return $recommendations;
    }
}
?>
