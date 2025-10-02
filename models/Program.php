<?php
/**
 * Program Model
 * Handles program-related operations
 */

class Program {
    private $database;
    
    public function __construct($database = null) {
        if ($database) {
            // Handle both Database object and PDO connection
            if ($database instanceof PDO) {
                $this->database = $database;
            } else {
                $this->database = $database->getConnection();
            }
        } else {
            $this->database = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
    }
    
    /**
     * Get active programs
     */
    public function getActive() {
        try {
            $stmt = $this->database->prepare("
                SELECT * FROM programs 
                WHERE status = 'active' 
                ORDER BY program_name
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get active programs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new program
     */
    public function create($data) {
        $sql = "INSERT INTO programs (
            program_name, program_code, level_name, department, 
            description, requirements, duration, credits, 
            application_fee, is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            $data['program_name'],
            $data['program_code'],
            $data['level_name'],
            $data['department'],
            $data['description'],
            $data['requirements'],
            $data['duration'],
            $data['credits'],
            $data['application_fee'],
            $data['is_active'] ?? 1,
            $data['created_by']
        ]);
    }
    
    /**
     * Get program by ID
     */
    public function getById($id) {
        $sql = "SELECT p.*, u.full_name as created_by_name
                FROM programs p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all programs
     */
    public function getAll($activeOnly = false, $limit = null, $offset = 0) {
        $sql = "SELECT p.*, u.full_name as created_by_name,
                       COUNT(a.id) as application_count
                FROM programs p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN applications a ON p.id = a.program_id
                WHERE 1=1";
        
        if ($activeOnly) {
            $sql .= " AND p.status = 'active'";
        }
        
        $sql .= " GROUP BY p.id ORDER BY p.program_name";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
        }
        
        $stmt = $this->database->prepare($sql);
        if ($limit) {
            $stmt->execute([$limit, $offset]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update program
     */
    public function update($id, $data) {
        $sql = "UPDATE programs SET 
                    program_name = ?, program_code = ?, level_name = ?, 
                    department = ?, description = ?, requirements = ?, 
                    duration = ?, credits = ?, application_fee = ?, 
                    is_active = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            $data['program_name'],
            $data['program_code'],
            $data['level_name'],
            $data['department'],
            $data['description'],
            $data['requirements'],
            $data['duration'],
            $data['credits'],
            $data['application_fee'],
            $data['is_active'] ?? 1,
            $id
        ]);
    }
    
    /**
     * Delete program
     */
    public function delete($id) {
        // Check if program has applications
        $sql = "SELECT COUNT(*) as count FROM applications WHERE program_id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception("Cannot delete program with existing applications");
        }
        
        $sql = "DELETE FROM programs WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Get programs by level
     */
    public function getByLevel($levelName) {
        $sql = "SELECT * FROM programs 
                WHERE degree_level = ? AND status = 'active' 
                ORDER BY program_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$levelName]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get programs by department
     */
    public function getByDepartment($department) {
        $sql = "SELECT * FROM programs 
                WHERE department = ? AND status = 'active' 
                ORDER BY program_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$department]);
        return $stmt->fetchAll();
    }
    
    /**
     * Search programs
     */
    public function search($query, $level = null, $department = null) {
        $sql = "SELECT * FROM programs 
                WHERE status = 'active' 
                AND (program_name LIKE ? OR program_code LIKE ? OR description LIKE ?)";
        
        $params = ["%$query%", "%$query%", "%$query%"];
        
        if ($level) {
            $sql .= " AND degree_level = ?";
            $params[] = $level;
        }
        
        if ($department) {
            $sql .= " AND department = ?";
            $params[] = $department;
        }
        
        $sql .= " ORDER BY program_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get program statistics
     */
    public function getStatistics($programId = null) {
        $sql = "SELECT 
                    p.id,
                    p.program_name,
                    COUNT(a.id) as total_applications,
                    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_applications,
                    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_applications,
                    COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_applications,
                    AVG(p.application_fee) as avg_application_fee
                FROM programs p
                LEFT JOIN applications a ON p.id = a.program_id";
        
        $params = [];
        if ($programId) {
            $sql .= " WHERE p.id = ?";
            $params[] = $programId;
        }
        
        $sql .= " GROUP BY p.id ORDER BY total_applications DESC";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get popular programs
     */
    public function getPopular($limit = 10) {
        $sql = "SELECT p.*, COUNT(a.id) as application_count
                FROM programs p
                LEFT JOIN applications a ON p.id = a.program_id
                WHERE p.is_active = 1
                GROUP BY p.id
                ORDER BY application_count DESC
                LIMIT ?";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get program requirements
     */
    public function getRequirements($programId) {
        $sql = "SELECT ar.* 
                FROM application_requirements ar
                WHERE ar.program_id = ? OR ar.program_id IS NULL
                ORDER BY ar.is_mandatory DESC, ar.requirement_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$programId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if program code exists
     */
    public function codeExists($code, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM programs WHERE program_code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Get program levels
     */
    public function getLevels() {
        $sql = "SELECT DISTINCT degree_level FROM programs WHERE status = 'active' ORDER BY degree_level";
        $stmt = $this->database->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get departments
     */
    public function getDepartments() {
        $sql = "SELECT DISTINCT department FROM programs WHERE status = 'active' AND department IS NOT NULL ORDER BY department";
        $stmt = $this->database->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Toggle program status
     */
    public function toggleStatus($id) {
        $sql = "UPDATE programs SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Get program application deadline
     */
    public function getApplicationDeadline($programId) {
        $sql = "SELECT application_deadline FROM programs WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$programId]);
        $result = $stmt->fetch();
        return $result ? $result['application_deadline'] : null;
    }
    
    /**
     * Check if application deadline has passed
     */
    public function isApplicationDeadlinePassed($programId) {
        $deadline = $this->getApplicationDeadline($programId);
        if (!$deadline) {
            return false; // No deadline set
        }
        
        return strtotime($deadline) < time();
    }
}
?>