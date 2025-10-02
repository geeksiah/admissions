<?php
/**
 * User Model
 * Handles user-related database operations
 */

class User {
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
     * Create new user
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, role, department, phone, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['username'],
                $data['email'],
                $data['password_hash'],
                $data['first_name'],
                $data['last_name'],
                $data['role'] ?? 'student',
                $data['department'] ?? null,
                $data['phone'] ?? null,
                $data['status'] ?? 'active'
            ]);
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, first_name, last_name, role, department, phone, 
                       created_at, updated_at, last_login, status 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("User fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by username or email
     */
    public function getByUsernameOrEmail($username) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, department, phone, 
                       created_at, updated_at, last_login, status 
                FROM users WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("User fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Authenticate user with username/email and password
     */
    public function authenticate($username, $password) {
        try {
            $user = $this->getByUsernameOrEmail($username);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Return user data without password hash
                unset($user['password_hash']);
                return $user;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user
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
                UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("User update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete user (soft delete)
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET status = 'inactive' WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("User delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['role'])) {
                $where[] = 'role = ?';
                $params[] = $filters['role'];
            }
            
            if (!empty($filters['status'])) {
                $where[] = 'status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT id, username, email, first_name, last_name, role, department, phone, 
                       created_at, updated_at, last_login, status 
                FROM users 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM users WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Users fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
            $params = [$username];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Username check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
            $params = [$email];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get users by role
     */
    public function getByRole($role) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, first_name, last_name, role, department, phone, 
                       created_at, updated_at, last_login, status 
                FROM users 
                WHERE role = ? AND status = 'active' 
                ORDER BY first_name, last_name
            ");
            $stmt->execute([$role]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Users by role fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET last_login = NOW() WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Last login update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($id, $newPasswordHash) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?
            ");
            return $stmt->execute([$newPasswordHash, $id]);
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                    SUM(CASE WHEN role = 'admissions_officer' THEN 1 ELSE 0 END) as admissions_officers,
                    SUM(CASE WHEN role = 'reviewer' THEN 1 ELSE 0 END) as reviewers,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_users
                FROM users
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("User statistics error: " . $e->getMessage());
            return false;
        }
    }
}
