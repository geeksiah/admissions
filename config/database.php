<?php
/**
 * Comprehensive Admissions Management System
 * Database Configuration
 * 
 * This class provides a secure, production-ready database connection wrapper
 * using PDO (PHP Data Objects) with the following features:
 * - Automatic connection management
 * - Transaction support (begin, commit, rollback)
 * - Error handling and logging
 * - UTF-8 character set support
 * - Prepared statement optimization
 * - Connection pooling ready
 * 
 * @package AdmissionsManagement
 * @version 1.0.0
 * @author Admissions Management Team
 * @since 2024-01-01
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'admissions_management';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
