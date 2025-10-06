<?php
echo "<h1>Simple Admin Test</h1>";
echo "<p>This is a simple admin test in the root directory.</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Links</h2>";
echo "<p><a href='admin/test-admin.php'>Admin Test (Direct)</a></p>";
echo "<p><a href='admin/applications.php'>Applications (Direct)</a></p>";
echo "<p><a href='admin/users.php'>Users (Direct)</a></p>";
echo "<p><a href='/admin/applications'>Applications (Clean URL)</a></p>";
echo "<p><a href='/admin/users'>Users (Clean URL)</a></p>";
?>
