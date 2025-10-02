<?php
/**
 * PHP Settings Check for Admissions Management System
 */

echo "<h1>PHP Settings Check</h1>\n";

$settings = [
    'PHP Version' => phpversion(),
    'Upload Max Filesize' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
    'Max File Uploads' => ini_get('max_file_uploads'),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time'),
    'Max Input Time' => ini_get('max_input_time'),
    'Display Errors' => ini_get('display_errors') ? 'On' : 'Off',
    'Error Reporting' => ini_get('error_reporting'),
    'Session GC Max Lifetime' => ini_get('session.gc_maxlifetime'),
    'Session Cookie Lifetime' => ini_get('session.cookie_lifetime'),
    'Timezone' => ini_get('date.timezone'),
    'OPcache Enabled' => ini_get('opcache.enable') ? 'On' : 'Off',
    'Allow URL Fopen' => ini_get('allow_url_fopen') ? 'On' : 'Off',
    'Expose PHP' => ini_get('expose_php') ? 'On' : 'Off'
];

echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Setting</th><th>Current Value</th><th>Recommended</th><th>Status</th></tr>\n";

$recommendations = [
    'PHP Version' => ['8.1', '8.2'],
    'Upload Max Filesize' => ['10M', '12M'],
    'Post Max Size' => ['12M', '15M'],
    'Max File Uploads' => ['20', '20'],
    'Memory Limit' => ['256M', '512M'],
    'Max Execution Time' => ['300', '300'],
    'Max Input Time' => ['300', '300'],
    'Display Errors' => ['Off', 'Off'],
    'Session GC Max Lifetime' => ['3600', '3600'],
    'Session Cookie Lifetime' => ['3600', '3600'],
    'OPcache Enabled' => ['On', 'On'],
    'Allow URL Fopen' => ['Off', 'Off'],
    'Expose PHP' => ['Off', 'Off']
];

foreach ($settings as $setting => $value) {
    $recommended = $recommendations[$setting] ?? ['N/A', 'N/A'];
    $status = '✅ OK';
    
    if ($setting === 'PHP Version') {
        if (version_compare($value, '8.4.0', '>=')) {
            $status = '⚠️ Too new - use 8.1 or 8.2';
        } elseif (version_compare($value, '8.0.0', '<')) {
            $status = '❌ Too old - use 8.1 or 8.2';
        }
    } elseif ($setting === 'Memory Limit') {
        $currentMB = (int)$value;
        if ($currentMB < 128) {
            $status = '❌ Too low - need at least 128M';
        } elseif ($currentMB > 512) {
            $status = '⚠️ Very high';
        }
    } elseif ($setting === 'Upload Max Filesize') {
        $currentMB = (int)$value;
        if ($currentMB < 5) {
            $status = '❌ Too low - need at least 5M';
        } elseif ($currentMB > 20) {
            $status = '⚠️ Very high';
        }
    }
    
    echo "<tr>";
    echo "<td><strong>$setting</strong></td>";
    echo "<td>$value</td>";
    echo "<td>" . implode(' or ', $recommended) . "</td>";
    echo "<td>$status</td>";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>Extensions Check</h2>\n";
$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'session', 'mbstring', 'fileinfo', 'openssl'];
echo "<ul>\n";
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "<li>$status $ext</li>\n";
}
echo "</ul>\n";

echo "<h2>File Permissions Check</h2>\n";
$directories = ['uploads', 'temp', 'logs', 'admin', 'student', 'api'];
echo "<ul>\n";
foreach ($directories as $dir) {
    if (file_exists($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $writable = is_writable($dir);
        $status = $writable ? '✅' : '❌';
        echo "<li>$status $dir (permissions: $perms, writable: " . ($writable ? 'Yes' : 'No') . ")</li>\n";
    } else {
        echo "<li>⚠️ $dir (directory doesn't exist)</li>\n";
    }
}
echo "</ul>\n";

echo "<h2>Recommendations</h2>\n";
echo "<ol>\n";
echo "<li><strong>Change PHP Version:</strong> Switch to PHP 8.1 or 8.2 in Hostinger control panel</li>\n";
echo "<li><strong>Increase Memory:</strong> Set memory_limit to at least 256M</li>\n";
echo "<li><strong>File Uploads:</strong> Set upload_max_filesize to 10M and post_max_size to 12M</li>\n";
echo "<li><strong>Security:</strong> Turn off expose_php and allow_url_fopen</li>\n";
echo "<li><strong>Performance:</strong> Enable OPcache if not already enabled</li>\n";
echo "</ol>\n";
?>
