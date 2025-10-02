<?php
/**
 * Applications API Endpoint
 */

require_once '../../models/Application.php';
require_once '../../models/Student.php';
require_once '../../models/Program.php';

$applicationModel = new Application($database);
$studentModel = new Student($database);
$programModel = new Program($database);

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific application
            $application = $applicationModel->getById($id);
            if (!$application) {
                apiError('Application not found', 404);
            }
            apiResponse($application);
        } else {
            // List applications with pagination and filters
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $status = $_GET['status'] ?? null;
            $program_id = $_GET['program_id'] ?? null;
            
            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($program_id) $filters['program_id'] = $program_id;
            
            $applications = $applicationModel->getAll($filters, $page, $limit);
            $total = $applicationModel->getCount($filters);
            
            apiResponse([
                'applications' => $applications,
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
        // Create new application
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        // Validate required fields
        $required = ['student_id', 'program_id'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                apiError("Missing required field: $field");
            }
        }
        
        $applicationId = $applicationModel->create($input);
        if ($applicationId) {
            $application = $applicationModel->getById($applicationId);
            apiResponse($application, 201, 'Application created successfully');
        } else {
            apiError('Failed to create application', 500);
        }
        break;
        
    case 'PUT':
        // Update application
        if (!$id) {
            apiError('Application ID required');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        $result = $applicationModel->update($id, $input);
        if ($result) {
            $application = $applicationModel->getById($id);
            apiResponse($application, 200, 'Application updated successfully');
        } else {
            apiError('Failed to update application', 500);
        }
        break;
        
    case 'DELETE':
        // Delete application
        if (!$id) {
            apiError('Application ID required');
        }
        
        $result = $applicationModel->delete($id);
        if ($result) {
            apiResponse(null, 200, 'Application deleted successfully');
        } else {
            apiError('Failed to delete application', 500);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
