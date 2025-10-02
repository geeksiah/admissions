<?php
/**
 * Programs API Endpoint
 */

require_once '../../models/Program.php';

$programModel = new Program($database);

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific program
            $program = $programModel->getById($id);
            if (!$program) {
                apiError('Program not found', 404);
            }
            apiResponse($program);
        } else {
            // List programs with filters
            $active_only = $_GET['active_only'] ?? true;
            $level = $_GET['level'] ?? null;
            $department = $_GET['department'] ?? null;
            
            if ($active_only) {
                $programs = $programModel->getActive();
            } else {
                $programs = $programModel->getAll();
            }
            
            // Apply additional filters
            if ($level) {
                $programs = array_filter($programs, function($p) use ($level) {
                    return $p['level_name'] === $level;
                });
            }
            
            if ($department) {
                $programs = array_filter($programs, function($p) use ($department) {
                    return $p['department'] === $department;
                });
            }
            
            apiResponse(array_values($programs));
        }
        break;
        
    case 'POST':
        // Create new program (admin only)
        if ($_SESSION['role'] !== 'admin') {
            apiError('Admin access required', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        // Validate required fields
        $required = ['program_name', 'program_code', 'level_name', 'department'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                apiError("Missing required field: $field");
            }
        }
        
        $input['created_by'] = $_SESSION['user_id'];
        $programId = $programModel->create($input);
        
        if ($programId) {
            $program = $programModel->getById($programId);
            apiResponse($program, 201, 'Program created successfully');
        } else {
            apiError('Failed to create program', 500);
        }
        break;
        
    case 'PUT':
        // Update program (admin only)
        if ($_SESSION['role'] !== 'admin') {
            apiError('Admin access required', 403);
        }
        
        if (!$id) {
            apiError('Program ID required');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        $result = $programModel->update($id, $input);
        if ($result) {
            $program = $programModel->getById($id);
            apiResponse($program, 200, 'Program updated successfully');
        } else {
            apiError('Failed to update program', 500);
        }
        break;
        
    case 'DELETE':
        // Delete program (admin only)
        if ($_SESSION['role'] !== 'admin') {
            apiError('Admin access required', 403);
        }
        
        if (!$id) {
            apiError('Program ID required');
        }
        
        try {
            $result = $programModel->delete($id);
            if ($result) {
                apiResponse(null, 200, 'Program deleted successfully');
            } else {
                apiError('Failed to delete program', 500);
            }
        } catch (Exception $e) {
            apiError($e->getMessage(), 400);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
