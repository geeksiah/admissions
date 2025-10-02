<?php
/**
 * Comprehensive Admissions Management System
 * Main Cron Job Handler
 * 
 * This file should be called every 5 minutes via cPanel cron jobs
 * Command: * /5 * * * * php /home/username/public_html/cron.php
 */

// Set time limit for long-running processes
set_time_limit(0);

// Include configuration
require_once 'config/config.php';
require_once 'config/database.php';

// Include required classes
require_once 'classes/BackupManager.php';
require_once 'classes/NotificationManager.php';
require_once 'classes/EnhancedNotificationManager.php';

// Log cron execution
$logFile = 'logs/cron.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logCron($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

logCron("Cron job started");

try {
    // Initialize database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // 1. Process email queue
    logCron("Processing email queue");
    $notificationManager = new NotificationManager($pdo);
    $emailResult = $notificationManager->processEmailQueue();
    logCron("Email queue processed: " . $emailResult['processed'] . " emails sent");

    // 2. Process SMS queue
    logCron("Processing SMS queue");
    $smsResult = $notificationManager->processSmsQueue();
    logCron("SMS queue processed: " . $smsResult['processed'] . " SMS sent");

    // 3. Process enhanced notifications (reminders, countdowns)
    logCron("Processing enhanced notifications");
    $enhancedManager = new EnhancedNotificationManager($pdo);
    $enhancedResult = $enhancedManager->processScheduledNotifications();
    logCron("Enhanced notifications processed: " . $enhancedResult['processed'] . " notifications sent");

    // 4. Check for daily backup (run at 2 AM)
    $currentHour = (int)date('H');
    if ($currentHour === 2) {
        logCron("Starting daily backup");
        $backupManager = new BackupManager($pdo);
        $backupResult = $backupManager->createBackup('daily');
        if ($backupResult['success']) {
            logCron("Daily backup completed: " . $backupResult['filename']);
        } else {
            logCron("Daily backup failed: " . $backupResult['error']);
        }
    }

    // 5. Check for weekly backup (run on Sunday at 3 AM)
    $currentDay = (int)date('w'); // 0 = Sunday
    if ($currentDay === 0 && $currentHour === 3) {
        logCron("Starting weekly backup");
        $backupManager = new BackupManager($pdo);
        $backupResult = $backupManager->createBackup('weekly');
        if ($backupResult['success']) {
            logCron("Weekly backup completed: " . $backupResult['filename']);
        } else {
            logCron("Weekly backup failed: " . $backupResult['error']);
        }
    }

    // 6. Clean up old sessions (run every hour)
    if ($currentHour % 1 === 0) {
        logCron("Cleaning up old sessions");
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        $deletedSessions = $stmt->rowCount();
        logCron("Cleaned up $deletedSessions expired sessions");
    }

    // 7. Clean up old email verifications (run daily at 4 AM)
    if ($currentHour === 4) {
        logCron("Cleaning up expired email verifications");
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE expires_at < NOW() AND verified_at IS NULL");
        $stmt->execute();
        $deletedVerifications = $stmt->rowCount();
        logCron("Cleaned up $deletedVerifications expired email verifications");
    }

    // 8. Clean up old backup files (run daily at 5 AM)
    if ($currentHour === 5) {
        logCron("Cleaning up old backup files");
        $backupManager = new BackupManager($pdo);
        $cleanupResult = $backupManager->cleanupOldBackups();
        logCron("Backup cleanup completed: " . $cleanupResult['deleted'] . " files deleted");
    }

    // 9. Update application statistics cache (run every 30 minutes)
    $currentMinute = (int)date('i');
    if ($currentMinute % 30 === 0) {
        logCron("Updating application statistics cache");
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_applications,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_applications,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_applications
            FROM applications
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        // Cache the statistics
        $cacheFile = 'cache/application_stats.json';
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, json_encode($stats));
        logCron("Application statistics cache updated");
    }

    logCron("Cron job completed successfully");

} catch (Exception $e) {
    logCron("Cron job error: " . $e->getMessage());
    error_log("Cron job error: " . $e->getMessage());
}

// Clean up
if (isset($pdo)) {
    $pdo = null;
}
?>
