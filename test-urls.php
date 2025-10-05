<?php
echo "<h1>URL Testing</h1>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// Test if mod_rewrite is enabled
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p>mod_rewrite enabled: " . (in_array('mod_rewrite', $modules) ? 'YES' : 'NO') . "</p>";
} else {
    echo "<p>Cannot check mod_rewrite status</p>";
}

echo "<h2>Test Links:</h2>";
echo "<p><a href='/dashboard'>Dashboard (Clean URL)</a></p>";
echo "<p><a href='/admin/applications'>Admin Applications (Clean URL)</a></p>";
echo "<p><a href='/admin/applications.php'>Admin Applications (.php)</a></p>";
echo "<p><a href='dashboard.php'>Dashboard (relative)</a></p>";
echo "<p><a href='admin/applications.php'>Admin Applications (relative)</a></p>";
?>
