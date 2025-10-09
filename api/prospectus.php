<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Only logged-in students can access; optionally allow admins testing
if (!isset($_SESSION['student_id']) && !isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

try {
  $db = new Database();
  $pdo = $db->getConnection();
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Server error';
  exit;
}

$programId = (int)($_GET['program_id'] ?? 0);
$applicationId = (int)($_GET['application_id'] ?? 0);

if (!$programId) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

// Authorization: student must own the application (if provided) and be approved
if (isset($_SESSION['student_id'])) {
  $studentId = (int)$_SESSION['student_id'];
  if ($applicationId > 0) {
    $st = $pdo->prepare('SELECT status FROM applications WHERE id=? AND student_id=? AND program_id=? LIMIT 1');
    $st->execute([$applicationId, $studentId, $programId]);
    $app = $st->fetch(PDO::FETCH_ASSOC);
    if (!$app || ($app['status'] !== 'approved')) {
      http_response_code(403);
      echo 'Not allowed';
      exit;
    }
  } else {
    // If no application provided, ensure student has any approved app for the program
    $st = $pdo->prepare("SELECT 1 FROM applications WHERE student_id=? AND program_id=? AND status='approved' LIMIT 1");
    $st->execute([$studentId, $programId]);
    if (!$st->fetchColumn()) {
      http_response_code(403);
      echo 'Not allowed';
      exit;
    }
  }
}

// Fetch prospectus path
$st = $pdo->prepare('SELECT prospectus_path FROM programs WHERE id=? AND status="active" LIMIT 1');
$st->execute([$programId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['prospectus_path'])) {
  http_response_code(404);
  echo 'Not found';
  exit;
}

$relPath = $row['prospectus_path'];
$absPath = $_SERVER['DOCUMENT_ROOT'] . $relPath;
if (!is_file($absPath) || !is_readable($absPath)) {
  http_response_code(404);
  echo 'File missing';
  exit;
}

// Serve securely
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="prospectus.pdf"');
header('Content-Length: ' . filesize($absPath));
header('X-Content-Type-Options: nosniff');
readfile($absPath);
exit;
?>


