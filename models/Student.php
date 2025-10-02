<?php
/**
 * Student Model
 * Handles student-related database operations
 */

class Student {
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
     * Create new student
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate student ID
            $studentId = $this->generateStudentId();
            
            $stmt = $this->db->prepare("
                INSERT INTO students (
                    user_id, student_id, first_name, last_name, middle_name, date_of_birth, gender, 
                    nationality, passport_number, phone, email, address, city, state, postal_code, 
                    country, emergency_contact_name, emergency_contact_phone, emergency_contact_relation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['user_id'] ?? null,
                $studentId,
                $data['first_name'],
                $data['last_name'],
                $data['middle_name'] ?? null,
                $data['date_of_birth'],
                $data['gender'],
                $data['nationality'],
                $data['passport_number'] ?? null,
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['postal_code'],
                $data['country'],
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $data['emergency_contact_relation'] ?? null
            ]);
            
            if ($result) {
                $studentDbId = $this->db->lastInsertId();
                $this->db->commit();
                return $studentDbId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Student creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.role, u.status as user_status
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Student fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student by student ID
     */
    public function getByStudentId($studentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.role, u.status as user_status
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.student_id = ?
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Student fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student by email
     */
    public function getByEmail($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.role, u.status as user_status
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.email = ?
            ");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Student fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update student
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
                UPDATE students SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Student update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete student
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM students WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Student delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all students with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['nationality'])) {
                $where[] = 's.nationality = ?';
                $params[] = $filters['nationality'];
            }
            
            if (!empty($filters['gender'])) {
                $where[] = 's.gender = ?';
                $params[] = $filters['gender'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.role, u.status as user_status
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE $whereClause 
                ORDER BY s.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $students = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM students s WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'students' => $students,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Students fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM students WHERE email = ?";
            $params = [$email];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student applications
     */
    public function getApplications($studentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, p.program_name, p.program_code, p.degree_level,
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
     * Get student academic records
     */
    public function getAcademicRecords($studentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM academic_records WHERE student_id = ? ORDER BY graduation_date DESC
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Academic records fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student test scores
     */
    public function getTestScores($studentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM test_scores WHERE student_id = ? ORDER BY test_date DESC
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Test scores fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique student ID
     */
    private function generateStudentId() {
        $year = date('Y');
        $prefix = 'STU' . $year;
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM students WHERE student_id LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();
            
            $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
            return $prefix . $sequence;
        } catch (Exception $e) {
            error_log("Student ID generation error: " . $e->getMessage());
            return $prefix . '0001';
        }
    }
    
    /**
     * Get student statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_students,
                    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_students,
                    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_students,
                    COUNT(DISTINCT nationality) as unique_nationalities
                FROM students
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Student statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get students by nationality
     */
    public function getByNationality($nationality) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.role, u.status as user_status
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.nationality = ?
                ORDER BY s.first_name, s.last_name
            ");
            $stmt->execute([$nationality]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Students by nationality fetch error: " . $e->getMessage());
            return false;
        }
    }
}
