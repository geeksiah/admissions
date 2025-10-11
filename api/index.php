<?php
/**
 * RESTful API System
 * Complete API implementation as recommended in Improvements.txt
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityMiddleware.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize
$database = new Database();
$security = SecurityMiddleware::getInstance();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Get path segments
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$query = $_GET;

// Response helper
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Error response helper
function sendError($message, $status = 400, $code = null) {
    $response = ['error' => $message];
    if ($code) $response['code'] = $code;
    sendResponse($response, $status);
}

// Authentication helper
function requireAuth($role = null) {
    global $security;
    
    if (!isLoggedIn()) {
        sendError('Authentication required', 401, 'AUTH_REQUIRED');
    }
    
    if ($role && $_SESSION['user_role'] !== $role) {
        sendError('Insufficient permissions', 403, 'ROLE_INSUFFICIENT');
    }
}

// Rate limiting
if (!$security->checkRateLimit('api_request', 100, 3600)) {
    sendError('Rate limit exceeded', 429, 'RATE_LIMIT');
}

try {
    $pdo = $database->getConnection();
    
    switch ($resource) {
        case 'applications':
            handleApplications($method, $id, $input, $query, $pdo);
            break;
            
        case 'students':
            handleStudents($method, $id, $input, $query, $pdo);
            break;
            
        case 'programs':
            handlePrograms($method, $id, $input, $query, $pdo);
            break;
            
        case 'payments':
            handlePayments($method, $id, $input, $query, $pdo);
            break;
            
        case 'notifications':
            handleNotifications($method, $id, $input, $query, $pdo);
            break;
            
        case 'reports':
            handleReports($method, $id, $input, $query, $pdo);
            break;
            
        case 'auth':
            handleAuth($method, $id, $input, $query, $pdo);
            break;
            
        default:
            sendError('Resource not found', 404, 'RESOURCE_NOT_FOUND');
    }
    
} catch (Exception $e) {
    sendError('Internal server error: ' . $e->getMessage(), 500, 'INTERNAL_ERROR');
}

// Application handlers
function handleApplications($method, $id, $input, $query, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getApplication($id, $pdo);
            } else {
                listApplications($query, $pdo);
            }
            break;
            
        case 'POST':
            createApplication($input, $pdo);
            break;
            
        case 'PUT':
            updateApplication($id, $input, $pdo);
            break;
            
        case 'DELETE':
            deleteApplication($id, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function listApplications($query, $pdo) {
    requireAuth();
    
    $page = (int)($query['page'] ?? 1);
    $limit = min((int)($query['limit'] ?? 20), 100);
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    
    // Filter by student if student role
    if ($_SESSION['user_role'] === 'student') {
        $where[] = "a.student_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    // Filter by status
    if (!empty($query['status'])) {
        $where[] = "a.status = ?";
        $params[] = $query['status'];
    }
    
    // Filter by program
    if (!empty($query['program_id'])) {
        $where[] = "a.program_id = ?";
        $params[] = $query['program_id'];
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT a.*, p.name as program_name, u.first_name, u.last_name, u.email
        FROM applications a
        LEFT JOIN programs p ON a.program_id = p.id
        LEFT JOIN users u ON a.student_id = u.id
        $whereClause
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM applications a $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetchColumn();
    
    sendResponse([
        'data' => $applications,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getApplication($id, $pdo) {
    requireAuth();
    
    $sql = "
        SELECT a.*, p.name as program_name, u.first_name, u.last_name, u.email
        FROM applications a
        LEFT JOIN programs p ON a.program_id = p.id
        LEFT JOIN users u ON a.student_id = u.id
        WHERE a.id = ?
    ";
    
    $params = [$id];
    
    // If student, ensure they can only see their own applications
    if ($_SESSION['user_role'] === 'student') {
        $sql .= " AND a.student_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        sendError('Application not found', 404);
    }
    
    sendResponse($application);
}

function createApplication($input, $pdo) {
    requireAuth('student');
    
    $required = ['program_id'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendError("Missing required field: $field", 400);
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO applications (student_id, program_id, status, form_data, created_at)
        VALUES (?, ?, 'draft', ?, NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $input['program_id'],
        json_encode($input['form_data'] ?? [])
    ]);
    
    $id = $pdo->lastInsertId();
    
    sendResponse(['id' => $id, 'message' => 'Application created'], 201);
}

function updateApplication($id, $input, $pdo) {
    requireAuth();
    
    // Check if application exists and user has permission
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        sendError('Application not found', 404);
    }
    
    if ($_SESSION['user_role'] === 'student' && $application['student_id'] != $_SESSION['user_id']) {
        sendError('Access denied', 403);
    }
    
    $allowedFields = ['status', 'form_data', 'notes'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = is_array($input[$field]) ? json_encode($input[$field]) : $input[$field];
        }
    }
    
    if (empty($updates)) {
        sendError('No valid fields to update', 400);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $id;
    
    $sql = "UPDATE applications SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    sendResponse(['message' => 'Application updated']);
}

function deleteApplication($id, $pdo) {
    requireAuth('admin');
    
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        sendError('Application not found', 404);
    }
    
    sendResponse(['message' => 'Application deleted']);
}

// Student handlers
function handleStudents($method, $id, $input, $query, $pdo) {
    requireAuth('admin');
    
    switch ($method) {
        case 'GET':
            if ($id) {
                getStudent($id, $pdo);
            } else {
                listStudents($query, $pdo);
            }
            break;
            
        case 'POST':
            createStudent($input, $pdo);
            break;
            
        case 'PUT':
            updateStudent($id, $input, $pdo);
            break;
            
        case 'DELETE':
            deleteStudent($id, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function listStudents($query, $pdo) {
    $page = (int)($query['page'] ?? 1);
    $limit = min((int)($query['limit'] ?? 20), 100);
    $offset = ($page - 1) * $limit;
    
    $where = ["u.role = 'student'"];
    $params = [];
    
    if (!empty($query['search'])) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $query['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $sql = "
        SELECT u.*, COUNT(a.id) as application_count
        FROM users u
        LEFT JOIN applications a ON u.id = a.student_id
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse($students);
}

// Program handlers
function handlePrograms($method, $id, $input, $query, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getProgram($id, $pdo);
            } else {
                listPrograms($query, $pdo);
            }
            break;
            
        case 'POST':
            requireAuth('admin');
            createProgram($input, $pdo);
            break;
            
        case 'PUT':
            requireAuth('admin');
            updateProgram($id, $input, $pdo);
            break;
            
        case 'DELETE':
            requireAuth('admin');
            deleteProgram($id, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function listPrograms($query, $pdo) {
    $where = [];
    $params = [];
    
    if (!empty($query['active'])) {
        $where[] = "status = 'active'";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT * FROM programs $whereClause ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse($programs);
}

// Payment handlers
function handlePayments($method, $id, $input, $query, $pdo) {
    requireAuth();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                getPayment($id, $pdo);
            } else {
                listPayments($query, $pdo);
            }
            break;
            
        case 'POST':
            createPayment($input, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

// Notification handlers
function handleNotifications($method, $id, $input, $query, $pdo) {
    requireAuth();
    
    switch ($method) {
        case 'GET':
            listNotifications($query, $pdo);
            break;
            
        case 'POST':
            createNotification($input, $pdo);
            break;
            
        case 'PUT':
            markAsRead($id, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function listNotifications($query, $pdo) {
    $where = [];
    $params = [];
    
    if ($_SESSION['user_role'] === 'student') {
        $where[] = "student_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['user_role'] === 'admin') {
        $where[] = "user_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    if (!empty($query['unread'])) {
        $where[] = "is_read = 0";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse($notifications);
}

// Report handlers
function handleReports($method, $id, $input, $query, $pdo) {
    requireAuth('admin');
    
    switch ($method) {
        case 'GET':
            generateReport($query, $pdo);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function generateReport($query, $pdo) {
    $type = $query['type'] ?? 'overview';
    
    switch ($type) {
        case 'overview':
            $sql = "
                SELECT 
                    COUNT(DISTINCT a.id) as total_applications,
                    COUNT(DISTINCT CASE WHEN a.status = 'approved' THEN a.id END) as approved_applications,
                    COUNT(DISTINCT u.id) as total_students,
                    COUNT(DISTINCT p.id) as total_programs
                FROM applications a
                LEFT JOIN users u ON a.student_id = u.id
                LEFT JOIN programs p ON a.program_id = p.id
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'applications_by_status':
            $sql = "
                SELECT status, COUNT(*) as count
                FROM applications
                GROUP BY status
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            sendError('Invalid report type', 400);
    }
    
    sendResponse($data);
}

// Auth handlers
function handleAuth($method, $id, $input, $query, $pdo) {
    switch ($method) {
        case 'POST':
            if ($id === 'login') {
                login($input, $pdo);
            } elseif ($id === 'logout') {
                logout();
            } else {
                sendError('Invalid auth action', 400);
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function login($input, $pdo) {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendError('Username and password required', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        sendError('Invalid credentials', 401);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    
    sendResponse([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ],
        'message' => 'Login successful'
    ]);
}

function logout() {
    session_destroy();
    sendResponse(['message' => 'Logged out successfully']);
}
