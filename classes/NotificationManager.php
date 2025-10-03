<?php
/**
 * Notification Manager Class
 * Handles email and SMS notifications across the system
 */

class NotificationManager {
    private $db;
    private $config;
    private $emailSettings;
    private $smsSettings;
    
    public function __construct($database, $systemConfig) {
        // Handle both Database object and PDO connection
        if ($database instanceof PDO) {
            $this->db = $database;
        } else {
            $this->db = $database->getConnection();
        }
        
        $this->config = $systemConfig;
        $this->emailSettings = $this->config->getEmailSettings();
        $this->smsSettings = $this->config->getSmsSettings();
    }
    
    /**
     * Send notification (email and/or SMS)
     */
    public function sendNotification($userId, $type, $data, $channels = ['email']) {
        try {
            // Get user details
            $user = $this->getUserById($userId);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Prepare notification content
            $content = $this->prepareNotificationContent($type, $data);
            
            // Log notification
            $notificationId = $this->logNotification($userId, $type, $content, $channels);
            
            // Send via specified channels
            $results = [];
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $results['email'] = $this->sendEmail($user['email'], $content['subject'], $content['body']);
                        break;
                    case 'sms':
                        $results['sms'] = $this->sendSMS($user['phone'], $content['sms_body']);
                        break;
                    case 'push':
                        $results['push'] = $this->sendPushNotification($userId, $content['title'], $content['body']);
                        break;
                }
            }
            
            // Update notification status
            $this->updateNotificationStatus($notificationId, $results);
            
