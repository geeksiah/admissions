<?php
/**
 * Voucher Model
 * Handles voucher and waiver code management
 */

class Voucher {
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
     * Create new voucher
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate voucher code if not provided
            if (empty($data['voucher_code'])) {
                $data['voucher_code'] = $this->generateVoucherCode();
            }
            
            // Generate PIN and SERIAL if not provided
            if (empty($data['pin'])) {
                $data['pin'] = $this->generatePIN();
            }
            if (empty($data['serial'])) {
                $data['serial'] = $this->generateSerial();
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO vouchers (
                    voucher_code, pin, serial, voucher_type, discount_value, max_uses, valid_from, 
                    valid_until, applicable_programs, applicable_users, description, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['voucher_code'],
                $data['pin'],
                $data['serial'],
                $data['voucher_type'],
                $data['discount_value'],
                $data['max_uses'] ?? 1,
                $data['valid_from'],
                $data['valid_until'],
                json_encode($data['applicable_programs'] ?? []),
                json_encode($data['applicable_users'] ?? []),
                $data['description'] ?? null,
                $data['created_by'],
                $data['status'] ?? 'active'
            ]);
            
            if ($result) {
                $voucherId = $this->db->lastInsertId();
                $this->db->commit();
                return $voucherId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Voucher creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate and apply voucher
     */
    public function validateAndApply($voucherCode, $applicationId, $applicationFee, $programId = null, $userId = null) {
        try {
            $this->db->beginTransaction();
            
            // Get voucher details
            $voucher = $this->getByCode($voucherCode);
            if (!$voucher) {
                return ['success' => false, 'error' => 'Invalid voucher code'];
            }
            
            // Check if voucher is active
            if ($voucher['status'] !== 'active') {
                return ['success' => false, 'error' => 'Voucher is not active'];
            }
            
            // Check validity dates
            $now = date('Y-m-d');
            if ($voucher['valid_from'] > $now || $voucher['valid_until'] < $now) {
                return ['success' => false, 'error' => 'Voucher has expired or is not yet valid'];
            }
            
            // Check usage limit
            if ($voucher['used_count'] >= $voucher['max_uses']) {
                return ['success' => false, 'error' => 'Voucher usage limit exceeded'];
            }
            
            // Check if voucher applies to this program
            $applicablePrograms = json_decode($voucher['applicable_programs'], true) ?? [];
            if (!empty($applicablePrograms) && !in_array($programId, $applicablePrograms)) {
                return ['success' => false, 'error' => 'Voucher is not applicable to this program'];
            }
            
            // Check if voucher applies to this user
            $applicableUsers = json_decode($voucher['applicable_users'], true) ?? [];
            if (!empty($applicableUsers) && !in_array($userId, $applicableUsers)) {
                return ['success' => false, 'error' => 'Voucher is not applicable to this user'];
            }
            
            // Calculate discount amount
            $discountAmount = $this->calculateDiscount($voucher['voucher_type'], $voucher['discount_value'], $applicationFee);
            
            if ($discountAmount <= 0) {
                return ['success' => false, 'error' => 'No discount applicable'];
            }
            
            // Record voucher usage
            $usageStmt = $this->db->prepare("
                INSERT INTO voucher_usage (voucher_id, application_id, used_by, discount_amount) 
                VALUES (?, ?, ?, ?)
            ");
            
            $usageResult = $usageStmt->execute([
                $voucher['id'],
                $applicationId,
                $userId,
                $discountAmount
            ]);
            
            if ($usageResult) {
                // Update voucher usage count
                $updateStmt = $this->db->prepare("
                    UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?
                ");
                $updateStmt->execute([$voucher['id']]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $applicationFee - $discountAmount,
                    'voucher_id' => $voucher['id']
                ];
            } else {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Failed to apply voucher'];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Voucher validation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred while processing the voucher'];
        }
    }
    
    /**
     * Get voucher by code
     */
    public function getByCode($voucherCode) {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM vouchers v
                LEFT JOIN users u ON v.created_by = u.id
                WHERE v.voucher_code = ?
            ");
            $stmt->execute([$voucherCode]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Voucher fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get voucher by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM vouchers v
                LEFT JOIN users u ON v.created_by = u.id
                WHERE v.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Voucher fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all vouchers with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'v.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['voucher_type'])) {
                $where[] = 'v.voucher_type = ?';
                $params[] = $filters['voucher_type'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(v.voucher_code LIKE ? OR v.description LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT v.*, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM vouchers v
                LEFT JOIN users u ON v.created_by = u.id
                WHERE $whereClause 
                ORDER BY v.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $vouchers = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM vouchers v WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'vouchers' => $vouchers,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Vouchers fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update voucher
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['applicable_programs', 'applicable_users'])) {
                    $fields[] = "$key = ?";
                    $values[] = json_encode($value);
                } else {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $id;
            
            $stmt = $this->db->prepare("
                UPDATE vouchers SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Voucher update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete voucher
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM vouchers WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Voucher deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get voucher usage history
     */
    public function getUsageHistory($voucherId) {
        try {
            $stmt = $this->db->prepare("
                SELECT vu.*, 
                       a.application_number,
                       s.first_name as student_first_name, s.last_name as student_last_name,
                       s.email as student_email,
                       p.program_name
                FROM voucher_usage vu
                JOIN applications a ON vu.application_id = a.id
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                WHERE vu.voucher_id = ?
                ORDER BY vu.used_at DESC
            ");
            $stmt->execute([$voucherId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Voucher usage history error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get voucher statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_vouchers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vouchers,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_vouchers,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_vouchers,
                    SUM(used_count) as total_uses,
                    SUM(discount_value * used_count) as total_discount_given
                FROM vouchers
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Voucher statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if voucher code exists
     */
    public function codeExists($voucherCode, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM vouchers WHERE voucher_code = ?";
            $params = [$voucherCode];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Voucher code check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate discount amount
     */
    private function calculateDiscount($voucherType, $discountValue, $applicationFee) {
        switch ($voucherType) {
            case 'percentage':
                return ($applicationFee * $discountValue) / 100;
            case 'fixed_amount':
                return min($discountValue, $applicationFee);
            case 'full_waiver':
                return $applicationFee;
            default:
                return 0;
        }
    }
    
    /**
     * Generate unique voucher code
     */
    private function generateVoucherCode() {
        $prefix = 'VOUCHER';
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM vouchers WHERE voucher_code LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();
            
            $sequence = str_pad($result['count'] + 1, 6, '0', STR_PAD_LEFT);
            return $prefix . $sequence;
        } catch (Exception $e) {
            error_log("Voucher code generation error: " . $e->getMessage());
            return $prefix . '000001';
        }
    }
    
    /**
     * Get active vouchers for a program
     */
    public function getActiveForProgram($programId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vouchers 
                WHERE status = 'active' 
                AND valid_from <= CURDATE() 
                AND valid_until >= CURDATE()
                AND (applicable_programs = '[]' OR JSON_CONTAINS(applicable_programs, ?))
                ORDER BY created_at DESC
            ");
            $stmt->execute([json_encode($programId)]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Active vouchers fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create vouchers in bulk
     */
    public function createBulk($data, $quantity) {
        try {
            $this->db->beginTransaction();
            $createdVouchers = [];
            $errors = [];
            
            for ($i = 0; $i < $quantity; $i++) {
                // Generate unique PIN and SERIAL for each voucher
                $voucherData = $data;
                $voucherData['pin'] = $this->generatePIN();
                $voucherData['serial'] = $this->generateSerial();
                $voucherData['voucher_code'] = $this->generateVoucherCode();
                
                $stmt = $this->db->prepare("
                    INSERT INTO vouchers (
                        voucher_code, pin, serial, voucher_type, discount_value, max_uses, valid_from, 
                        valid_until, applicable_programs, applicable_users, description, created_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $voucherData['voucher_code'],
                    $voucherData['pin'],
                    $voucherData['serial'],
                    $voucherData['voucher_type'],
                    $voucherData['discount_value'],
                    $voucherData['max_uses'] ?? 1,
                    $voucherData['valid_from'],
                    $voucherData['valid_until'],
                    json_encode($voucherData['applicable_programs'] ?? []),
                    json_encode($voucherData['applicable_users'] ?? []),
                    $voucherData['description'] ?? null,
                    $voucherData['created_by'],
                    $voucherData['status'] ?? 'active'
                ]);
                
                if ($result) {
                    $voucherId = $this->db->lastInsertId();
                    $createdVouchers[] = [
                        'id' => $voucherId,
                        'voucher_code' => $voucherData['voucher_code'],
                        'pin' => $voucherData['pin'],
                        'serial' => $voucherData['serial']
                    ];
                } else {
                    $errors[] = "Failed to create voucher " . ($i + 1);
                }
            }
            
            if (count($errors) === 0) {
                $this->db->commit();
                return [
                    'success' => true,
                    'created_count' => count($createdVouchers),
                    'vouchers' => $createdVouchers
                ];
            } else {
                $this->db->rollback();
                return [
                    'success' => false,
                    'errors' => $errors,
                    'created_count' => count($createdVouchers)
                ];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Bulk voucher creation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An error occurred during bulk creation'
            ];
        }
    }
    
    /**
     * Validate voucher by PIN and SERIAL
     */
    public function validateByPinSerial($pin, $serial) {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM vouchers v
                LEFT JOIN users u ON v.created_by = u.id
                WHERE v.pin = ? AND v.serial = ?
            ");
            $stmt->execute([$pin, $serial]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Voucher PIN/SERIAL validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique PIN
     */
    private function generatePIN() {
        do {
            $pin = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $exists = $this->pinExists($pin);
        } while ($exists);
        
        return $pin;
    }
    
    /**
     * Generate unique Serial
     */
    private function generateSerial() {
        do {
            $serial = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
            $exists = $this->serialExists($serial);
        } while ($exists);
        
        return $serial;
    }
    
    /**
     * Check if PIN exists
     */
    private function pinExists($pin) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM vouchers WHERE pin = ?");
            $stmt->execute([$pin]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("PIN check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if Serial exists
     */
    private function serialExists($serial) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM vouchers WHERE serial = ?");
            $stmt->execute([$serial]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Serial check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export vouchers to CSV
     */
    public function exportToCSV($filters = []) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'v.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['voucher_type'])) {
                $where[] = 'v.voucher_type = ?';
                $params[] = $filters['voucher_type'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT v.*, u.first_name as created_by_first_name, u.last_name as created_by_last_name
                FROM vouchers v
                LEFT JOIN users u ON v.created_by = u.id
                WHERE $whereClause 
                ORDER BY v.created_at DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Voucher export error: " . $e->getMessage());
            return false;
        }
    }
}
