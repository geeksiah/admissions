<?php
/**
 * Automated Backup Cron Job
 * This script should be run via cron job for automated backups
 * 
 * Example cron entry:
 * 0 2 * * * /usr/bin/php /path/to/your/project/cron/backup-cron.php
 */

// Set time limit for long-running backup process
set_time_limit(0);

// Include required files
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/classes/BackupManager.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = dirname(__DIR__) . '/logs/backup-cron.log';
    
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
    logMessage('Starting automated backup process');
    
    // Initialize database and backup manager
    $database = new Database();
    $backupManager = new BackupManager($database);
    
    // Check if backup is scheduled for today
    $scheduleFile = dirname(__DIR__) . '/backups/schedule.json';
    $shouldRun = false;
    
    if (file_exists($scheduleFile)) {
        $schedule = json_decode(file_get_contents($scheduleFile), true);
        
        if ($schedule && $schedule['enabled']) {
            $now = new DateTime();
            $nextRun = new DateTime($schedule['next_run']);
            
            if ($now >= $nextRun) {
                $shouldRun = true;
                logMessage('Scheduled backup time reached');
            } else {
                logMessage('Scheduled backup not due yet. Next run: ' . $schedule['next_run']);
            }
        }
    } else {
        // No schedule file, run daily backup by default
        $shouldRun = true;
        logMessage('No schedule file found, running default daily backup');
    }
    
    if ($shouldRun) {
        // Create backup
        $description = 'Automated backup - ' . date('Y-m-d H:i:s');
        $result = $backupManager->createFullBackup($description);
        
        logMessage('Backup created successfully: ' . $result['backup_id']);
        logMessage('Backup size: ' . formatBytes($result['size']));
        
        // Update next run time
        if (file_exists($scheduleFile)) {
            $schedule = json_decode(file_get_contents($scheduleFile), true);
            if ($schedule) {
                $nextRun = $backupManager->calculateNextRun($schedule['frequency'], $schedule['time']);
                $schedule['next_run'] = $nextRun;
                file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
                logMessage('Next backup scheduled for: ' . $nextRun);
            }
        }
        
        // Send notification email (optional)
        if (defined('BACKUP_NOTIFICATION_EMAIL') && BACKUP_NOTIFICATION_EMAIL) {
            sendBackupNotification($result);
        }
        
    } else {
        logMessage('Backup not scheduled for this time');
    }
    
    logMessage('Automated backup process completed');
    
} catch (Exception $e) {
    logMessage('ERROR: ' . $e->getMessage());
    
    // Send error notification email (optional)
    if (defined('BACKUP_NOTIFICATION_EMAIL') && BACKUP_NOTIFICATION_EMAIL) {
        sendBackupErrorNotification($e->getMessage());
    }
    
    exit(1);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Send backup success notification
 */
function sendBackupNotification($backupResult) {
    try {
        $to = BACKUP_NOTIFICATION_EMAIL;
        $subject = 'Backup Completed Successfully - ' . APP_NAME;
        $message = "
        <h2>Backup Completed Successfully</h2>
        <p>A new backup has been created successfully.</p>
        <ul>
            <li><strong>Backup ID:</strong> {$backupResult['backup_id']}</li>
            <li><strong>Size:</strong> " . formatBytes($backupResult['size']) . "</li>
            <li><strong>Created:</strong> " . date('Y-m-d H:i:s') . "</li>
        </ul>
        <p>You can manage your backups in the admin panel.</p>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . SMTP_FROM_EMAIL,
            'Reply-To: ' . SMTP_FROM_EMAIL
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        logMessage('Backup notification email sent to: ' . $to);
        
    } catch (Exception $e) {
        logMessage('Failed to send backup notification email: ' . $e->getMessage());
    }
}

/**
 * Send backup error notification
 */
function sendBackupErrorNotification($errorMessage) {
    try {
        $to = BACKUP_NOTIFICATION_EMAIL;
        $subject = 'Backup Failed - ' . APP_NAME;
        $message = "
        <h2>Backup Failed</h2>
        <p>An error occurred during the automated backup process.</p>
        <p><strong>Error:</strong> " . htmlspecialchars($errorMessage) . "</p>
        <p>Please check the backup logs and system configuration.</p>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . SMTP_FROM_EMAIL,
            'Reply-To: ' . SMTP_FROM_EMAIL
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        logMessage('Backup error notification email sent to: ' . $to);
        
    } catch (Exception $e) {
        logMessage('Failed to send backup error notification email: ' . $e->getMessage());
    }
}

/**
 * Calculate next run time (duplicate from BackupManager for cron script)
 */
function calculateNextRun($frequency, $time) {
    $now = new DateTime();
    $nextRun = clone $now;
    
    switch ($frequency) {
        case 'daily':
            $nextRun->setTime(...explode(':', $time));
            if ($nextRun <= $now) {
                $nextRun->add(new DateInterval('P1D'));
            }
            break;
        case 'weekly':
            $nextRun->setTime(...explode(':', $time));
            $nextRun->add(new DateInterval('P7D'));
            break;
        case 'monthly':
            $nextRun->setTime(...explode(':', $time));
            $nextRun->add(new DateInterval('P1M'));
            break;
    }
    
    return $nextRun->format('Y-m-d H:i:s');
}
?>
