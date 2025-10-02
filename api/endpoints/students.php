<?php
/**
 * Students API Endpoint
 */

require_once '../../models/Student.php';

$studentModel = new Student($database);

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific student
            $student = $studentModel->getById($id);
            if (!$student) {
                apiError('Student not found', 404);
            }
            apiResponse($student);
        } else {
            // List students with pagination and filters
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? null;
            $nationality = $_GET['nationality'] ?? null;
            
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($nationality) $filters['nationality'] = $nationality;
            
            $students = $studentModel->getAll($filters, $page, $limit);
            $total = $studentModel->getCount($filters);
            
            apiResponse([
                'students' => $students,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        break;
        
    case 'POST':
        // Create new student
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        // Validate required fields
        $required = ['first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                apiError("Missing required field: $field");
            }
        }
        
        $studentId = $studentModel->create($input);
        if ($studentId) {
            $student = $studentModel->getById($studentId);
            apiResponse($student, 201, 'Student created successfully');
        } else {
            apiError('Failed to create student', 500);
        }
        break;
        
    case 'PUT':
        // Update student
        if (!$id) {
            apiError('Student ID required');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        $result = $studentModel->update($id, $input);
        if ($result) {
            $student = $studentModel->getById($id);
            apiResponse($student, 200, 'Student updated successfully');
        } else {
            apiError('Failed to update student', 500);
        }
        break;
        
    case 'DELETE':
        // Delete student
        if (!$id) {
            apiError('Student ID required');
        }
        
        $result = $studentModel->delete($id);
        if ($result) {
            apiResponse(null, 200, 'Student deleted successfully');
        } else {
            apiError('Failed to delete student', 500);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
