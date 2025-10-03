<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/SystemConfig.php';
require_once '../classes/NotificationManager.php';

// Initialize database and system config
$database = new Database();
$systemConfig = new SystemConfig($database);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get notifications for user
        $userId = (int)($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            throw new Exception('User ID required');
        }
        
        $notificationManager = new NotificationManager($database, $systemConfig);
        $notifications = $notificationManager->getUserNotifications($userId, 50);
        
        // Count unread notifications
        $unreadCount = 0;
        foreach ($notifications as $notification) {
            if (!$notification['is_read']) {
                $unreadCount++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle POST requests (mark as read, etc.)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            throw new Exception('Invalid request');
        }
        
        $notificationManager = new NotificationManager($database, $systemConfig);
        
        switch ($input['action']) {
            case 'mark_read':
                $notificationId = (int)$input['notification_id'];
                $userId = (int)$_SESSION['user_id'] ?? 0;
                
                if (!$notificationId || !$userId) {
                    throw new Exception('Invalid parameters');
                }
                
                $success = $notificationManager->markAsRead($notificationId, $userId);
                
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
                ]);
                break;
                
            case 'mark_all_read':
                $userId = (int)$input['user_id'];
                
                if (!$userId) {
                    throw new Exception('User ID required');
                }
                
                // Mark all notifications as read for user
                $stmt = $database->getConnection()->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_id = ? AND is_read = 0
                ");
                $success = $stmt->execute([$userId]);
                
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications as read'
                ]);
                break;
                
            default:
                throw new Exception('Unknown action');
        }
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
