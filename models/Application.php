<?php
/**
 * Application Model
 * Handles application-related database operations
 */

class Application {
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
     * Get recent applications
     */
    public function getRecent($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, s.first_name, s.last_name, s.email, p.program_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get recent applications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending documents
     */
    public function getPendingDocuments($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT ad.*, ar.requirement_name, s.first_name, s.last_name
                FROM application_documents ad
                JOIN application_requirements ar ON ad.requirement_id = ar.id
                JOIN applications a ON ad.application_id = a.id
                JOIN students s ON a.student_id = s.id
                WHERE ad.status = 'pending'
                ORDER BY ad.uploaded_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get pending documents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create new application
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO applications (
                    student_id, program_id, application_number, status, priority, notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['student_id'],
                $data['program_id'],
                $applicationNumber,
                $data['status'] ?? 'submitted',
                $data['priority'] ?? 'medium',
                $data['notes'] ?? null
            ]);
            
            if ($result) {
                $applicationId = $this->db->lastInsertId();
                $this->db->commit();
                return $applicationId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Application creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get application by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       s.first_name as student_first_name, s.last_name as student_last_name, 
                       s.email as student_email, s.student_id as student_number,
                       p.program_name, p.program_code, p.degree_level, p.department,
                       u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN users u ON a.reviewer_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Application fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get application by application number
     */
    public function getByApplicationNumber($applicationNumber) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       s.first_name as student_first_name, s.last_name as student_last_name, 
                       s.email as student_email, s.student_id as student_number,
                       p.program_name, p.program_code, p.degree_level, p.department,
                       u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN users u ON a.reviewer_id = u.id
                WHERE a.application_number = ?
            ");
            $stmt->execute([$applicationNumber]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Application fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update application
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            
            $values[] = $id;
            
            $stmt = $this->db->prepare("
                UPDATE applications SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Application update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update application status
     */
    public function updateStatus($id, $status, $reviewerId = null, $decisionNotes = null) {
        try {
            $data = [
                'status' => $status,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'decision_date' => date('Y-m-d H:i:s')
            ];
            
            if ($reviewerId) {
                $data['reviewer_id'] = $reviewerId;
            }
            
            if ($decisionNotes) {
                $data['decision_notes'] = $decisionNotes;
            }
            
            return $this->update($id, $data);
        } catch (Exception $e) {
            error_log("Application status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all applications with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'a.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['program_id'])) {
                $where[] = 'a.program_id = ?';
                $params[] = $filters['program_id'];
            }
            
            if (!empty($filters['priority'])) {
                $where[] = 'a.priority = ?';
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['reviewer_id'])) {
                $where[] = 'a.reviewer_id = ?';
                $params[] = $filters['reviewer_id'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR a.application_number LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = 'a.application_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'a.application_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       s.first_name as student_first_name, s.last_name as student_last_name, 
                       s.email as student_email, s.student_id as student_number,
                       p.program_name, p.program_code, p.degree_level, p.department,
                       u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN users u ON a.reviewer_id = u.id
                WHERE $whereClause 
                ORDER BY a.application_date DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $applications = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM applications a
                JOIN students s ON a.student_id = s.id
                WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'applications' => $applications,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Applications fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get applications by student
     */
    public function getByStudent($studentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       p.program_name, p.program_code, p.degree_level, p.department,
                       u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM applications a
                JOIN programs p ON a.program_id = p.id
                LEFT JOIN users u ON a.reviewer_id = u.id
                WHERE a.student_id = ?
                ORDER BY a.application_date DESC
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Student applications fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get applications by program
     */
    public function getByProgram($programId) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       s.first_name as student_first_name, s.last_name as student_last_name, 
                       s.email as student_email, s.student_id as student_number,
                       u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                LEFT JOIN users u ON a.reviewer_id = u.id
                WHERE a.program_id = ?
                ORDER BY a.application_date DESC
            ");
            $stmt->execute([$programId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Program applications fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get application documents
     */
    public function getDocuments($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT ad.*, u.first_name as verified_by_first_name, u.last_name as verified_by_last_name
                FROM application_documents ad
                LEFT JOIN users u ON ad.verified_by = u.id
                WHERE ad.application_id = ?
                ORDER BY ad.uploaded_at DESC
            ");
            $stmt->execute([$applicationId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Application documents fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add document to application
     */
    public function addDocument($applicationId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO application_documents (
                    application_id, document_type, document_name, file_path, file_size, mime_type
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $applicationId,
                $data['document_type'],
                $data['document_name'],
                $data['file_path'],
                $data['file_size'],
                $data['mime_type']
            ]);
        } catch (Exception $e) {
            error_log("Document addition error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get application reviews
     */
    public function getReviews($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT ar.*, u.first_name as reviewer_first_name, u.last_name as reviewer_last_name
                FROM application_reviews ar
                JOIN users u ON ar.reviewer_id = u.id
                WHERE ar.application_id = ?
                ORDER BY ar.reviewed_at DESC
            ");
            $stmt->execute([$applicationId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Application reviews fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add review to application
     */
    public function addReview($applicationId, $reviewerId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO application_reviews (
                    application_id, reviewer_id, review_type, score, comments, recommendation
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $applicationId,
                $reviewerId,
                $data['review_type'],
                $data['score'],
                $data['comments'],
                $data['recommendation']
            ]);
        } catch (Exception $e) {
            error_log("Review addition error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique application number
     */
    private function generateApplicationNumber() {
        $year = date('Y');
        $prefix = 'APP' . $year;
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM applications WHERE application_number LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();
            
            $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
            return $prefix . $sequence;
        } catch (Exception $e) {
            error_log("Application number generation error: " . $e->getMessage());
            return $prefix . '0001';
        }
    }
    
    /**
     * Get application statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_applications,
                    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    SUM(CASE WHEN status = 'waitlisted' THEN 1 ELSE 0 END) as waitlisted_applications
                FROM applications
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Application statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get applications by status
     */
    public function getByStatus($status) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       s.first_name as student_first_name, s.last_name as student_last_name, 
                       s.email as student_email, s.student_id as student_number,
                       p.program_name, p.program_code, p.degree_level, p.department
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                WHERE a.status = ?
                ORDER BY a.application_date DESC
            ");
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Applications by status fetch error: " . $e->getMessage());
            return false;
        }
    }
}
