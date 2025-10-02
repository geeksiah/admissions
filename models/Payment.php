<?php
/**
 * Payment Model
 * Handles payment transactions and processing
 */

class Payment {
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
     * Create new payment transaction
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate transaction ID
            $transactionId = $this->generateTransactionId();
            
            $stmt = $this->db->prepare("
                INSERT INTO payment_transactions (
                    application_id, transaction_id, amount, currency, payment_method, 
                    payment_gateway, payment_status, processed_by, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['application_id'],
                $transactionId,
                $data['amount'],
                $data['currency'] ?? 'USD',
                $data['payment_method'],
                $data['payment_gateway'] ?? null,
                $data['payment_status'] ?? 'pending',
                $data['processed_by'] ?? null,
                $data['notes'] ?? null
            ]);
            
            if ($result) {
                $paymentId = $this->db->lastInsertId();
                $this->db->commit();
                return $paymentId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Payment creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update payment status
     */
    public function updateStatus($transactionId, $status, $gatewayTransactionId = null, $notes = null) {
        try {
            $data = [
                'payment_status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($gatewayTransactionId) {
                $data['gateway_transaction_id'] = $gatewayTransactionId;
            }
            
            if ($notes) {
                $data['notes'] = $notes;
            }
            
            if ($status === 'completed') {
                $data['payment_date'] = date('Y-m-d H:i:s');
            }
            
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            
            $values[] = $transactionId;
            
            $stmt = $this->db->prepare("
                UPDATE payment_transactions SET " . implode(', ', $fields) . " WHERE transaction_id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Payment status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment by transaction ID
     */
    public function getByTransactionId($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT pt.*, 
                       a.application_number,
                       s.first_name as student_first_name, s.last_name as student_last_name,
                       s.email as student_email,
                       p.program_name, p.program_code
                FROM payment_transactions pt
                JOIN applications a ON pt.application_id = a.id
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                WHERE pt.transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Payment fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payments by application ID
     */
    public function getByApplication($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT pt.*, u.first_name as processed_by_first_name, u.last_name as processed_by_last_name
                FROM payment_transactions pt
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.application_id = ?
                ORDER BY pt.created_at DESC
            ");
            $stmt->execute([$applicationId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Payments fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all payments with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['payment_status'])) {
                $where[] = 'pt.payment_status = ?';
                $params[] = $filters['payment_status'];
            }
            
            if (!empty($filters['payment_method'])) {
                $where[] = 'pt.payment_method = ?';
                $params[] = $filters['payment_method'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = 'pt.payment_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'pt.payment_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(pt.transaction_id LIKE ? OR a.application_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT pt.*, 
                       a.application_number,
                       s.first_name as student_first_name, s.last_name as student_last_name,
                       s.email as student_email,
                       p.program_name, p.program_code,
                       u.first_name as processed_by_first_name, u.last_name as processed_by_last_name
                FROM payment_transactions pt
                JOIN applications a ON pt.application_id = a.id
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE $whereClause 
                ORDER BY pt.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $payments = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM payment_transactions pt
                JOIN applications a ON pt.application_id = a.id
                JOIN students s ON a.student_id = s.id
                WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'payments' => $payments,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Payments fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process refund
     */
    public function processRefund($transactionId, $amount, $reason, $processedBy) {
        try {
            $this->db->beginTransaction();
            
            // Create refund transaction
            $refundData = [
                'application_id' => $this->getApplicationIdByTransactionId($transactionId),
                'amount' => -abs($amount), // Negative amount for refund
                'payment_method' => 'refund',
                'payment_status' => 'refunded',
                'processed_by' => $processedBy,
                'notes' => "Refund: $reason"
            ];
            
            $refundId = $this->create($refundData);
            
            if ($refundId) {
                // Update original transaction status
                $this->updateStatus($transactionId, 'refunded', null, "Refunded: $reason");
                
                $this->db->commit();
                return $refundId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Refund processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                    SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                    SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END) as refunded_payments,
                    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN payment_status = 'refunded' THEN ABS(amount) ELSE 0 END) as total_refunds,
                    AVG(CASE WHEN payment_status = 'completed' THEN amount ELSE NULL END) as average_payment
                FROM payment_transactions
                WHERE amount > 0
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Payment statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId() {
        $prefix = 'PAY' . date('Y');
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM payment_transactions WHERE transaction_id LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();
            
            $sequence = str_pad($result['count'] + 1, 6, '0', STR_PAD_LEFT);
            return $prefix . $sequence;
        } catch (Exception $e) {
            error_log("Transaction ID generation error: " . $e->getMessage());
            return $prefix . '000001';
        }
    }
    
    /**
     * Get application ID by transaction ID
     */
    private function getApplicationIdByTransactionId($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT application_id FROM payment_transactions WHERE transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            $result = $stmt->fetch();
            return $result ? $result['application_id'] : null;
        } catch (Exception $e) {
            error_log("Application ID fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if application has completed payment
     */
    public function hasCompletedPayment($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM payment_transactions 
                WHERE application_id = ? AND payment_status = 'completed' AND amount > 0
            ");
            $stmt->execute([$applicationId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Payment check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get total amount paid for application
     */
    public function getTotalPaid($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(amount) as total FROM payment_transactions 
                WHERE application_id = ? AND payment_status = 'completed'
            ");
            $stmt->execute([$applicationId]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Total paid calculation error: " . $e->getMessage());
            return 0;
        }
    }
}
