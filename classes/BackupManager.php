<?php
/**
 * Backup and Recovery Manager
 * Handles automated database backups, file backups, and recovery operations
 */
class BackupManager {
    private $database;
    private $backupPath;
    private $maxBackups;
    private $compressionEnabled;
    
    public function __construct($database, $backupPath = null, $maxBackups = 30, $compressionEnabled = true) {
        $this->database = $database;
        $this->backupPath = $backupPath ?: __DIR__ . '/../backups/';
        $this->maxBackups = $maxBackups;
        $this->compressionEnabled = $compressionEnabled;
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    /**
     * Create a full system backup (database + files)
     */
    public function createFullBackup($description = '') {
        $timestamp = date('Y-m-d_H-i-s');
        $backupId = 'backup_' . $timestamp;
        $backupDir = $this->backupPath . $backupId . '/';
        
        // Create backup directory
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Failed to create backup directory');
        }
        
        try {
            // Backup database
            $dbBackupFile = $this->createDatabaseBackup($backupDir);
            
            // Backup files
            $filesBackupFile = $this->createFilesBackup($backupDir);
            
            // Create backup manifest
            $manifest = [
                'backup_id' => $backupId,
                'timestamp' => $timestamp,
                'description' => $description,
                'type' => 'full',
                'database_file' => basename($dbBackupFile),
                'files_file' => basename($filesBackupFile),
                'size' => $this->getDirectorySize($backupDir),
                'created_by' => $_SESSION['user_id'] ?? null
            ];
            
            file_put_contents($backupDir . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            // Compress backup if enabled
            if ($this->compressionEnabled) {
                $this->compressBackup($backupDir, $backupId);
            }
            
            // Clean up old backups
            $this->cleanupOldBackups();
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'path' => $backupDir,
                'size' => $manifest['size']
            ];
            
        } catch (Exception $e) {
            // Clean up failed backup
            $this->removeDirectory($backupDir);
            throw $e;
        }
    }
    
