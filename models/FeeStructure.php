<?php
/**
 * Fee Structure Model
 * Manages fee structures for programs and applications
 */

class FeeStructure {
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
     * Create new fee structure
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO fee_structures (
                    fee_name, fee_type, amount, currency, is_percentage, percentage_of,
                    program_id, is_mandatory, due_date, late_fee_amount, late_fee_grace_days,
                    description, is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['fee_name'],
                $data['fee_type'],
                $data['amount'],
                $data['currency'] ?? 'USD',
                $data['is_percentage'] ? 1 : 0,
                $data['percentage_of'] ?? null,
                $data['program_id'] ?? null,
                $data['is_mandatory'] ? 1 : 0,
                $data['due_date'] ?? null,
                $data['late_fee_amount'] ?? 0.00,
                $data['late_fee_grace_days'] ?? 0,
                $data['description'] ?? null,
                $data['is_active'] ? 1 : 0,
                $data['created_by']
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("FeeStructure creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get fee structure by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT fs.*, p.program_name, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM fee_structures fs
                LEFT JOIN programs p ON fs.program_id = p.id
                LEFT JOIN users u ON fs.created_by = u.id
                WHERE fs.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("FeeStructure getById error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all fee structures
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['fee_type'])) {
                $where[] = 'fs.fee_type = ?';
                $params[] = $filters['fee_type'];
            }
            
            if (!empty($filters['program_id'])) {
                $where[] = 'fs.program_id = ?';
                $params[] = $filters['program_id'];
            }
            
            if (!empty($filters['is_active'])) {
                $where[] = 'fs.is_active = ?';
                $params[] = $filters['is_active'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(fs.fee_name LIKE ? OR fs.description LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM fee_structures fs
                WHERE $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get paginated results
            $stmt = $this->db->prepare("
                SELECT fs.*, p.program_name, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM fee_structures fs
                LEFT JOIN programs p ON fs.program_id = p.id
                LEFT JOIN users u ON fs.created_by = u.id
                WHERE $whereClause
                ORDER BY fs.fee_type, fs.fee_name
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $fees = $stmt->fetchAll();
            
            return [
                'fees' => $fees,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'current_page' => $page
            ];
        } catch (Exception $e) {
            error_log("FeeStructure getAll error: " . $e->getMessage());
            return [
                'fees' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => $page
            ];
        }
    }
    
    /**
     * Get fees by program
     */
    public function getByProgram($programId, $activeOnly = true) {
        try {
            $where = 'fs.program_id = ?';
            $params = [$programId];
            
            if ($activeOnly) {
                $where .= ' AND fs.is_active = 1';
            }
            
            $stmt = $this->db->prepare("
                SELECT fs.*, p.program_name
                FROM fee_structures fs
                LEFT JOIN programs p ON fs.program_id = p.id
                WHERE $where
                ORDER BY fs.fee_type, fs.fee_name
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("FeeStructure getByProgram error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get fees by type
     */
    public function getByType($feeType, $activeOnly = true) {
        try {
            $where = 'fs.fee_type = ?';
            $params = [$feeType];
            
            if ($activeOnly) {
                $where .= ' AND fs.is_active = 1';
            }
            
            $stmt = $this->db->prepare("
                SELECT fs.*, p.program_name
                FROM fee_structures fs
                LEFT JOIN programs p ON fs.program_id = p.id
                WHERE $where
                ORDER BY fs.fee_name
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("FeeStructure getByType error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update fee structure
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'fee_name', 'fee_type', 'amount', 'currency', 'is_percentage', 'percentage_of',
                'program_id', 'is_mandatory', 'due_date', 'late_fee_amount', 'late_fee_grace_days',
                'description', 'is_active'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    if ($field === 'is_percentage' || $field === 'is_mandatory' || $field === 'is_active') {
                        $params[] = $data[$field] ? 1 : 0;
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $params[] = $id;
            
            $stmt = $this->db->prepare("
                UPDATE fee_structures 
                SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("FeeStructure update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete fee structure
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM fee_structures WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("FeeStructure delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate total fees for a program
     */
    public function calculateTotalFees($programId, $excludeTypes = []) {
        try {
            $where = 'fs.program_id = ? AND fs.is_active = 1';
            $params = [$programId];
            
            if (!empty($excludeTypes)) {
                $placeholders = str_repeat('?,', count($excludeTypes) - 1) . '?';
                $where .= " AND fs.fee_type NOT IN ($placeholders)";
                $params = array_merge($params, $excludeTypes);
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN fs.is_percentage = 0 THEN fs.amount ELSE 0 END) as fixed_total,
                    SUM(CASE WHEN fs.is_percentage = 1 THEN fs.amount ELSE 0 END) as percentage_total,
                    fs.percentage_of
                FROM fee_structures fs
                WHERE $where
            ");
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            $total = $result['fixed_total'] ?? 0;
            
            // Add percentage-based fees
            if ($result['percentage_total'] > 0 && $result['percentage_of']) {
                $percentageAmount = ($result['percentage_of'] * $result['percentage_total']) / 100;
                $total += $percentageAmount;
            }
            
            return $total;
        } catch (Exception $e) {
            error_log("FeeStructure calculateTotalFees error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get fee types
     */
    public function getFeeTypes() {
        return [
            'application' => 'Application Fee',
            'acceptance' => 'Acceptance Fee',
            'tuition' => 'Tuition Fee',
            'late' => 'Late Fee',
            'processing' => 'Processing Fee',
            'other' => 'Other Fee'
        ];
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_fees,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_fees,
                    COUNT(CASE WHEN program_id IS NULL THEN 1 END) as global_fees,
                    SUM(amount) as total_amount
                FROM fee_structures
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("FeeStructure getStatistics error: " . $e->getMessage());
            return [
                'total_fees' => 0,
                'active_fees' => 0,
                'global_fees' => 0,
                'total_amount' => 0
            ];
        }
    }
    
    /**
     * Check if fee exists for program
     */
    public function feeExists($programId, $feeType, $excludeId = null) {
        try {
            $where = 'program_id = ? AND fee_type = ?';
            $params = [$programId, $feeType];
            
            if ($excludeId) {
                $where .= ' AND id != ?';
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM fee_structures WHERE $where
            ");
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("FeeStructure feeExists error: " . $e->getMessage());
            return false;
        }
    }
}
