<?php
/**
 * Receipt Manager
 * Handles receipt generation, management, and tracking for payments and transactions
 */
class ReceiptManager {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Generate receipt for payment
     */
    public function generateReceipt($paymentId, $receiptType = 'payment') {
        try {
            // Get payment details
            $stmt = $this->database->prepare("
                SELECT pt.*, a.application_id, s.first_name, s.last_name, s.email, s.phone,
                       p.program_name, pg.gateway_name, pg.gateway_type
                FROM payment_transactions pt
                JOIN applications a ON pt.application_id = a.id
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN payment_gateways pg ON pt.gateway_id = pg.id
                WHERE pt.id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            // Generate receipt number
            $receiptNumber = $this->generateReceiptNumber($receiptType);
            
            // Create receipt record
            $stmt = $this->database->prepare("
                INSERT INTO receipts (receipt_number, payment_id, receipt_type, amount, currency, 
                                    student_name, student_email, program_name, gateway_name, 
                                    generated_at, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $receiptNumber,
                $paymentId,
                $receiptType,
                $payment['amount'],
                $payment['currency'],
                $payment['first_name'] . ' ' . $payment['last_name'],
                $payment['email'],
                $payment['program_name'],
                $payment['gateway_name'],
                $_SESSION['user_id'] ?? null
            ]);
            
            $receiptId = $this->database->lastInsertId();
            
            // Generate PDF receipt
            $pdfPath = $this->generatePDFReceipt($receiptId, $payment);
            
            // Update receipt with PDF path
            $stmt = $this->database->prepare("
                UPDATE receipts SET pdf_path = ? WHERE id = ?
            ");
            $stmt->execute([$pdfPath, $receiptId]);
            
            return [
                'success' => true,
                'receipt_id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'pdf_path' => $pdfPath
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate receipt number
     */
    private function generateReceiptNumber($type) {
        $prefix = strtoupper(substr($type, 0, 3));
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number for this month
        $stmt = $this->database->prepare("
            SELECT COUNT(*) + 1 as next_number
            FROM receipts
            WHERE receipt_number LIKE ? AND YEAR(generated_at) = ? AND MONTH(generated_at) = ?
        ");
        $pattern = $prefix . $year . $month . '%';
        $stmt->execute([$pattern, $year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sequence = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        
        return $prefix . $year . $month . $sequence;
    }
    
    /**
     * Generate PDF receipt
     */
    private function generatePDFReceipt($receiptId, $paymentData) {
        require_once __DIR__ . '/../vendor/autoload.php'; // Assuming FPDF is installed via Composer
        
        // Create PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Header
        $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Receipt details
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 8, 'Receipt Number:', 0, 0);
        $pdf->Cell(0, 8, $this->generateReceiptNumber('payment'), 0, 1);
        
        $pdf->Cell(50, 8, 'Date:', 0, 0);
        $pdf->Cell(0, 8, date('Y-m-d H:i:s'), 0, 1);
        
        $pdf->Cell(50, 8, 'Student Name:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['first_name'] . ' ' . $paymentData['last_name'], 0, 1);
        
        $pdf->Cell(50, 8, 'Email:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['email'], 0, 1);
        
        $pdf->Cell(50, 8, 'Program:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['program_name'], 0, 1);
        
        $pdf->Cell(50, 8, 'Application ID:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['application_id'], 0, 1);
        
        $pdf->Ln(10);
        
        // Payment details
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'Payment Details', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        
        $pdf->Cell(50, 8, 'Amount:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['currency'] . ' ' . number_format($paymentData['amount'], 2), 0, 1);
        
        $pdf->Cell(50, 8, 'Payment Method:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['gateway_name'], 0, 1);
        
        $pdf->Cell(50, 8, 'Transaction ID:', 0, 0);
        $pdf->Cell(0, 8, $paymentData['transaction_id'], 0, 1);
        
        $pdf->Cell(50, 8, 'Status:', 0, 0);
        $pdf->Cell(0, 8, ucfirst($paymentData['status']), 0, 1);
        
        // Footer
        $pdf->Ln(20);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'This is a computer-generated receipt. No signature required.', 0, 1, 'C');
        
        // Save PDF
        $filename = 'receipt_' . $receiptId . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = __DIR__ . '/../receipts/' . $filename;
        
        // Create receipts directory if it doesn't exist
        $receiptDir = dirname($filepath);
        if (!is_dir($receiptDir)) {
            mkdir($receiptDir, 0755, true);
        }
        
        $pdf->Output('F', $filepath);
        
        return $filepath;
    }
    
    /**
     * Get receipt by ID
     */
    public function getReceipt($receiptId) {
        try {
            $stmt = $this->database->prepare("
                SELECT r.*, pt.transaction_id, pt.status as payment_status, pt.paid_at
                FROM receipts r
                LEFT JOIN payment_transactions pt ON r.payment_id = pt.id
                WHERE r.id = ?
            ");
            $stmt->execute([$receiptId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get receipt by receipt number
     */
    public function getReceiptByNumber($receiptNumber) {
        try {
            $stmt = $this->database->prepare("
                SELECT r.*, pt.transaction_id, pt.status as payment_status, pt.paid_at
                FROM receipts r
                LEFT JOIN payment_transactions pt ON r.payment_id = pt.id
                WHERE r.receipt_number = ?
            ");
            $stmt->execute([$receiptNumber]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * List receipts with filters
     */
    public function listReceipts($filters = [], $page = 1, $limit = 20) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['receipt_number'])) {
                $whereConditions[] = "r.receipt_number LIKE ?";
                $params[] = '%' . $filters['receipt_number'] . '%';
            }
            
            if (!empty($filters['student_name'])) {
                $whereConditions[] = "r.student_name LIKE ?";
                $params[] = '%' . $filters['student_name'] . '%';
            }
            
            if (!empty($filters['receipt_type'])) {
                $whereConditions[] = "r.receipt_type = ?";
                $params[] = $filters['receipt_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(r.generated_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(r.generated_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countStmt = $this->database->prepare("
                SELECT COUNT(*) FROM receipts r $whereClause
            ");
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);
            
            // Get receipts
            $offset = ($page - 1) * $limit;
            $stmt = $this->database->prepare("
                SELECT r.*, pt.transaction_id, pt.status as payment_status, pt.paid_at
                FROM receipts r
                LEFT JOIN payment_transactions pt ON r.payment_id = pt.id
                $whereClause
                ORDER BY r.generated_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'receipts' => $receipts,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $page
            ];
            
        } catch (Exception $e) {
            return [
                'receipts' => [],
                'total_records' => 0,
                'total_pages' => 0,
                'current_page' => $page
            ];
        }
    }
    
    /**
     * Download receipt PDF
     */
    public function downloadReceipt($receiptId) {
        try {
            $receipt = $this->getReceipt($receiptId);
            
            if (!$receipt || !file_exists($receipt['pdf_path'])) {
                throw new Exception('Receipt not found');
            }
            
            // Set headers for download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($receipt['pdf_path']) . '"');
            header('Content-Length: ' . filesize($receipt['pdf_path']));
            
            readfile($receipt['pdf_path']);
            exit;
            
        } catch (Exception $e) {
            http_response_code(404);
            echo 'Receipt not found';
            exit;
        }
    }
    
    /**
     * View receipt PDF
     */
    public function viewReceipt($receiptId) {
        try {
            $receipt = $this->getReceipt($receiptId);
            
            if (!$receipt || !file_exists($receipt['pdf_path'])) {
                throw new Exception('Receipt not found');
            }
            
            // Set headers for viewing
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($receipt['pdf_path']) . '"');
            header('Content-Length: ' . filesize($receipt['pdf_path']));
            
            readfile($receipt['pdf_path']);
            exit;
            
        } catch (Exception $e) {
            http_response_code(404);
            echo 'Receipt not found';
            exit;
        }
    }
    
    /**
     * Get receipt statistics
     */
    public function getReceiptStatistics($days = 30) {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    COUNT(*) as total_receipts,
                    SUM(amount) as total_amount,
                    COUNT(DISTINCT student_email) as unique_students,
                    receipt_type,
                    COUNT(*) as type_count
                FROM receipts
                WHERE generated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY receipt_type
            ");
            $stmt->execute([$days]);
            $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $statistics;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Resend receipt email
     */
    public function resendReceiptEmail($receiptId) {
        try {
            $receipt = $this->getReceipt($receiptId);
            
            if (!$receipt) {
                throw new Exception('Receipt not found');
            }
            
            // Send email with receipt attachment
            $emailData = [
                'to' => $receipt['student_email'],
                'subject' => 'Payment Receipt - ' . $receipt['receipt_number'],
                'template' => 'receipt_email',
                'data' => [
                    'student_name' => $receipt['student_name'],
                    'receipt_number' => $receipt['receipt_number'],
                    'amount' => $receipt['amount'],
                    'currency' => $receipt['currency'],
                    'program_name' => $receipt['program_name']
                ],
                'attachments' => [
                    [
                        'path' => $receipt['pdf_path'],
                        'name' => 'receipt_' . $receipt['receipt_number'] . '.pdf'
                    ]
                ]
            ];
            
            $notificationManager = new NotificationManager($this->database);
            $result = $notificationManager->sendEmail($emailData);
            
            if ($result) {
                // Log the resend
                $stmt = $this->database->prepare("
                    INSERT INTO receipt_actions (receipt_id, action_type, action_data, created_at)
                    VALUES (?, 'email_resent', ?, NOW())
                ");
                $stmt->execute([$receiptId, json_encode(['email' => $receipt['student_email']])]);
                
                return [
                    'success' => true,
                    'message' => 'Receipt email sent successfully'
                ];
            } else {
                throw new Exception('Failed to send email');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
