<?php
/**
 * Comprehensive Admissions Management System
 * RESTful API Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';

// Initialize database and security
$database = new Database();
$security = new Security($database);

// API Response helper
function apiResponse($data = null, $status = 200, $message = 'Success') {
    http_response_code($status);
    echo json_encode([
        'status' => $status < 400 ? 'success' : 'error',
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}

// API Error handler
function apiError($message, $status = 400) {
    apiResponse(null, $status, $message);
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$pathSegments = array_filter(explode('/', $path));

// Route the request
try {
    if (empty($pathSegments)) {
        apiResponse([
            'name' => 'Admissions Management System API',
            'version' => '1.0.0',
            'endpoints' => [
                'GET /api/applications' => 'List applications',
                'GET /api/applications/{id}' => 'Get application details',
                'POST /api/applications' => 'Create application',
                'PUT /api/applications/{id}' => 'Update application',
                'DELETE /api/applications/{id}' => 'Delete application',
                'GET /api/students' => 'List students',
                'GET /api/students/{id}' => 'Get student details',
                'POST /api/students' => 'Create student',
                'GET /api/programs' => 'List programs',
                'GET /api/programs/{id}' => 'Get program details',
                'GET /api/dashboard/stats' => 'Get dashboard statistics',
                'POST /api/auth/login' => 'User authentication',
                'POST /api/auth/logout' => 'User logout'
            ]
        ]);
    }

    $resource = $pathSegments[0];
    $id = isset($pathSegments[1]) ? $pathSegments[1] : null;
    $action = isset($pathSegments[2]) ? $pathSegments[2] : null;

    // Authentication check for protected endpoints
    $protectedEndpoints = ['applications', 'students', 'programs', 'dashboard', 'auth/logout'];
    if (in_array($resource, $protectedEndpoints) && $resource !== 'auth') {
        if (!$security->isAuthenticated()) {
            apiError('Authentication required', 401);
        }
    }

    // Route to appropriate handler
    switch ($resource) {
        case 'applications':
            require_once 'endpoints/applications.php';
            break;
        case 'students':
            require_once 'endpoints/students.php';
            break;
        case 'programs':
            require_once 'endpoints/programs.php';
            break;
        case 'dashboard':
            require_once 'endpoints/dashboard.php';
            break;
        case 'auth':
            require_once 'endpoints/auth.php';
            break;
        case 'reports':
            require_once 'endpoints/reports.php';
            break;
        case 'payments':
            require_once 'endpoints/payments.php';
            break;
        default:
            apiError('Resource not found', 404);
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    apiError('Internal server error', 500);
}
?>
