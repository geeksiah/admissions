<?php
/**
 * Reports API Endpoint
 */

require_once '../../models/Report.php';

$reportModel = new Report($database);

switch ($method) {
    case 'GET':
        if ($action === 'applications') {
            // Get application statistics
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'program_id' => $_GET['program_id'] ?? null
            ];
            
            $stats = $reportModel->getApplicationStatistics($filters);
            apiResponse($stats);
            
        } elseif ($action === 'programs') {
            // Get program statistics
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            
            $stats = $reportModel->getApplicationsByProgram($filters);
            apiResponse($stats);
            
        } elseif ($action === 'trends') {
            // Get application trends
            $days = $_GET['days'] ?? 30;
            $trends = $reportModel->getApplicationTrends($days);
            apiResponse($trends);
            
        } else {
            apiError('Invalid report type', 404);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
