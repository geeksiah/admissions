<?php
/**
 * Dashboard API Endpoint
 */

require_once '../../models/Report.php';
require_once '../../models/Application.php';
require_once '../../models/Payment.php';
require_once '../../models/Program.php';

$reportModel = new Report($database);
$applicationModel = new Application($database);
$paymentModel = new Payment($database);
$programModel = new Program($database);

switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            // Get dashboard statistics
            $stats = $reportModel->getDashboardStats();
            $recentApplications = $applicationModel->getRecent(5);
            $paymentStats = $paymentModel->getStatistics();
            $popularPrograms = $programModel->getPopular(5);
            
            apiResponse([
                'statistics' => $stats,
                'recent_applications' => $recentApplications,
                'payment_statistics' => $paymentStats,
                'popular_programs' => $popularPrograms
            ]);
        } else {
            apiError('Invalid dashboard action', 404);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
