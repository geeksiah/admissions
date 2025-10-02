<?php
/**
 * Debug Index - Wrapper for index.php with comprehensive error reporting
 * Use this temporarily to debug 500 errors
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- Debug Index Started: " . date('Y-m-d H:i:s') . " -->\n";

try {
    // Capture any output and errors
    ob_start();
    
    // Include the original index.php
    include 'index.php';
    
    // If we get here, the include was successful
    $output = ob_get_clean();
    echo $output;
    
} catch (Error $e) {
    ob_end_clean();
    echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 20px; margin: 20px; border-radius: 8px;'>";
    echo "<h2 style='color: #d32f2f; margin-top: 0;'>🔥 Fatal Error Detected</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e8; border: 1px solid #4caf50; padding: 20px; margin: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #2e7d32; margin-top: 0;'>💡 Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Check if all required files exist (config/config.php, config/database.php, etc.)</li>";
    echo "<li>Verify database connection settings in config/database.php</li>";
    echo "<li>Ensure database exists and has proper schema imported</li>";
    echo "<li>Check file permissions (755 for directories, 644 for files)</li>";
    echo "<li>Run production_hotfix.php to auto-fix common issues</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<div style='background: #fff3e0; border: 1px solid #ff9800; padding: 20px; margin: 20px; border-radius: 8px;'>";
    echo "<h2 style='color: #f57c00; margin-top: 0;'>⚠️ Exception Caught</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e8; border: 1px solid #4caf50; padding: 20px; margin: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #2e7d32; margin-top: 0;'>🔧 Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Run <code>production_diagnostic.php</code> to identify specific issues</li>";
    echo "<li>Check database connectivity and credentials</li>";
    echo "<li>Ensure all class files are uploaded correctly</li>";
    echo "<li>Verify config/installed.lock file exists</li>";
    echo "<li>Check PHP error logs in Hostinger control panel</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<!-- Debug Index Completed: " . date('Y-m-d H:i:s') . " -->\n";
?>
