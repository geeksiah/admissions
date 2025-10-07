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
}

?>