    /**
     * Create database backup
     */
    public function createDatabaseBackup($backupDir = null) {
        $backupDir = $backupDir ?: $this->backupPath;
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'database_backup_' . $timestamp . '.sql';
        $filepath = $backupDir . $filename;
        
        // Get database configuration
        $config = require __DIR__ . '/../config/database.php';
        $host = $config['host'];
        $dbname = $config['dbname'];
        $username = $config['username'];
        $password = $config['password'];
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($dbname),
            escapeshellarg($filepath)
        );
        
        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Database backup failed: ' . implode("\n", $output));
        }
        
        if (!file_exists($filepath) || filesize($filepath) === 0) {
            throw new Exception('Database backup file was not created or is empty');
        }
        
        return $filepath;
    }
    
    /**
     * Create files backup
     */
    public function createFilesBackup($backupDir = null) {
        $backupDir = $backupDir ?: $this->backupPath;
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'files_backup_' . $timestamp . '.tar.gz';
        $filepath = $backupDir . $filename;
        
        // Define directories to backup
        $rootDir = dirname(__DIR__);
        $directoriesToBackup = [
            'uploads/',
            'assets/',
            'config/',
            'classes/',
            'models/',
            'admin/',
            'student/',
            'includes/'
        ];
        
        // Create tar command
        $command = 'tar -czf ' . escapeshellarg($filepath) . ' -C ' . escapeshellarg($rootDir);
        
        foreach ($directoriesToBackup as $dir) {
            if (is_dir($rootDir . '/' . $dir)) {
                $command .= ' ' . escapeshellarg($dir);
            }
        }
        
        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Files backup failed: ' . implode("\n", $output));
        }
        
        if (!file_exists($filepath) || filesize($filepath) === 0) {
            throw new Exception('Files backup file was not created or is empty');
        }
        
        return $filepath;
    }
    
    /**
     * Restore from backup
     */
    public function restoreFromBackup($backupId) {
        $backupDir = $this->backupPath . $backupId . '/';
        
        if (!is_dir($backupDir)) {
            throw new Exception('Backup directory not found');
        }
        
        $manifestFile = $backupDir . 'manifest.json';
        if (!file_exists($manifestFile)) {
            throw new Exception('Backup manifest not found');
        }
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if (!$manifest) {
            throw new Exception('Invalid backup manifest');
        }
        
        try {
            // Restore database
            if (isset($manifest['database_file'])) {
                $this->restoreDatabase($backupDir . $manifest['database_file']);
            }
            
            // Restore files
            if (isset($manifest['files_file'])) {
                $this->restoreFiles($backupDir . $manifest['files_file']);
            }
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'restored_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            throw new Exception('Restore failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Restore database from backup
     */
    public function restoreDatabase($backupFile) {
        if (!file_exists($backupFile)) {
            throw new Exception('Database backup file not found');
        }
        
        // Get database configuration
        $config = require __DIR__ . '/../config/database.php';
        $host = $config['host'];
        $dbname = $config['dbname'];
        $username = $config['username'];
        $password = $config['password'];
        
        // Create mysql command
        $command = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($dbname),
            escapeshellarg($backupFile)
        );
        
        // Execute restore command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Database restore failed: ' . implode("\n", $output));
        }
    }
    
    /**
     * Restore files from backup
     */
    public function restoreFiles($backupFile) {
        if (!file_exists($backupFile)) {
            throw new Exception('Files backup file not found');
        }
        
        $rootDir = dirname(__DIR__);
        
        // Create tar command
        $command = 'tar -xzf ' . escapeshellarg($backupFile) . ' -C ' . escapeshellarg($rootDir);
        
        // Execute restore command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Files restore failed: ' . implode("\n", $output));
        }
    }
    
    /**
     * List available backups
     */
    public function listBackups() {
        $backups = [];
        
        if (!is_dir($this->backupPath)) {
            return $backups;
        }
        
        $directories = glob($this->backupPath . 'backup_*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $manifestFile = $dir . '/manifest.json';
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if ($manifest) {
                    $manifest['path'] = $dir;
                    $manifest['size_formatted'] = $this->formatBytes($manifest['size'] ?? 0);
                    $backups[] = $manifest;
                }
            }
        }
        
        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return $backups;
    }
    
    /**
     * Get backup details
     */
    public function getBackupDetails($backupId) {
        $backupDir = $this->backupPath . $backupId . '/';
        $manifestFile = $backupDir . 'manifest.json';
        
        if (!file_exists($manifestFile)) {
            return null;
        }
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if ($manifest) {
            $manifest['path'] = $backupDir;
            $manifest['size_formatted'] = $this->formatBytes($manifest['size'] ?? 0);
        }
        
        return $manifest;
    }
    
    /**
     * Delete backup
     */
    public function deleteBackup($backupId) {
        $backupDir = $this->backupPath . $backupId . '/';
        
        if (!is_dir($backupDir)) {
            throw new Exception('Backup not found');
        }
        
        return $this->removeDirectory($backupDir);
    }
    
    /**
     * Compress backup directory
     */
    private function compressBackup($backupDir, $backupId) {
        $compressedFile = $this->backupPath . $backupId . '.tar.gz';
        
        $command = 'tar -czf ' . escapeshellarg($compressedFile) . ' -C ' . escapeshellarg($this->backupPath) . ' ' . escapeshellarg($backupId);
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($compressedFile)) {
            // Remove uncompressed directory
            $this->removeDirectory($backupDir);
        }
    }
    
    /**
     * Clean up old backups
     */
    private function cleanupOldBackups() {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            $backupsToDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['backup_id']);
            }
        }
    }
    
    /**
     * Get directory size
     */
    private function getDirectorySize($directory) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory($directory) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($directory);
    }
    
    /**
     * Schedule automatic backup
     */
    public function scheduleBackup($frequency = 'daily', $time = '02:00') {
        // This would typically integrate with a cron job or task scheduler
        // For now, we'll just log the schedule
        $schedule = [
            'frequency' => $frequency,
            'time' => $time,
            'next_run' => $this->calculateNextRun($frequency, $time),
            'enabled' => true
        ];
        
        // Store schedule in database or config file
        $scheduleFile = $this->backupPath . 'schedule.json';
        file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
        
        return $schedule;
    }
    
    /**
     * Calculate next run time
     */
    private function calculateNextRun($frequency, $time) {
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
}
