<?php
/**
 * Message Model
 * Handles internal communication and messaging
 */

class Message {
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
     * Send internal message
     */
    public function send($data) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO internal_messages (
                    sender_id, recipient_id, subject, message_body, message_type, 
                    priority, related_application_id, related_student_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unread')
            ");
            
            $result = $stmt->execute([
                $data['sender_id'],
                $data['recipient_id'],
                $data['subject'],
                $data['message_body'],
                $data['message_type'] ?? 'general',
                $data['priority'] ?? 'normal',
                $data['related_application_id'] ?? null,
                $data['related_student_id'] ?? null
            ]);
            
            if ($result) {
                $messageId = $this->db->lastInsertId();
                
                // Send notification to recipient
                $this->sendMessageNotification($data['recipient_id'], $messageId);
                
                $this->db->commit();
                return $messageId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Message sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get message by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name, s.email as sender_email,
                       r.first_name as recipient_first_name, r.last_name as recipient_last_name, r.email as recipient_email,
                       a.application_number,
                       st.first_name as student_first_name, st.last_name as student_last_name
                FROM internal_messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                LEFT JOIN applications a ON m.related_application_id = a.id
                LEFT JOIN students st ON m.related_student_id = st.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Message fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get messages for user (inbox)
     */
    public function getInbox($userId, $page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['m.recipient_id = ?'];
            $params = [$userId];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'm.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['message_type'])) {
                $where[] = 'm.message_type = ?';
                $params[] = $filters['message_type'];
            }
            
            if (!empty($filters['priority'])) {
                $where[] = 'm.priority = ?';
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(m.subject LIKE ? OR m.message_body LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name,
                       a.application_number,
                       st.first_name as student_first_name, st.last_name as student_last_name
                FROM internal_messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN applications a ON m.related_application_id = a.id
                LEFT JOIN students st ON m.related_student_id = st.id
                WHERE $whereClause 
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $messages = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM internal_messages m WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'messages' => $messages,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Inbox fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get sent messages for user
     */
    public function getSent($userId, $page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['m.sender_id = ?'];
            $params = [$userId];
            
            // Apply filters
            if (!empty($filters['message_type'])) {
                $where[] = 'm.message_type = ?';
                $params[] = $filters['message_type'];
            }
            
            if (!empty($filters['priority'])) {
                $where[] = 'm.priority = ?';
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(m.subject LIKE ? OR m.message_body LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       r.first_name as recipient_first_name, r.last_name as recipient_last_name,
                       a.application_number,
                       st.first_name as student_first_name, st.last_name as student_last_name
                FROM internal_messages m
                LEFT JOIN users r ON m.recipient_id = r.id
                LEFT JOIN applications a ON m.related_application_id = a.id
                LEFT JOIN students st ON m.related_student_id = st.id
                WHERE $whereClause 
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $messages = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM internal_messages m WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'messages' => $messages,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Sent messages fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead($messageId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE internal_messages 
                SET status = 'read', read_at = NOW() 
                WHERE id = ? AND recipient_id = ?
            ");
            
            return $stmt->execute([$messageId, $userId]);
        } catch (Exception $e) {
            error_log("Message read status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark message as unread
     */
    public function markAsUnread($messageId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE internal_messages 
                SET status = 'unread', read_at = NULL 
                WHERE id = ? AND recipient_id = ?
            ");
            
            return $stmt->execute([$messageId, $userId]);
        } catch (Exception $e) {
            error_log("Message unread status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete message
     */
    public function delete($messageId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE internal_messages 
                SET deleted_at = NOW() 
                WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
            ");
            
            return $stmt->execute([$messageId, $userId, $userId]);
        } catch (Exception $e) {
            error_log("Message deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reply to message
     */
    public function reply($originalMessageId, $data) {
        try {
            // Get original message
            $originalMessage = $this->getById($originalMessageId);
            if (!$originalMessage) {
                return false;
            }
            
            // Create reply
            $replyData = [
                'sender_id' => $data['sender_id'],
                'recipient_id' => $originalMessage['sender_id'],
                'subject' => 'Re: ' . $originalMessage['subject'],
                'message_body' => $data['message_body'],
                'message_type' => $originalMessage['message_type'],
                'priority' => $data['priority'] ?? $originalMessage['priority'],
                'related_application_id' => $originalMessage['related_application_id'],
                'related_student_id' => $originalMessage['related_student_id']
            ];
            
            return $this->send($replyData);
        } catch (Exception $e) {
            error_log("Message reply error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count for user
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM internal_messages 
                WHERE recipient_id = ? AND status = 'unread' AND deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            return $result['count'];
        } catch (Exception $e) {
            error_log("Unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get message statistics
     */
    public function getStatistics($userId = null) {
        try {
            $where = $userId ? "WHERE (sender_id = ? OR recipient_id = ?)" : "";
            $params = $userId ? [$userId, $userId] : [];
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_messages,
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_messages,
                    SUM(CASE WHEN message_type = 'application' THEN 1 ELSE 0 END) as application_messages,
                    SUM(CASE WHEN message_type = 'student' THEN 1 ELSE 0 END) as student_messages
                FROM internal_messages 
                $where
            ");
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Message statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent messages for dashboard
     */
    public function getRecentMessages($userId, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name,
                       r.first_name as recipient_first_name, r.last_name as recipient_last_name
                FROM internal_messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE (m.sender_id = ? OR m.recipient_id = ?) 
                AND m.deleted_at IS NULL
                ORDER BY m.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $userId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Recent messages fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send message notification
     */
    private function sendMessageNotification($recipientId, $messageId) {
        try {
            // Get recipient details
            $userModel = new User($this->db);
            $recipient = $userModel->getById($recipientId);
            
            if ($recipient && $recipient['email']) {
                $notificationModel = new Notification($this->db);
                $notificationModel->sendEmail(
                    $recipient['email'],
                    'New Internal Message',
                    'You have received a new internal message. Please log in to view it.',
                    null,
                    ['recipient_name' => $recipient['first_name'] . ' ' . $recipient['last_name']]
                );
            }
        } catch (Exception $e) {
            error_log("Message notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Get message types
     */
    public function getMessageTypes() {
        return [
            'general' => 'General',
            'application' => 'Application Related',
            'student' => 'Student Related',
            'payment' => 'Payment Related',
            'system' => 'System Notification'
        ];
    }
    
    /**
     * Get priority levels
     */
    public function getPriorityLevels() {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];
    }
    
    /**
     * Clean old messages
     */
    public function cleanOldMessages($days = 90) {
        try {
            $stmt = $this->db->prepare("
                UPDATE internal_messages 
                SET deleted_at = NOW() 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                AND deleted_at IS NULL
            ");
            
            return $stmt->execute([$days]);
        } catch (Exception $e) {
            error_log("Message cleanup error: " . $e->getMessage());
            return false;
        }
    }
}
