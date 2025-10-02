<?php
/**
 * Notification Model
 * Handles email and SMS notifications
 */

class Notification {
    private $db;
    
    public function __construct($database) {
        // Handle both Database object and PDO connection
        if ($database instanceof PDO) {
            $this->db = $database;
        } else {
            $this->db = $database->getConnection();
        }
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $templateId = null, $variables = []) {
        try {
            // Get email template if template ID provided
            if ($templateId) {
                $template = $this->getEmailTemplate($templateId);
                if ($template) {
                    $subject = $this->replaceVariables($template['subject'], $variables);
                    $message = $this->replaceVariables($template['body'], $variables);
                }
            }
            
            // Add to email queue
            $queueId = $this->addToEmailQueue($to, $subject, $message, $templateId, $variables);
            
            // Try to send immediately if configured
            if (EMAIL_SEND_IMMEDIATELY) {
                return $this->processEmailQueue($queueId);
            }
            
            return $queueId;
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS notification
     */
    public function sendSMS($to, $message, $templateId = null, $variables = []) {
        try {
            // Get SMS template if template ID provided
            if ($templateId) {
                $template = $this->getSMSTemplate($templateId);
                if ($template) {
                    $message = $this->replaceVariables($template['body'], $variables);
                }
            }
            
            // Add to SMS queue
            $queueId = $this->addToSMSQueue($to, $message, $templateId, $variables);
            
            // Try to send immediately if configured
            if (SMS_SEND_IMMEDIATELY) {
                return $this->processSMSQueue($queueId);
            }
            
            return $queueId;
        } catch (Exception $e) {
            error_log("SMS sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template by ID
     */
    public function getEmailTemplate($templateId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_templates WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$templateId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Email template fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get SMS template by ID
     */
    public function getSMSTemplate($templateId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sms_templates WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$templateId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("SMS template fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all email templates
     */
    public function getEmailTemplates($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = 'status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(template_name LIKE ? OR subject LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT * FROM email_templates 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $templates = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM email_templates WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'templates' => $templates,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Email templates fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create email template
     */
    public function createEmailTemplate($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates (
                    template_name, subject, body, variables, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['template_name'],
                $data['subject'],
                $data['body'],
                json_encode($data['variables'] ?? []),
                $data['status'] ?? 'active',
                $data['created_by']
            ]);
        } catch (Exception $e) {
            error_log("Email template creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update email template
     */
    public function updateEmailTemplate($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if ($key === 'variables') {
                    $fields[] = "$key = ?";
                    $values[] = json_encode($value);
                } else {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $id;
            
            $stmt = $this->db->prepare("
                UPDATE email_templates SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Email template update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add email to queue
     */
    private function addToEmailQueue($to, $subject, $message, $templateId = null, $variables = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (
                    recipient_email, subject, message_body, template_id, variables, 
                    status, priority, scheduled_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', 'normal', NOW())
            ");
            
            $stmt->execute([
                $to,
                $subject,
                $message,
                $templateId,
                json_encode($variables)
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Email queue addition error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add SMS to queue
     */
    private function addToSMSQueue($to, $message, $templateId = null, $variables = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sms_queue (
                    recipient_phone, message_body, template_id, variables, 
                    status, priority, scheduled_at
                ) VALUES (?, ?, ?, ?, 'pending', 'normal', NOW())
            ");
            
            $stmt->execute([
                $to,
                $message,
                $templateId,
                json_encode($variables)
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("SMS queue addition error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process email queue
     */
    public function processEmailQueue($queueId = null) {
        try {
            $where = $queueId ? "id = $queueId" : "status = 'pending' AND scheduled_at <= NOW()";
            $limit = $queueId ? 1 : EMAIL_BATCH_SIZE;
            
            $stmt = $this->db->prepare("
                SELECT * FROM email_queue 
                WHERE $where 
                ORDER BY priority DESC, created_at ASC 
                LIMIT $limit
            ");
            $stmt->execute();
            $emails = $stmt->fetchAll();
            
            $processed = 0;
            foreach ($emails as $email) {
                if ($this->sendEmailViaProvider($email)) {
                    $this->updateEmailQueueStatus($email['id'], 'sent', null);
                    $processed++;
                } else {
                    $this->updateEmailQueueStatus($email['id'], 'failed', 'Failed to send via provider');
                }
            }
            
            return $processed;
        } catch (Exception $e) {
            error_log("Email queue processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process SMS queue
     */
    public function processSMSQueue($queueId = null) {
        try {
            $where = $queueId ? "id = $queueId" : "status = 'pending' AND scheduled_at <= NOW()";
            $limit = $queueId ? 1 : SMS_BATCH_SIZE;
            
            $stmt = $this->db->prepare("
                SELECT * FROM sms_queue 
                WHERE $where 
                ORDER BY priority DESC, created_at ASC 
                LIMIT $limit
            ");
            $stmt->execute();
            $smsMessages = $stmt->fetchAll();
            
            $processed = 0;
            foreach ($smsMessages as $sms) {
                if ($this->sendSMSViaProvider($sms)) {
                    $this->updateSMSQueueStatus($sms['id'], 'sent', null);
                    $processed++;
                } else {
                    $this->updateSMSQueueStatus($sms['id'], 'failed', 'Failed to send via provider');
                }
            }
            
            return $processed;
        } catch (Exception $e) {
            error_log("SMS queue processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via provider (SMTP, SendGrid, etc.)
     */
    private function sendEmailViaProvider($email) {
        try {
            // Use PHPMailer or similar library
            // This is a simplified version - implement with actual email provider
            
            $headers = [
                'From: ' . EMAIL_FROM_ADDRESS,
                'Reply-To: ' . EMAIL_FROM_ADDRESS,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            return mail(
                $email['recipient_email'],
                $email['subject'],
                $email['message_body'],
                implode("\r\n", $headers)
            );
        } catch (Exception $e) {
            error_log("Email provider error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS via provider (Twilio, etc.)
     */
    private function sendSMSViaProvider($sms) {
        try {
            // Use Twilio or similar SMS provider
            // This is a simplified version - implement with actual SMS provider
            
            // For now, just log the SMS
            error_log("SMS to {$sms['recipient_phone']}: {$sms['message_body']}");
            return true;
        } catch (Exception $e) {
            error_log("SMS provider error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update email queue status
     */
    private function updateEmailQueueStatus($id, $status, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_queue 
                SET status = ?, error_message = ?, sent_at = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$status, $errorMessage, $id]);
        } catch (Exception $e) {
            error_log("Email queue status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update SMS queue status
     */
    private function updateSMSQueueStatus($id, $status, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE sms_queue 
                SET status = ?, error_message = ?, sent_at = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$status, $errorMessage, $id]);
        } catch (Exception $e) {
            error_log("SMS queue status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Replace variables in template
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }
        return $text;
    }
    
    /**
     * Get notification statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM email_queue WHERE status = 'sent') as emails_sent,
                    (SELECT COUNT(*) FROM email_queue WHERE status = 'pending') as emails_pending,
                    (SELECT COUNT(*) FROM email_queue WHERE status = 'failed') as emails_failed,
                    (SELECT COUNT(*) FROM sms_queue WHERE status = 'sent') as sms_sent,
                    (SELECT COUNT(*) FROM sms_queue WHERE status = 'pending') as sms_pending,
                    (SELECT COUNT(*) FROM sms_queue WHERE status = 'failed') as sms_failed
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Notification statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send application status notification
     */
    public function sendApplicationStatusNotification($applicationId, $status, $studentEmail, $studentName) {
        $variables = [
            'student_name' => $studentName,
            'application_id' => $applicationId,
            'status' => ucfirst(str_replace('_', ' ', $status)),
            'university_name' => APP_NAME
        ];
        
        $templateId = $this->getTemplateIdByStatus($status);
        return $this->sendEmail($studentEmail, '', '', $templateId, $variables);
    }
    
    /**
     * Send payment confirmation notification
     */
    public function sendPaymentConfirmation($applicationId, $studentEmail, $studentName, $amount) {
        $variables = [
            'student_name' => $studentName,
            'application_id' => $applicationId,
            'amount' => formatCurrency($amount),
            'university_name' => APP_NAME
        ];
        
        return $this->sendEmail($studentEmail, '', '', 'payment_confirmation', $variables);
    }
    
    /**
     * Get template ID by application status
     */
    private function getTemplateIdByStatus($status) {
        $templateMap = [
            'submitted' => 'application_submitted',
            'under_review' => 'application_under_review',
            'approved' => 'application_approved',
            'rejected' => 'application_rejected',
            'waitlisted' => 'application_waitlisted'
        ];
        
        return $templateMap[$status] ?? 'application_status_update';
    }
    
    /**
     * Clean old queue entries
     */
    public function cleanOldQueueEntries($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM email_queue 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                AND status IN ('sent', 'failed')
            ");
            $stmt->execute([$days]);
            
            $stmt = $this->db->prepare("
                DELETE FROM sms_queue 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                AND status IN ('sent', 'failed')
            ");
            $stmt->execute([$days]);
            
            return true;
        } catch (Exception $e) {
            error_log("Queue cleanup error: " . $e->getMessage());
            return false;
        }
    }
}
