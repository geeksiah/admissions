<?php
/**
 * Centralized Router
 * Replaces complex .htaccess routing as recommended in Improvements.txt
 */

class Router {
    private static $instance = null;
    private $routes = [];
    private $middleware = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->setupRoutes();
    }
    
    private function setupRoutes() {
        // Public routes (no authentication required)
        $this->addRoute('GET', '/', 'public/index.php');
        $this->addRoute('GET', '/login', 'login.php');
        $this->addRoute('POST', '/login', 'login.php');
        $this->addRoute('GET', '/logout', 'logout.php');
        $this->addRoute('GET', '/unauthorized', 'unauthorized.php');
        $this->addRoute('GET', '/info', 'info.php');
        
        // Student routes
        $this->addRoute('GET', '/student/login', 'student/login.php');
        $this->addRoute('POST', '/student/login', 'student/login.php');
        $this->addRoute('GET', '/student/signup', 'student/signup.php');
        $this->addRoute('POST', '/student/signup', 'student/signup.php');
        $this->addRoute('GET', '/student/verify', 'student/verify.php');
        $this->addRoute('POST', '/student/verify', 'student/verify.php');
        $this->addRoute('GET', '/student/dashboard', 'student/dashboard.php', ['auth' => 'student']);
        $this->addRoute('POST', '/student/dashboard', 'student/dashboard.php', ['auth' => 'student']);
        
        // Admin routes
        $this->addRoute('GET', '/admin/dashboard', 'admin/dashboard.php', ['auth' => 'admin']);
        $this->addRoute('POST', '/admin/dashboard', 'admin/dashboard.php', ['auth' => 'admin']);
        
        // API routes
        $this->addRoute('GET', '/api/application-history', 'api/application_history.php', ['auth' => 'admin']);
        $this->addRoute('GET', '/api/prospectus', 'api/prospectus.php', ['auth' => 'student']);
        
        // Installer routes (only if not installed)
        if (!file_exists('config/installed.lock')) {
            $this->addRoute('GET', '/install', 'install/index.php');
            $this->addRoute('POST', '/install', 'install/index.php');
        }
    }
    
    public function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    public function addMiddleware($name, $callback) {
        $this->middleware[$name] = $callback;
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash except for root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }
        
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $method, $path)) {
                return $this->executeRoute($route);
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        include 'error-pages/404.php';
        exit;
    }
    
    private function matchRoute($route, $method, $path) {
        if ($route['method'] !== $method) {
            return false;
        }
        
        // Simple exact match for now
        // In a more advanced implementation, you'd support route parameters
        return $route['path'] === $path;
    }
    
    private function executeRoute($route) {
        // Execute middleware
        foreach ($route['middleware'] as $middlewareName) {
            if (isset($this->middleware[$middlewareName])) {
                $result = call_user_func($this->middleware[$middlewareName]);
                if ($result === false) {
                    return false; // Middleware blocked the request
                }
            }
        }
        
        // Include the handler file
        $handlerFile = $route['handler'];
        if (file_exists($handlerFile)) {
            include $handlerFile;
            return true;
        } else {
            http_response_code(500);
            die("Handler file not found: $handlerFile");
        }
    }
    
    /**
     * Generate URL for a route
     */
    public function url($path = '') {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'];
        return $baseUrl . $path;
    }
    
    /**
     * Redirect to a route
     */
    public function redirect($path, $statusCode = 302) {
        header("Location: " . $this->url($path), true, $statusCode);
        exit;
    }
}
