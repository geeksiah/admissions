<?php
// Database wrapper with PDO proxy and installer-safe config
require_once __DIR__ . '/config.php';

class Database {
    private $host;
    private $name;
    private $user;
    private $pass;
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->host = DB_HOST;
        $this->name = DB_NAME;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->connect();
    }

    private function connect(): void {
        $dsn = "mysql:host={$this->host};dbname={$this->name};charset={$this->charset}";
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new \PDO($dsn, $this->user, $this->pass, $options);
    }

    public function getConnection(): \PDO { return $this->pdo; }

    // Proxy common PDO calls for legacy usage
    public function prepare($sql, $opts = []) { return $this->pdo->prepare($sql, $opts); }
    public function query($sql) { return $this->pdo->query($sql); }
    public function exec($sql) { return $this->pdo->exec($sql); }
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollback(); }
    
    /**
     * Enhanced query methods for common operations
     * Standardizes database interactions as recommended in Improvements.txt
     */
    public function select($table, $conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $sql = "SELECT * FROM `$table`";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $where[] = "`$field` IN ($placeholders)";
                    $params = array_merge($params, $value);
                } else {
                    $where[] = "`$field` = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
            if ($offset) {
                $sql .= " OFFSET $offset";
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function find($table, $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $conditions) {
        $fields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $where = [];
        foreach ($conditions as $field => $value) {
            $where[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    public function delete($table, $conditions) {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    public function count($table, $conditions = []) {
        $sql = "SELECT COUNT(*) FROM `$table`";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "`$field` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    public function exists($table, $conditions) {
        return $this->count($table, $conditions) > 0;
    }
    
    /**
     * Safe query execution with error handling
     */
    public function safeQuery($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new RuntimeException("Database operation failed");
        }
    }
}

?>


