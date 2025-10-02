<?php
/**
 * Notification Manager
 * Handles multiple email and SMS provider integrations
 */

class NotificationManager {
    private $db;
    private $emailProviders = [];
    private $smsProviders = [];
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadProviders();
    }
    
    /**
     * Load all active notification providers
     */
    private function loadProviders() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM notification_providers 
                WHERE is_active = 1 
                ORDER BY provider_type, priority ASC
            ");
            $stmt->execute();
            $providers = $stmt->fetchAll();
            
            foreach ($providers as $provider) {
                if ($provider['provider_type'] === 'email') {
                    $this->emailProviders[] = $provider;
                } else {
                    $this->smsProviders[] = $provider;
                }
            }
        } catch (Exception $e) {
            error_log("Notification provider loading error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email using available providers
     */
    public function sendEmail($to, $subject, $body, $isHtml = true, $priority = 'normal', $templateId = null) {
        $emailProviders = $this->getEmailProviders();
        
        if (empty($emailProviders)) {
            return ['success' => false, 'error' => 'No email providers configured'];
        }
        
        // Try providers in order of priority
        foreach ($emailProviders as $provider) {
            $result = $this->sendEmailViaProvider($provider, $to, $subject, $body, $isHtml, $priority, $templateId);
            
            if ($result['success']) {
                return $result;
            }
            
            // Log failure and try next provider
            error_log("Email provider {$provider['provider_name']} failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return ['success' => false, 'error' => 'All email providers failed'];
    }
    
    /**
     * Send SMS using available providers
     */
    public function sendSMS($to, $message, $priority = 'normal', $templateId = null) {
        $smsProviders = $this->getSMSProviders();
        
        if (empty($smsProviders)) {
            return ['success' => false, 'error' => 'No SMS providers configured'];
        }
        
        // Try providers in order of priority
        foreach ($smsProviders as $provider) {
            $result = $this->sendSMSViaProvider($provider, $to, $message, $priority, $templateId);
            
            if ($result['success']) {
                return $result;
            }
            
            // Log failure and try next provider
            error_log("SMS provider {$provider['provider_name']} failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return ['success' => false, 'error' => 'All SMS providers failed'];
    }
    
    /**
     * Send email via specific provider
     */
    private function sendEmailViaProvider($provider, $to, $subject, $body, $isHtml, $priority, $templateId) {
        try {
            switch ($provider['provider_name']) {
                case 'smtp':
                    return $this->sendEmailViaSMTP($provider, $to, $subject, $body, $isHtml);
                case 'sendgrid':
                    return $this->sendEmailViaSendGrid($provider, $to, $subject, $body, $isHtml);
                case 'mailgun':
                    return $this->sendEmailViaMailgun($provider, $to, $subject, $body, $isHtml);
                case 'ses':
                    return $this->sendEmailViaSES($provider, $to, $subject, $body, $isHtml);
                default:
                    return ['success' => false, 'error' => 'Unsupported email provider'];
            }
        } catch (Exception $e) {
            error_log("Email sending error for {$provider['provider_name']}: " . $e->getMessage());
            return ['success' => false, 'error' => 'Email sending failed'];
        }
    }
    
    /**
     * Send SMS via specific provider
     */
    private function sendSMSViaProvider($provider, $to, $message, $priority, $templateId) {
        try {
            switch ($provider['provider_name']) {
                case 'twilio':
                    return $this->sendSMSViaTwilio($provider, $to, $message);
                case 'nexmo':
                    return $this->sendSMSViaNexmo($provider, $to, $message);
                case 'africastalking':
                    return $this->sendSMSViaAfricasTalking($provider, $to, $message);
                case 'hubtel':
                    return $this->sendSMSViaHubtel($provider, $to, $message);
                case 'bulksms':
                    return $this->sendSMSViaBulkSMS($provider, $to, $message);
                default:
                    return ['success' => false, 'error' => 'Unsupported SMS provider'];
            }
        } catch (Exception $e) {
            error_log("SMS sending error for {$provider['provider_name']}: " . $e->getMessage());
            return ['success' => false, 'error' => 'SMS sending failed'];
        }
    }
    
    /**
     * Send email via SMTP
     */
    private function sendEmailViaSMTP($provider, $to, $subject, $body, $isHtml) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $config['encryption'];
            $mail->Port = $config['port'];
            
            // Recipients
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send email via SendGrid
     */
    private function sendEmailViaSendGrid($provider, $to, $subject, $body, $isHtml) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'personalizations' => [
                    [
                        'to' => [
                            ['email' => $to]
                        ]
                    ]
                ],
                'from' => [
                    'email' => $config['from_email'],
                    'name' => $config['from_name']
                ],
                'subject' => $subject,
                'content' => [
                    [
                        'type' => $isHtml ? 'text/html' : 'text/plain',
                        'value' => $body
                    ]
                ]
            ];
            
            $response = $this->makeHttpRequest(
                'https://api.sendgrid.com/v3/mail/send',
                $data,
                [
                    'Authorization: Bearer ' . $config['api_key'],
                    'Content-Type: application/json'
                ],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send email via Mailgun
     */
    private function sendEmailViaMailgun($provider, $to, $subject, $body, $isHtml) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'from' => $config['from_name'] . ' <' . $config['from_email'] . '>',
                'to' => $to,
                'subject' => $subject,
                'html' => $isHtml ? $body : null,
                'text' => $isHtml ? strip_tags($body) : $body
            ];
            
            $response = $this->makeHttpRequest(
                'https://api.mailgun.net/v3/' . $config['domain'] . '/messages',
                $data,
                [
                    'Authorization: Basic ' . base64_encode('api:' . $config['api_key'])
                ],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send email via Amazon SES
     */
    private function sendEmailViaSES($provider, $to, $subject, $body, $isHtml) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            // This would require AWS SDK
            // For now, return a placeholder
            return ['success' => false, 'error' => 'Amazon SES integration requires AWS SDK'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS via Twilio
     */
    private function sendSMSViaTwilio($provider, $to, $message) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'From' => $config['from_number'],
                'To' => $to,
                'Body' => $message
            ];
            
            $response = $this->makeHttpRequest(
                'https://api.twilio.com/2010-04-01/Accounts/' . $config['account_sid'] . '/Messages.json',
                $data,
                [
                    'Authorization: Basic ' . base64_encode($config['account_sid'] . ':' . $config['auth_token'])
                ],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name'], 'message_id' => $response['sid'] ?? null];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS via Nexmo (Vonage)
     */
    private function sendSMSViaNexmo($provider, $to, $message) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'api_key' => $config['api_key'],
                'api_secret' => $config['api_secret'],
                'to' => $to,
                'from' => $config['from_number'],
                'text' => $message
            ];
            
            $response = $this->makeHttpRequest(
                'https://rest.nexmo.com/sms/json',
                $data,
                ['Content-Type: application/json'],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name'], 'message_id' => $response['messages'][0]['message-id'] ?? null];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS via Africa's Talking
     */
    private function sendSMSViaAfricasTalking($provider, $to, $message) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'username' => $config['username'],
                'to' => $to,
                'message' => $message,
                'from' => $config['from_number']
            ];
            
            $response = $this->makeHttpRequest(
                'https://api.africastalking.com/version1/messaging',
                $data,
                [
                    'apiKey: ' . $config['api_key'],
                    'Content-Type: application/x-www-form-urlencoded'
                ],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS via Hubtel
     */
    private function sendSMSViaHubtel($provider, $to, $message) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'From' => $config['from_number'],
                'To' => $to,
                'Content' => $message,
                'Type' => 0,
                'RegisteredDelivery' => 0
            ];
            
            $response = $this->makeHttpRequest(
                'https://devapi.hubtel.com/v1/messages/send',
                $data,
                [
                    'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']),
                    'Content-Type: application/json'
                ],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS via BulkSMS
     */
    private function sendSMSViaBulkSMS($provider, $to, $message) {
        try {
            $config = json_decode($provider['config_data'], true);
            
            $data = [
                'username' => $config['username'],
                'password' => $config['password'],
                'message' => $message,
                'msisdn' => $to,
                'sender' => $config['from_number']
            ];
            
            $response = $this->makeHttpRequest(
                'https://api.bulksms.com/v1/messages',
                $data,
                ['Content-Type: application/x-www-form-urlencoded'],
                'POST'
            );
            
            return ['success' => true, 'provider' => $provider['provider_name']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get available email providers
     */
    public function getEmailProviders() {
        return $this->emailProviders;
    }
    
    /**
     * Get available SMS providers
     */
    public function getSMSProviders() {
        return $this->smsProviders;
    }
    
    /**
     * Get default email provider
     */
    public function getDefaultEmailProvider() {
        foreach ($this->emailProviders as $provider) {
            if ($provider['is_default']) {
                return $provider;
            }
        }
        return !empty($this->emailProviders) ? $this->emailProviders[0] : null;
    }
    
    /**
     * Get default SMS provider
     */
    public function getDefaultSMSProvider() {
        foreach ($this->smsProviders as $provider) {
            if ($provider['is_default']) {
                return $provider;
            }
        }
        return !empty($this->smsProviders) ? $this->smsProviders[0] : null;
    }
    
    /**
     * Update provider configuration
     */
    public function updateProviderConfig($providerId, $configData) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE notification_providers 
                SET config_data = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([json_encode($configData), $providerId]);
        } catch (Exception $e) {
            error_log("Provider config update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set default provider
     */
    public function setDefaultProvider($providerId) {
        try {
            $this->db->beginTransaction();
            
            // Get provider type
            $stmt = $this->db->getConnection()->prepare("SELECT provider_type FROM notification_providers WHERE id = ?");
            $stmt->execute([$providerId]);
            $provider = $stmt->fetch();
            
            if (!$provider) {
                $this->db->rollback();
                return false;
            }
            
            // Remove default from all providers of same type
            $stmt1 = $this->db->getConnection()->prepare("UPDATE notification_providers SET is_default = 0 WHERE provider_type = ?");
            $stmt1->execute([$provider['provider_type']]);
            
            // Set new default
            $stmt2 = $this->db->getConnection()->prepare("UPDATE notification_providers SET is_default = 1 WHERE id = ?");
            $result = $stmt2->execute([$providerId]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Set default provider error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle provider status
     */
    public function toggleProviderStatus($providerId, $isActive) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE notification_providers 
                SET is_active = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([$isActive ? 1 : 0, $providerId]);
        } catch (Exception $e) {
            error_log("Provider status toggle error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $data = [], $headers = [], $method = 'GET') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                if (in_array('Content-Type: application/json', $headers)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("HTTP request error: " . $error);
            return false;
        }
        
        if ($httpCode >= 400) {
            error_log("HTTP request failed with code: " . $httpCode);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Queue notification for batch processing
     */
    public function queueNotification($type, $to, $subject, $body, $templateId = null, $priority = 'normal') {
        try {
            if ($type === 'email') {
                $stmt = $this->db->getConnection()->prepare("
                    INSERT INTO email_queue (to_email, subject, body_html, body_text, template_id, priority) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                return $stmt->execute([
                    $to,
                    $subject,
                    $body,
                    strip_tags($body),
                    $templateId,
                    $priority
                ]);
            } else {
                $stmt = $this->db->getConnection()->prepare("
                    INSERT INTO sms_queue (to_phone, message, template_id, priority) 
                    VALUES (?, ?, ?, ?)
                ");
                return $stmt->execute([
                    $to,
                    $body,
                    $templateId,
                    $priority
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process notification queue
     */
    public function processQueue($type, $limit = 50) {
        try {
            if ($type === 'email') {
                $stmt = $this->db->getConnection()->prepare("
                    SELECT * FROM email_queue 
                    WHERE status = 'pending' 
                    ORDER BY priority DESC, scheduled_at ASC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $queue = $stmt->fetchAll();
                
                foreach ($queue as $item) {
                    $result = $this->sendEmail(
                        $item['to_email'],
                        $item['subject'],
                        $item['body_html'] ?: $item['body_text'],
                        !empty($item['body_html']),
                        $item['priority']
                    );
                    
                    $this->updateQueueStatus('email', $item['id'], $result['success'] ? 'sent' : 'failed', $result['error'] ?? null);
                }
            } else {
                $stmt = $this->db->getConnection()->prepare("
                    SELECT * FROM sms_queue 
                    WHERE status = 'pending' 
                    ORDER BY priority DESC, scheduled_at ASC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $queue = $stmt->fetchAll();
                
                foreach ($queue as $item) {
                    $result = $this->sendSMS(
                        $item['to_phone'],
                        $item['message'],
                        $item['priority']
                    );
                    
                    $this->updateQueueStatus('sms', $item['id'], $result['success'] ? 'sent' : 'failed', $result['error'] ?? null);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Queue processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update queue item status
     */
    private function updateQueueStatus($type, $id, $status, $errorMessage = null) {
        try {
            $table = $type === 'email' ? 'email_queue' : 'sms_queue';
            $stmt = $this->db->getConnection()->prepare("
                UPDATE {$table} 
                SET status = ?, sent_at = ?, error_message = ?, attempts = attempts + 1 
                WHERE id = ?
            ");
            return $stmt->execute([
                $status,
                $status === 'sent' ? date('Y-m-d H:i:s') : null,
                $errorMessage,
                $id
            ]);
        } catch (Exception $e) {
            error_log("Queue status update error: " . $e->getMessage());
            return false;
        }
    }
}
