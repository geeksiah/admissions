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
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        // Prefer installer/config constants when available
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->db_name = defined('DB_NAME') ? DB_NAME : '';
        $this->username = defined('DB_USER') ? DB_USER : '';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        $this->connect();
    }

    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->pdo = new \PDO($dsn, $this->username, $this->password, $options);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Transaction helpers
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollback(); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }

    // Common PDO proxy methods to support legacy calls like $database->prepare()
    public function prepare($statement, $options = []) { return $this->pdo->prepare($statement, $options); }
    public function query($statement) { return $this->pdo->query($statement); }
    public function exec($statement) { return $this->pdo->exec($statement); }
    public function quote($string, $parameter_type = \PDO::PARAM_STR) { return $this->pdo->quote($string, $parameter_type); }
    public function inTransaction() { return $this->pdo->inTransaction(); }
    public function errorCode() { return $this->pdo->errorCode(); }
    public function errorInfo() { return $this->pdo->errorInfo(); }

    // Fallback: forward any other PDO method calls
    public function __call($name, $arguments) {
        if (method_exists($this->pdo, $name)) {
            return $this->pdo->$name(...$arguments);
        }
        throw new \BadMethodCallException("Method {$name} does not exist on Database or PDO");
    }
}