            return [
                'success' => true,
                'notification_id' => $notificationId,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmail($to, $subject, $body) {
        try {
            // Use PHPMailer or similar for production
            // For now, we'll use basic mail() function
            
            $headers = [
                'From: ' . $this->emailSettings['from_email'],
                'Reply-To: ' . $this->emailSettings['from_email'],
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            $htmlBody = $this->wrapEmailTemplate($body);
            
            $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            
            return [
                'success' => $result,
                'channel' => 'email',
                'to' => $to
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'channel' => 'email',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS notification
     */
    private function sendSMS($phone, $message) {
        try {
            // In production, integrate with SMS service like Twilio, Africa's Talking, etc.
            // For now, we'll simulate SMS sending
            
            if (empty($phone)) {
                throw new Exception("Phone number not provided");
            }
            
            // Simulate SMS sending
            $result = true; // This would be actual SMS API call
            
            return [
                'success' => $result,
                'channel' => 'sms',
                'to' => $phone
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'channel' => 'sms',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send push notification
     */
    private function sendPushNotification($userId, $title, $body) {
        try {
            // In production, integrate with Firebase Cloud Messaging or similar
            // For now, we'll simulate push notification
            
            $result = true; // This would be actual push notification API call
            
            return [
                'success' => $result,
                'channel' => 'push',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'channel' => 'push',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare notification content based on type
     */
    private function prepareNotificationContent($type, $data) {
        $templates = [
            'application_submitted' => [
                'subject' => 'Application Submitted Successfully',
                'title' => 'Application Submitted',
                'body' => "
                    <h2>Application Submitted Successfully!</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>Your application for <strong>{$data['program_name']}</strong> has been submitted successfully.</p>
                    <p><strong>Application ID:</strong> {$data['application_id']}</p>
                    <p><strong>Program:</strong> {$data['program_name']} ({$data['program_code']})</p>
                    <p><strong>Submitted:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    <p>We will review your application and notify you of the status within 5-7 business days.</p>
                    <p>Thank you for choosing our institution!</p>
                ",
                'sms_body' => "Application submitted for {$data['program_name']}. ID: {$data['application_id']}. We'll review and notify you within 5-7 days."
            ],
            
            'application_approved' => [
                'subject' => 'Application Approved - Congratulations!',
                'title' => 'Application Approved',
                'body' => "
                    <h2>Congratulations! Your Application Has Been Approved</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>We are pleased to inform you that your application for <strong>{$data['program_name']}</strong> has been approved!</p>
                    <p><strong>Application ID:</strong> {$data['application_id']}</p>
                    <p><strong>Program:</strong> {$data['program_name']} ({$data['program_code']})</p>
                    <p><strong>Approved:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    <p>Next steps will be communicated to you shortly. Welcome to our institution!</p>
                    <p>Congratulations again!</p>
                ",
                'sms_body' => "Congratulations! Your application for {$data['program_name']} has been approved. Welcome to our institution!"
            ],
            
            'application_rejected' => [
                'subject' => 'Application Status Update',
                'title' => 'Application Update',
                'body' => "
                    <h2>Application Status Update</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>Thank you for your interest in our institution. After careful review, we regret to inform you that your application for <strong>{$data['program_name']}</strong> was not successful.</p>
                    <p><strong>Application ID:</strong> {$data['application_id']}</p>
                    <p><strong>Program:</strong> {$data['program_name']} ({$data['program_code']})</p>
                    <p><strong>Decision Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    <p>We encourage you to apply for other programs or reapply in future admission cycles.</p>
                    <p>Thank you for considering our institution.</p>
                ",
                'sms_body' => "Application for {$data['program_name']} was not successful. We encourage you to apply for other programs."
            ],
            
            'payment_required' => [
                'subject' => 'Payment Required for Application',
                'title' => 'Payment Required',
                'body' => "
                    <h2>Payment Required</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>Your application for <strong>{$data['program_name']}</strong> requires payment to proceed.</p>
                    <p><strong>Application ID:</strong> {$data['application_id']}</p>
                    <p><strong>Amount Due:</strong> {$data['amount']}</p>
                    <p><strong>Due Date:</strong> {$data['due_date']}</p>
                    <p>Please complete your payment to continue with the application process.</p>
                    <p><a href='{$data['payment_link']}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Pay Now</a></p>
                ",
                'sms_body' => "Payment required for {$data['program_name']} application. Amount: {$data['amount']}. Due: {$data['due_date']}"
            ],
            
            'payment_received' => [
                'subject' => 'Payment Received - Thank You!',
                'title' => 'Payment Received',
                'body' => "
                    <h2>Payment Received Successfully</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>Thank you! We have received your payment for the application fee.</p>
                    <p><strong>Application ID:</strong> {$data['application_id']}</p>
                    <p><strong>Amount Paid:</strong> {$data['amount']}</p>
                    <p><strong>Transaction ID:</strong> {$data['transaction_id']}</p>
                    <p><strong>Payment Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    <p>Your application will now proceed to the review stage.</p>
                    <p>Thank you for your payment!</p>
                ",
                'sms_body' => "Payment received for {$data['program_name']} application. Amount: {$data['amount']}. Transaction ID: {$data['transaction_id']}"
            ],
            
            'deadline_reminder' => [
                'subject' => 'Application Deadline Reminder',
                'title' => 'Deadline Reminder',
                'body' => "
                    <h2>Application Deadline Reminder</h2>
                    <p>Dear {$data['student_name']},</p>
                    <p>This is a friendly reminder that the application deadline for <strong>{$data['program_name']}</strong> is approaching.</p>
                    <p><strong>Program:</strong> {$data['program_name']} ({$data['program_code']})</p>
                    <p><strong>Deadline:</strong> {$data['deadline']}</p>
                    <p><strong>Days Remaining:</strong> {$data['days_remaining']}</p>
                    <p>Please ensure you complete your application before the deadline.</p>
                    <p><a href='{$data['application_link']}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Complete Application</a></p>
                ",
                'sms_body' => "Application deadline for {$data['program_name']} is {$data['deadline']}. {$data['days_remaining']} days remaining."
            ]
        ];
        
        if (!isset($templates[$type])) {
            throw new Exception("Unknown notification type: $type");
        }
        
        return $templates[$type];
    }
    
    /**
     * Wrap email content in HTML template
     */
    private function wrapEmailTemplate($body) {
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Notification</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                    a { color: #007bff; text-decoration: none; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>" . APP_NAME . "</h1>
                    </div>
                    <div class='content'>
                        $body
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
    
    /**
     * Log notification in database
     */
    private function logNotification($userId, $type, $content, $channels) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, channels, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $channelsJson = json_encode($channels);
            $stmt->execute([
                $userId,
                $type,
                $content['title'],
                $content['body'],
                $channelsJson
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Notification logging error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update notification status
     */
    private function updateNotificationStatus($notificationId, $results) {
        if (!$notificationId) return;
        
        try {
            $status = 'delivered';
            foreach ($results as $result) {
                if (!$result['success']) {
                    $status = 'failed';
                    break;
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET status = ?, results = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$status, json_encode($results), $notificationId]);
            
        } catch (Exception $e) {
            error_log("Notification status update error: " . $e->getMessage());
        }
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, phone 
                FROM users 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get SMS settings
     */
    private function getSmsSettings() {
        $sms = $this->config->getByCategory('sms');
        
        $defaults = [
            'provider' => 'twilio', // twilio, africas_talking, etc.
            'api_key' => '',
            'api_secret' => '',
            'sender_id' => APP_NAME,
            'enabled' => '1'
        ];
        
        return array_merge($defaults, $sms);
    }
    
    /**
     * Get notifications for user
     */
    public function getUserNotifications($userId, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }
}
?>