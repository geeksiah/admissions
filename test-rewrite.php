<?php
echo "<h1>URL Rewrite Test</h1>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";

// Check if mod_rewrite is enabled
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p>mod_rewrite enabled: " . (in_array('mod_rewrite', $modules) ? 'YES' : 'NO') . "</p>";
    echo "<p>Available modules: " . implode(', ', $modules) . "</p>";
} else {
    echo "<p>Cannot check mod_rewrite status (not Apache or function not available)</p>";
}

echo "<h2>Test Links</h2>";
echo "<p><a href='/test-simple'>Test Simple (Clean URL)</a></p>";
echo "<p><a href='/test-dashboard-ultra'>Test Dashboard Ultra (Clean URL)</a></p>";
echo "<p><a href='admin/test-direct.php'>Test Direct (Direct File)</a></p>";
echo "<p><a href='admin/simple-test.php'>Simple Test (Direct File)</a></p>";
?>
