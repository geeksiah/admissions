<?php
/**
 * Notification Processing Cron Job
 * This script processes pending notifications and reminders
 * 
 * Example cron entry (run every hour):
 * 0 * * * * /usr/bin/php /path/to/your/project/cron/notification-cron.php
 */

// Set time limit for long-running process
set_time_limit(0);

// Include required files
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/classes/EnhancedNotificationManager.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = dirname(__DIR__) . '/logs/notification-cron.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logMessage('Starting notification processing');
    
    // Initialize database and notification manager
    $database = new Database();
    $notificationManager = new EnhancedNotificationManager($database);
    
    // Process pending reminders
    $processed = $notificationManager->processPendingReminders();
    
    logMessage("Processed $processed notifications");
    
    // Process email queue
    $emailQueue = new NotificationManager($database);
    $emailProcessed = $emailQueue->processEmailQueue();
    
    logMessage("Processed $emailProcessed emails from queue");
    
    // Process SMS queue
    $smsProcessed = $emailQueue->processSmsQueue();
    
    logMessage("Processed $smsProcessed SMS messages from queue");
    
    // Clean up old notification logs (older than 90 days)
    $stmt = $database->prepare("
        DELETE FROM notification_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    
    if ($deletedLogs > 0) {
        logMessage("Cleaned up $deletedLogs old notification logs");
    }
    
    // Clean up old email queue (older than 30 days)
    $stmt = $database->prepare("
        DELETE FROM email_queue 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status IN ('sent', 'failed')
    ");
    $stmt->execute();
    $deletedEmails = $stmt->rowCount();
    
    if ($deletedEmails > 0) {
        logMessage("Cleaned up $deletedEmails old email queue entries");
    }
    
    // Clean up old SMS queue (older than 30 days)
    $stmt = $database->prepare("
        DELETE FROM sms_queue 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status IN ('sent', 'failed')
    ");
    $stmt->execute();
    $deletedSms = $stmt->rowCount();
    
    if ($deletedSms > 0) {
        logMessage("Cleaned up $deletedSms old SMS queue entries");
    }
    
    logMessage('Notification processing completed successfully');
    
} catch (Exception $e) {
    logMessage('ERROR: ' . $e->getMessage());
    exit(1);
}
?>
