<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Auth: require logged-in admin-like user
if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Forbidden']);
  exit;
}

try {
  $db = new Database();
  $pdo = $db->getConnection();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
  exit;
}

$applicationId = (int)($_GET['application_id'] ?? 0);
if ($applicationId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid application id']);
  exit;
}

try {
  // Ensure table exists (idempotent)
  $pdo->exec("CREATE TABLE IF NOT EXISTS application_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(30),
    new_status VARCHAR(30) NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app(application_id),
    INDEX idx_changed(changed_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $stmt = $pdo->prepare("SELECT h.id, h.old_status, h.new_status, h.changed_at, u.username, u.email 
                         FROM application_status_history h 
                         LEFT JOIN users u ON u.id = h.changed_by 
                         WHERE h.application_id = ? 
                         ORDER BY h.changed_at DESC, h.id DESC");
  $stmt->execute([$applicationId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Query failed']);
}

?>


