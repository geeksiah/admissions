<?php
/**
 * Authentication API Endpoint
 */

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            // User login
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                apiError('Invalid JSON input');
            }
            
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                apiError('Username and password required');
            }
            
            if ($security->authenticate($username, $password)) {
                $user = [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'email' => $_SESSION['email'],
                    'role' => $_SESSION['role'],
                    'first_name' => $_SESSION['first_name'],
                    'last_name' => $_SESSION['last_name']
                ];
                
                apiResponse([
                    'user' => $user,
                    'token' => session_id() // In production, use JWT
                ], 200, 'Login successful');
            } else {
                apiError('Invalid credentials', 401);
            }
        } elseif ($action === 'logout') {
            // User logout
            session_destroy();
            apiResponse(null, 200, 'Logout successful');
        } else {
            apiError('Invalid auth action', 404);
        }
        break;
        
    case 'GET':
        if ($action === 'check') {
            // Check authentication status
            if ($security->isAuthenticated()) {
                $user = [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'email' => $_SESSION['email'],
                    'role' => $_SESSION['role'],
                    'first_name' => $_SESSION['first_name'],
                    'last_name' => $_SESSION['last_name']
                ];
                apiResponse(['authenticated' => true, 'user' => $user]);
            } else {
                apiResponse(['authenticated' => false]);
            }
        } else {
            apiError('Invalid auth action', 404);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
