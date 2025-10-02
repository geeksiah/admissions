<?php
/**
 * Enhanced Notification Manager
 * Handles advanced notification features including reminders, countdown timers, and scheduled notifications
 */
class EnhancedNotificationManager {
    private $database;
    private $notificationManager;
    
    public function __construct($database) {
        $this->database = $database;
        $this->notificationManager = new NotificationManager($database);
    }
    
    /**
     * Send application deadline reminder
     */
    public function sendApplicationDeadlineReminder($applicationId, $daysBeforeDeadline = 7) {
        try {
            // Get application details
            $stmt = $this->database->prepare("
                SELECT a.*, p.program_name, s.first_name, s.last_name, s.email, s.phone
                FROM applications a
                JOIN programs p ON a.program_id = p.id
                JOIN students s ON a.student_id = s.id
                WHERE a.id = ? AND a.status = 'submitted'
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                return false;
            }
            
            // Check if deadline reminder is needed
            $deadline = new DateTime($application['deadline']);
            $now = new DateTime();
            $daysRemaining = $now->diff($deadline)->days;
            
            if ($daysRemaining <= $daysBeforeDeadline && $daysRemaining > 0) {
                // Send email reminder
                $emailData = [
                    'to' => $application['email'],
                    'subject' => 'Application Deadline Reminder - ' . $application['program_name'],
                    'template' => 'application_deadline_reminder',
                    'data' => [
                        'student_name' => $application['first_name'] . ' ' . $application['last_name'],
                        'program_name' => $application['program_name'],
                        'application_id' => $application['application_id'],
                        'deadline' => $deadline->format('F j, Y'),
                        'days_remaining' => $daysRemaining,
                        'application_url' => APP_URL . '/student/applications.php'
                    ]
                ];
                
                $this->notificationManager->sendEmail($emailData);
                
                // Send SMS reminder if phone number is available
                if (!empty($application['phone'])) {
                    $smsData = [
                        'to' => $application['phone'],
                        'message' => "Reminder: Your application for {$application['program_name']} is due in {$daysRemaining} days. Deadline: {$deadline->format('M j, Y')}",
                        'template' => 'application_deadline_reminder_sms'
                    ];
                    
                    $this->notificationManager->sendSms($smsData);
                }
                
                // Log the reminder
                $this->logNotification('application_deadline_reminder', $applicationId, $daysRemaining);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Failed to send application deadline reminder: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment due reminder
     */
    public function sendPaymentDueReminder($applicationId, $daysBeforeDue = 3) {
        try {
            // Get application and payment details
            $stmt = $this->database->prepare("
                SELECT a.*, p.program_name, s.first_name, s.last_name, s.email, s.phone,
                       pt.amount, pt.due_date, pt.status as payment_status
                FROM applications a
                JOIN programs p ON a.program_id = p.id
                JOIN students s ON a.student_id = s.id
                LEFT JOIN payment_transactions pt ON a.id = pt.application_id
                WHERE a.id = ? AND a.status = 'submitted'
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application || $application['payment_status'] === 'completed') {
                return false;
            }
            
            // Check if payment reminder is needed
            $dueDate = new DateTime($application['due_date']);
            $now = new DateTime();
            $daysRemaining = $now->diff($dueDate)->days;
            
            if ($daysRemaining <= $daysBeforeDue && $daysRemaining > 0) {
                // Send email reminder
                $emailData = [
                    'to' => $application['email'],
                    'subject' => 'Payment Due Reminder - ' . $application['program_name'],
                    'template' => 'payment_due_reminder',
                    'data' => [
                        'student_name' => $application['first_name'] . ' ' . $application['last_name'],
                        'program_name' => $application['program_name'],
                        'application_id' => $application['application_id'],
                        'amount' => $application['amount'],
                        'due_date' => $dueDate->format('F j, Y'),
                        'days_remaining' => $daysRemaining,
                        'payment_url' => APP_URL . '/student/payment.php?id=' . $application['id']
                    ]
                ];
                
                $this->notificationManager->sendEmail($emailData);
                
                // Send SMS reminder if phone number is available
                if (!empty($application['phone'])) {
                    $smsData = [
                        'to' => $application['phone'],
                        'message' => "Payment reminder: {$application['amount']} due in {$daysRemaining} days for {$application['program_name']}. Due: {$dueDate->format('M j, Y')}",
                        'template' => 'payment_due_reminder_sms'
                    ];
                    
                    $this->notificationManager->sendSms($smsData);
                }
                
                // Log the reminder
                $this->logNotification('payment_due_reminder', $applicationId, $daysRemaining);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Failed to send payment due reminder: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send document submission reminder
     */
    public function sendDocumentSubmissionReminder($applicationId, $daysAfterSubmission = 3) {
        try {
            // Get application details
            $stmt = $this->database->prepare("
                SELECT a.*, p.program_name, s.first_name, s.last_name, s.email, s.phone
                FROM applications a
                JOIN programs p ON a.program_id = p.id
                JOIN students s ON a.student_id = s.id
                WHERE a.id = ? AND a.status = 'submitted'
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                return false;
            }
            
            // Check if document reminder is needed
            $submissionDate = new DateTime($application['submitted_at']);
            $now = new DateTime();
            $daysSinceSubmission = $now->diff($submissionDate)->days;
            
            if ($daysSinceSubmission >= $daysAfterSubmission) {
                // Check if documents are missing
                $stmt = $this->database->prepare("
                    SELECT COUNT(*) as missing_docs
                    FROM application_requirements ar
                    LEFT JOIN application_documents ad ON ar.id = ad.requirement_id AND ad.application_id = ?
                    WHERE ar.is_mandatory = 1 AND ad.id IS NULL
                ");
                $stmt->execute([$applicationId]);
                $missingDocs = $stmt->fetch(PDO::FETCH_ASSOC)['missing_docs'];
                
                if ($missingDocs > 0) {
                    // Send email reminder
                    $emailData = [
                        'to' => $application['email'],
                        'subject' => 'Document Submission Reminder - ' . $application['program_name'],
                        'template' => 'document_submission_reminder',
                        'data' => [
                            'student_name' => $application['first_name'] . ' ' . $application['last_name'],
                            'program_name' => $application['program_name'],
                            'application_id' => $application['application_id'],
                            'missing_documents' => $missingDocs,
                            'application_url' => APP_URL . '/student/applications.php'
                        ]
                    ];
                    
                    $this->notificationManager->sendEmail($emailData);
                    
                    // Log the reminder
                    $this->logNotification('document_submission_reminder', $applicationId, $missingDocs);
                    
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Failed to send document submission reminder: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send countdown timer notifications
     */
    public function sendCountdownNotifications($applicationId, $hoursBeforeDeadline = [24, 2, 1]) {
        try {
            // Get application details
            $stmt = $this->database->prepare("
                SELECT a.*, p.program_name, s.first_name, s.last_name, s.email, s.phone
                FROM applications a
                JOIN programs p ON a.program_id = p.id
                JOIN students s ON a.student_id = s.id
                WHERE a.id = ? AND a.status = 'submitted'
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                return false;
            }
            
            $deadline = new DateTime($application['deadline']);
            $now = new DateTime();
            $hoursRemaining = $now->diff($deadline)->h + ($now->diff($deadline)->days * 24);
            
            // Check if countdown notification is needed
            foreach ($hoursBeforeDeadline as $hours) {
                if ($hoursRemaining <= $hours && $hoursRemaining > 0) {
                    // Check if notification was already sent
                    $stmt = $this->database->prepare("
                        SELECT COUNT(*) as sent
                        FROM notification_log
                        WHERE application_id = ? AND notification_type = 'countdown_timer' 
                        AND JSON_EXTRACT(data, '$.hours') = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ");
                    $stmt->execute([$applicationId, $hours]);
                    $alreadySent = $stmt->fetch(PDO::FETCH_ASSOC)['sent'];
                    
                    if (!$alreadySent) {
                        // Send countdown notification
                        $emailData = [
                            'to' => $application['email'],
                            'subject' => 'URGENT: Application Deadline in ' . $hours . ' Hours',
                            'template' => 'countdown_timer',
                            'data' => [
                                'student_name' => $application['first_name'] . ' ' . $application['last_name'],
                                'program_name' => $application['program_name'],
                                'application_id' => $application['application_id'],
                                'deadline' => $deadline->format('F j, Y g:i A'),
                                'hours_remaining' => $hoursRemaining,
                                'application_url' => APP_URL . '/student/applications.php'
                            ]
                        ];
                        
                        $this->notificationManager->sendEmail($emailData);
                        
                        // Send SMS if phone number is available
                        if (!empty($application['phone'])) {
                            $smsData = [
                                'to' => $application['phone'],
                                'message' => "URGENT: Application for {$application['program_name']} due in {$hoursRemaining} hours! Deadline: {$deadline->format('M j, Y g:i A')}",
                                'template' => 'countdown_timer_sms'
                            ];
                            
                            $this->notificationManager->sendSms($smsData);
                        }
                        
                        // Log the notification
                        $this->logNotification('countdown_timer', $applicationId, $hours, ['hours' => $hours]);
                        
                        return true;
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Failed to send countdown notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process all pending reminders
     */
    public function processPendingReminders() {
        $processed = 0;
        
        try {
            // Get applications that need deadline reminders
            $stmt = $this->database->prepare("
                SELECT id FROM applications 
                WHERE status = 'submitted' 
                AND deadline > NOW() 
                AND deadline <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($applications as $applicationId) {
                if ($this->sendApplicationDeadlineReminder($applicationId)) {
                    $processed++;
                }
            }
            
            // Get applications that need payment reminders
            $stmt = $this->database->prepare("
                SELECT a.id FROM applications a
                LEFT JOIN payment_transactions pt ON a.id = pt.application_id
                WHERE a.status = 'submitted' 
                AND pt.due_date > NOW() 
                AND pt.due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND pt.status != 'completed'
            ");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($applications as $applicationId) {
                if ($this->sendPaymentDueReminder($applicationId)) {
                    $processed++;
                }
            }
            
            // Get applications that need document reminders
            $stmt = $this->database->prepare("
                SELECT id FROM applications 
                WHERE status = 'submitted' 
                AND submitted_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($applications as $applicationId) {
                if ($this->sendDocumentSubmissionReminder($applicationId)) {
                    $processed++;
                }
            }
            
            // Process countdown notifications
            $stmt = $this->database->prepare("
                SELECT id FROM applications 
                WHERE status = 'submitted' 
                AND deadline > NOW() 
                AND deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($applications as $applicationId) {
                if ($this->sendCountdownNotifications($applicationId)) {
                    $processed++;
                }
            }
            
        } catch (Exception $e) {
            error_log('Failed to process pending reminders: ' . $e->getMessage());
        }
        
        return $processed;
    }
    
    /**
     * Log notification
     */
    private function logNotification($type, $applicationId, $value, $additionalData = []) {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO notification_log (notification_type, application_id, data, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $data = array_merge(['value' => $value], $additionalData);
            $stmt->execute([$type, $applicationId, json_encode($data)]);
            
        } catch (Exception $e) {
            error_log('Failed to log notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStatistics($days = 30) {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    notification_type,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM notification_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY notification_type, DATE(created_at)
                ORDER BY date DESC, notification_type
            ");
            $stmt->execute([$days]);
            $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $statistics;
            
        } catch (Exception $e) {
            error_log('Failed to get notification statistics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread notification count for user
     */
    public function getUnreadNotificationCount($userId) {
        try {
            $stmt = $this->database->prepare("
                SELECT COUNT(*) as count
                FROM internal_messages
                WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log('Failed to get unread notification count: ' . $e->getMessage());
            return 0;
        }
    }
}
