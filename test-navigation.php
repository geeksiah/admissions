<?php
echo "<h1>Navigation Test</h1>";

// Test navigation links
$testLinks = [
    '/dashboard',
    '/admin/applications',
    '/admin/students', 
    '/admin/programs',
    '/admin/users',
    '/admin/reports',
    '/admin/settings',
    '/profile',
    '/logout'
];

echo "<h2>Test Navigation Links</h2>";
foreach ($testLinks as $link) {
    $file = str_replace('/', '', $link) . '.php';
    if (strpos($link, '/admin/') === 0) {
        $file = 'admin/' . str_replace('/admin/', '', $link) . '.php';
    }
    
    $exists = file_exists($file);
    $status = $exists ? '✅ EXISTS' : '❌ MISSING';
    echo "<p><a href='$link'>$link</a> → $file $status</p>";
}

echo "<h2>Direct File Test</h2>";
echo "<p><a href='admin/applications.php'>admin/applications.php (Direct)</a></p>";
echo "<p><a href='admin/students.php'>admin/students.php (Direct)</a></p>";
echo "<p><a href='admin/programs.php'>admin/programs.php (Direct)</a></p>";

echo "<h2>Current URL</h2>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
?>
