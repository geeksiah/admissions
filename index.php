<?php
/**
 * Main Application Entry Point
 * Uses centralized router as recommended in Improvements.txt
 */

// Start session
session_start();

// Load configuration
require_once 'config/config.php';

// Load classes
require_once 'classes/Database.php';
require_once 'classes/Router.php';
require_once 'classes/SecurityMiddleware.php';

// Initialize router
$router = Router::getInstance();

// Add middleware
$security = SecurityMiddleware::getInstance();
$router->addMiddleware('auth', function() use ($security) {
    $security->requireLogin();
    return true;
});

$router->addMiddleware('admin', function() use ($security) {
    $security->requireRole('admin');
    return true;
});

$router->addMiddleware('student', function() use ($security) {
    $security->requireRole('student');
    return true;
});

// Dispatch the request
$router->dispatch();


