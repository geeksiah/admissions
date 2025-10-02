<?php
/**
 * License Server API
 * Central license management server
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

require_once '../config/license-config.php';
require_once '../classes/LicenseServer.php';

// Initialize license server
$licenseServer = new LicenseServer();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$pathSegments = array_filter(explode('/', $path));

// Route the request
try {
    if (empty($pathSegments)) {
        apiResponse([
            'name' => 'Admissions Management License Server',
            'version' => '1.0.0',
            'endpoints' => [
                'POST /api/validate' => 'Validate license',
                'POST /api/activate' => 'Activate license',
                'POST /api/deactivate' => 'Deactivate license',
                'GET /api/status/{license_key}' => 'Get license status',
                'POST /api/heartbeat' => 'Send heartbeat',
                'GET /api/installations' => 'List installations',
                'GET /api/analytics' => 'Get analytics'
            ]
        ]);
    }

    $action = $pathSegments[0];
    $id = isset($pathSegments[1]) ? $pathSegments[1] : null;

    // Route to appropriate handler
    switch ($action) {
        case 'validate':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $licenseServer->validateLicense($input);
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'activate':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $licenseServer->activateLicense($input);
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'deactivate':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $licenseServer->deactivateLicense($input);
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'status':
            if ($method === 'GET' && $id) {
                $result = $licenseServer->getLicenseStatus($id);
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'heartbeat':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $licenseServer->processHeartbeat($input);
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'installations':
            if ($method === 'GET') {
                $result = $licenseServer->getInstallations();
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        case 'analytics':
            if ($method === 'GET') {
                $result = $licenseServer->getAnalytics();
                apiResponse($result);
            } else {
                apiError('Method not allowed', 405);
            }
            break;
            
        default:
            apiError('Endpoint not found', 404);
    }

} catch (Exception $e) {
    error_log("License Server API Error: " . $e->getMessage());
    apiError('Internal server error', 500);
}

// Helper functions
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

function apiError($message, $status = 400) {
    apiResponse(null, $status, $message);
}
?>
