<?php
/**
 * Report Model
 * Handles advanced reporting and analytics
 */

class Report {
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
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_applications
                FROM applications
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return [
                'total_applications' => 0,
                'pending_applications' => 0,
                'approved_applications' => 0,
                'rejected_applications' => 0,
                'under_review_applications' => 0
            ];
        }
    }

    /**
     * Get application trends
     */
    public function getApplicationTrends($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as applications_count
                FROM applications
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Application trends error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get application statistics report
     */
    public function getApplicationStatistics($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Apply date filters
            if (!empty($filters['date_from'])) {
                $where[] = 'a.application_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'a.application_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['program_id'])) {
                $where[] = 'a.program_id = ?';
                $params[] = $filters['program_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted_applications,
                    SUM(CASE WHEN a.status = 'under_review' THEN 1 ELSE 0 END) as under_review_applications,
                    SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    SUM(CASE WHEN a.status = 'waitlisted' THEN 1 ELSE 0 END) as waitlisted_applications,
                    AVG(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) * 100 as approval_rate,
                    COUNT(DISTINCT a.student_id) as unique_applicants
                FROM applications a
                WHERE $whereClause
            ");
            
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Application statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get applications by program report
     */
    public function getApplicationsByProgram($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'a.application_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'a.application_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    p.program_name,
                    p.program_code,
                    p.department,
                    p.degree_level,
                    COUNT(a.id) as total_applications,
                    SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    AVG(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) * 100 as approval_rate,
                    p.max_capacity,
                    p.current_enrolled
                FROM programs p
                LEFT JOIN applications a ON p.id = a.program_id AND $whereClause
                GROUP BY p.id, p.program_name, p.program_code, p.department, p.degree_level, p.max_capacity, p.current_enrolled
                ORDER BY total_applications DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Applications by program error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get applications by status report
     */
    public function getApplicationsByStatus($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'application_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'application_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) * 100 as approval_rate
                FROM applications
                WHERE $whereClause
                GROUP BY status
                ORDER BY count DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Applications by status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get monthly application trends
     */
    public function getMonthlyApplicationTrends($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['year'])) {
                $where[] = 'YEAR(application_date) = ?';
                $params[] = $filters['year'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    YEAR(application_date) as year,
                    MONTH(application_date) as month,
                    MONTHNAME(application_date) as month_name,
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
                FROM applications
                WHERE $whereClause
                GROUP BY YEAR(application_date), MONTH(application_date), MONTHNAME(application_date)
                ORDER BY year DESC, month DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Monthly trends error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student demographics report
     */
    public function getStudentDemographics($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 's.created_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 's.created_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Gender distribution
            $genderStmt = $this->db->prepare("
                SELECT 
                    gender,
                    COUNT(*) as count,
                    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM students WHERE $whereClause) as percentage
                FROM students s
                WHERE $whereClause
                GROUP BY gender
                ORDER BY count DESC
            ");
            $genderStmt->execute($params);
            $genderData = $genderStmt->fetchAll();
            
            // Nationality distribution
            $nationalityStmt = $this->db->prepare("
                SELECT 
                    nationality,
                    COUNT(*) as count,
                    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM students WHERE $whereClause) as percentage
                FROM students s
                WHERE $whereClause
                GROUP BY nationality
                ORDER BY count DESC
                LIMIT 10
            ");
            $nationalityStmt->execute($params);
            $nationalityData = $nationalityStmt->fetchAll();
            
            // Age distribution
            $ageStmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36-45'
                        ELSE 'Over 45'
                    END as age_group,
                    COUNT(*) as count,
                    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM students WHERE $whereClause) as percentage
                FROM students s
                WHERE $whereClause
                GROUP BY age_group
                ORDER BY 
                    CASE age_group
                        WHEN 'Under 18' THEN 1
                        WHEN '18-25' THEN 2
                        WHEN '26-35' THEN 3
                        WHEN '36-45' THEN 4
                        WHEN 'Over 45' THEN 5
                    END
            ");
            $ageStmt->execute($params);
            $ageData = $ageStmt->fetchAll();
            
            return [
                'gender' => $genderData,
                'nationality' => $nationalityData,
                'age' => $ageData
            ];
        } catch (Exception $e) {
            error_log("Student demographics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment statistics report
     */
    public function getPaymentStatistics($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'pt.created_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'pt.created_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN pt.payment_status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                    SUM(CASE WHEN pt.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN pt.payment_status = 'failed' THEN 1 ELSE 0 END) as failed_transactions,
                    SUM(CASE WHEN pt.payment_status = 'completed' THEN pt.amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN pt.payment_status = 'completed' THEN pt.amount ELSE NULL END) as average_payment,
                    SUM(CASE WHEN pt.payment_method = 'credit_card' THEN 1 ELSE 0 END) as credit_card_payments,
                    SUM(CASE WHEN pt.payment_method = 'bank_transfer' THEN 1 ELSE 0 END) as bank_transfer_payments,
                    SUM(CASE WHEN pt.payment_method = 'cash' THEN 1 ELSE 0 END) as cash_payments
                FROM payment_transactions pt
                WHERE $whereClause
            ");
            
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Payment statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get voucher usage report
     */
    public function getVoucherUsageReport($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'vu.used_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'vu.used_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    v.voucher_code,
                    v.voucher_type,
                    v.discount_value,
                    COUNT(vu.id) as usage_count,
                    SUM(vu.discount_amount) as total_discount_given,
                    AVG(vu.discount_amount) as average_discount
                FROM vouchers v
                LEFT JOIN voucher_usage vu ON v.id = vu.voucher_id AND $whereClause
                GROUP BY v.id, v.voucher_code, v.voucher_type, v.discount_value
                HAVING usage_count > 0
                ORDER BY usage_count DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Voucher usage report error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reviewer performance report
     */
    public function getReviewerPerformance($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'ar.created_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'ar.created_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    u.first_name,
                    u.last_name,
                    u.email,
                    COUNT(ar.id) as total_reviews,
                    AVG(TIMESTAMPDIFF(HOUR, a.application_date, ar.created_at)) as average_review_time_hours,
                    SUM(CASE WHEN ar.recommendation = 'approve' THEN 1 ELSE 0 END) as approved_reviews,
                    SUM(CASE WHEN ar.recommendation = 'reject' THEN 1 ELSE 0 END) as rejected_reviews,
                    AVG(ar.score) as average_score
                FROM users u
                LEFT JOIN application_reviews ar ON u.id = ar.reviewer_id AND $whereClause
                LEFT JOIN applications a ON ar.application_id = a.id
                WHERE u.role = 'reviewer'
                GROUP BY u.id, u.first_name, u.last_name, u.email
                HAVING total_reviews > 0
                ORDER BY total_reviews DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Reviewer performance error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get system activity report
     */
    public function getSystemActivity($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM audit_log
                WHERE $whereClause
                GROUP BY action
                ORDER BY count DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("System activity error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export report data to CSV
     */
    public function exportToCSV($data, $filename, $headers = []) {
        try {
            $output = fopen('php://output', 'w');
            
            // Set headers for download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            // Write headers if provided
            if (!empty($headers)) {
                fputcsv($output, $headers);
            } elseif (!empty($data)) {
                // Use first row keys as headers
                fputcsv($output, array_keys($data[0]));
            }
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            return true;
        } catch (Exception $e) {
            error_log("CSV export error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get dashboard summary statistics
     */
    public function getDashboardSummary() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM applications WHERE status = 'submitted') as pending_applications,
                    (SELECT COUNT(*) FROM applications WHERE status = 'under_review') as under_review_applications,
                    (SELECT COUNT(*) FROM applications WHERE status = 'approved') as approved_applications,
                    (SELECT COUNT(*) FROM students) as total_students,
                    (SELECT COUNT(*) FROM programs WHERE status = 'active') as active_programs,
                    (SELECT COUNT(*) FROM payment_transactions WHERE payment_status = 'completed' AND DATE(created_at) = CURDATE()) as today_payments,
                    (SELECT SUM(amount) FROM payment_transactions WHERE payment_status = 'completed' AND DATE(created_at) = CURDATE()) as today_revenue,
                    (SELECT COUNT(*) FROM internal_messages WHERE recipient_id = ? AND status = 'unread') as unread_messages
            ");
            $stmt->execute([$_SESSION['user_id'] ?? 0]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Dashboard summary error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available report types
     */
    public function getReportTypes() {
        return [
            'application_statistics' => 'Application Statistics',
            'applications_by_program' => 'Applications by Program',
            'applications_by_status' => 'Applications by Status',
            'monthly_trends' => 'Monthly Application Trends',
            'student_demographics' => 'Student Demographics',
            'payment_statistics' => 'Payment Statistics',
            'voucher_usage' => 'Voucher Usage Report',
            'reviewer_performance' => 'Reviewer Performance',
            'system_activity' => 'System Activity'
        ];
    }
}
