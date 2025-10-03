<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../models/Program.php';

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid program ID');
    }
    
    $programId = (int)$_GET['id'];
    $database = new Database();
    $pdo = $database->getConnection();
    
    $programModel = new Program($pdo);
    $program = $programModel->getById($programId);
    
    if (!$program) {
        throw new Exception('Program not found');
    }
    
    echo json_encode([
        'success' => true,
        'program' => $program
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
