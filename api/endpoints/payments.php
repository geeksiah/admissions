<?php
/**
 * Payments API Endpoint
 */

require_once '../../models/Payment.php';

$paymentModel = new Payment($database);

switch ($method) {
    case 'GET':
        if ($id) {
            // Get specific payment
            $payment = $paymentModel->getById($id);
            if (!$payment) {
                apiError('Payment not found', 404);
            }
            apiResponse($payment);
        } else {
            // List payments with filters
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $status = $_GET['status'] ?? null;
            $gateway = $_GET['gateway'] ?? null;
            
            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($gateway) $filters['gateway'] = $gateway;
            
            $payments = $paymentModel->getAll($filters, $page, $limit);
            $total = $paymentModel->getCount($filters);
            
            apiResponse([
                'payments' => $payments,
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
        // Create new payment
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        // Validate required fields
        $required = ['application_id', 'amount', 'gateway'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                apiError("Missing required field: $field");
            }
        }
        
        $paymentId = $paymentModel->create($input);
        if ($paymentId) {
            $payment = $paymentModel->getById($paymentId);
            apiResponse($payment, 201, 'Payment created successfully');
        } else {
            apiError('Failed to create payment', 500);
        }
        break;
        
    case 'PUT':
        // Update payment status
        if (!$id) {
            apiError('Payment ID required');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            apiError('Invalid JSON input');
        }
        
        $result = $paymentModel->updateStatus($id, $input['status'], $input['notes'] ?? null);
        if ($result) {
            $payment = $paymentModel->getById($id);
            apiResponse($payment, 200, 'Payment updated successfully');
        } else {
            apiError('Failed to update payment', 500);
        }
        break;
        
    default:
        apiError('Method not allowed', 405);
}
?>
