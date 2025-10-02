<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$paymentGatewayManager = new PaymentGatewayManager($database);

// Check admin access
requireRole(['admin']);

$pageTitle = 'Payment Gateway Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Payment Gateways', 'url' => '/admin/payment-gateways.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_config':
                // Update gateway configuration
                $gatewayId = $_POST['gateway_id'];
                $configData = [];
                
                // Collect configuration data based on gateway type
                $gateway = $paymentGatewayManager->getGatewayById($gatewayId);
                if ($gateway) {
                    $gatewayType = $gateway['gateway_type'];
                    
                    switch ($gatewayType) {
                        case 'stripe':
                            $configData = [
                                'publishable_key' => $_POST['publishable_key'] ?? '',
                                'secret_key' => $_POST['secret_key'] ?? '',
                                'test_publishable_key' => $_POST['test_publishable_key'] ?? '',
                                'test_secret_key' => $_POST['test_secret_key'] ?? '',
                                'webhook_secret' => $_POST['webhook_secret'] ?? ''
                            ];
                            break;
                        case 'paypal':
                            $configData = [
                                'client_id' => $_POST['client_id'] ?? '',
                                'client_secret' => $_POST['client_secret'] ?? '',
                                'test_client_id' => $_POST['test_client_id'] ?? '',
                                'test_client_secret' => $_POST['test_client_secret'] ?? '',
                                'webhook_id' => $_POST['webhook_id'] ?? ''
                            ];
                            break;
                        case 'paystack':
                            $configData = [
                                'public_key' => $_POST['public_key'] ?? '',
                                'secret_key' => $_POST['secret_key'] ?? '',
                                'test_public_key' => $_POST['test_public_key'] ?? '',
                                'test_secret_key' => $_POST['test_secret_key'] ?? '',
                                'webhook_secret' => $_POST['webhook_secret'] ?? ''
                            ];
                            break;
                        case 'hubtel':
                            $configData = [
                                'client_id' => $_POST['client_id'] ?? '',
                                'client_secret' => $_POST['client_secret'] ?? '',
                                'test_client_id' => $_POST['test_client_id'] ?? '',
                                'test_client_secret' => $_POST['test_client_secret'] ?? '',
                                'webhook_secret' => $_POST['webhook_secret'] ?? ''
                            ];
                            break;
                        case 'flutterwave':
                            $configData = [
                                'public_key' => $_POST['public_key'] ?? '',
                                'secret_key' => $_POST['secret_key'] ?? '',
                                'test_public_key' => $_POST['test_public_key'] ?? '',
                                'test_secret_key' => $_POST['test_secret_key'] ?? '',
                                'webhook_secret' => $_POST['webhook_secret'] ?? ''
                            ];
                            break;
                        case 'razorpay':
                            $configData = [
                                'key_id' => $_POST['key_id'] ?? '',
                                'key_secret' => $_POST['key_secret'] ?? '',
                                'test_key_id' => $_POST['test_key_id'] ?? '',
                                'test_key_secret' => $_POST['test_key_secret'] ?? '',
                                'webhook_secret' => $_POST['webhook_secret'] ?? ''
                            ];
                            break;
                    }
                    
                    if ($paymentGatewayManager->updateGatewayConfig($gatewayId, $configData)) {
                        $message = 'Gateway configuration updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update gateway configuration.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Gateway not found.';
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle_status':
                // Toggle gateway status
                $gatewayId = $_POST['gateway_id'];
                $isActive = $_POST['is_active'] === '1';
                
                try {
                    $stmt = $database->getConnection()->prepare("
                        UPDATE payment_gateways 
                        SET is_active = ? 
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$isActive ? 1 : 0, $gatewayId])) {
                        $message = 'Gateway status updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update gateway status.';
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Error updating gateway status.';
                    $messageType = 'danger';
                }
                break;
                
            case 'set_default':
                // Set default gateway
                $gatewayId = $_POST['gateway_id'];
                if ($paymentGatewayManager->setDefaultGateway($gatewayId)) {
                    $message = 'Default gateway updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to set default gateway.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get all gateways
$gateways = $paymentGatewayManager->getAvailableGateways();
$defaultGateway = $paymentGatewayManager->getDefaultGateway();

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="bi bi-credit-card me-2"></i>Payment Gateway Management</h2>
            <div class="text-muted">
                <small>Default Gateway: <strong><?php echo $defaultGateway['display_name'] ?? 'None'; ?></strong></small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php foreach ($gateways as $gateway): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo $gateway['display_name']; ?></h5>
                    <div class="d-flex gap-2">
                        <?php if ($gateway['is_default']): ?>
                            <span class="badge bg-primary">Default</span>
                        <?php endif; ?>
                        <span class="badge bg-<?php echo $gateway['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $gateway['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text text-muted"><?php echo $gateway['description']; ?></p>
                    
                    <div class="mb-3">
                        <small class="text-muted">Supported Currencies:</small>
                        <div class="mt-1">
                            <?php 
                            $currencies = json_decode($gateway['supported_currencies'], true) ?? [];
                            foreach ($currencies as $currency): 
                            ?>
                                <span class="badge bg-light text-dark me-1"><?php echo $currency; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Processing Fee:</small>
                        <div class="mt-1">
                            <?php if ($gateway['processing_fee_percentage'] > 0): ?>
                                <span class="badge bg-info"><?php echo $gateway['processing_fee_percentage']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($gateway['processing_fee_fixed'] > 0): ?>
                                <span class="badge bg-warning">$<?php echo $gateway['processing_fee_fixed']; ?></span>
                            <?php endif; ?>
                            <?php if ($gateway['processing_fee_percentage'] == 0 && $gateway['processing_fee_fixed'] == 0): ?>
                                <span class="badge bg-success">No Fee</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Amount Range:</small>
                        <div class="mt-1">
                            <span class="badge bg-light text-dark">$<?php echo $gateway['min_amount']; ?> - $<?php echo $gateway['max_amount']; ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Mode:</small>
                        <span class="badge bg-<?php echo $gateway['test_mode'] ? 'warning' : 'success'; ?>">
                            <?php echo $gateway['test_mode'] ? 'Test Mode' : 'Live Mode'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="configureGateway(<?php echo $gateway['id']; ?>)"
                                title="Configure">
                            <i class="bi bi-gear"></i>
                        </button>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $gateway['is_active'] ? '0' : '1'; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $gateway['is_active'] ? 'warning' : 'success'; ?>"
                                    title="<?php echo $gateway['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                <i class="bi bi-<?php echo $gateway['is_active'] ? 'pause' : 'play'; ?>"></i>
                            </button>
                        </form>
                        
                        <?php if (!$gateway['is_default']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                        title="Set as Default">
                                    <i class="bi bi-star"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Configuration Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Payment Gateway</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="configForm">
                <input type="hidden" name="action" value="update_config">
                <input type="hidden" name="gateway_id" id="config_gateway_id">
                <div class="modal-body" id="configModalBody">
                    <!-- Configuration fields will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function configureGateway(gatewayId) {
    // Get gateway data
    const gateway = <?php echo json_encode($gateways); ?>.find(g => g.id == gatewayId);
    if (!gateway) return;
    
    document.getElementById('config_gateway_id').value = gatewayId;
    
    let configFields = '';
    const config = JSON.parse(gateway.config_data || '{}');
    
    switch (gateway.gateway_type) {
        case 'stripe':
            configFields = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="publishable_key" class="form-label">Live Publishable Key</label>
                            <input type="text" class="form-control" id="publishable_key" name="publishable_key" 
                                   value="${config.publishable_key || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="secret_key" class="form-label">Live Secret Key</label>
                            <input type="password" class="form-control" id="secret_key" name="secret_key" 
                                   value="${config.secret_key || ''}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_publishable_key" class="form-label">Test Publishable Key</label>
                            <input type="text" class="form-control" id="test_publishable_key" name="test_publishable_key" 
                                   value="${config.test_publishable_key || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_secret_key" class="form-label">Test Secret Key</label>
                            <input type="password" class="form-control" id="test_secret_key" name="test_secret_key" 
                                   value="${config.test_secret_key || ''}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="webhook_secret" class="form-label">Webhook Secret</label>
                    <input type="password" class="form-control" id="webhook_secret" name="webhook_secret" 
                           value="${config.webhook_secret || ''}">
                </div>
            `;
            break;
            
        case 'paystack':
            configFields = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="public_key" class="form-label">Live Public Key</label>
                            <input type="text" class="form-control" id="public_key" name="public_key" 
                                   value="${config.public_key || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="secret_key" class="form-label">Live Secret Key</label>
                            <input type="password" class="form-control" id="secret_key" name="secret_key" 
                                   value="${config.secret_key || ''}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_public_key" class="form-label">Test Public Key</label>
                            <input type="text" class="form-control" id="test_public_key" name="test_public_key" 
                                   value="${config.test_public_key || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_secret_key" class="form-label">Test Secret Key</label>
                            <input type="password" class="form-control" id="test_secret_key" name="test_secret_key" 
                                   value="${config.test_secret_key || ''}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="webhook_secret" class="form-label">Webhook Secret</label>
                    <input type="password" class="form-control" id="webhook_secret" name="webhook_secret" 
                           value="${config.webhook_secret || ''}">
                </div>
            `;
            break;
            
        case 'hubtel':
            configFields = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Live Client ID</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" 
                                   value="${config.client_id || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_secret" class="form-label">Live Client Secret</label>
                            <input type="password" class="form-control" id="client_secret" name="client_secret" 
                                   value="${config.client_secret || ''}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_client_id" class="form-label">Test Client ID</label>
                            <input type="text" class="form-control" id="test_client_id" name="test_client_id" 
                                   value="${config.test_client_id || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="test_client_secret" class="form-label">Test Client Secret</label>
                            <input type="password" class="form-control" id="test_client_secret" name="test_client_secret" 
                                   value="${config.test_client_secret || ''}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="webhook_secret" class="form-label">Webhook Secret</label>
                    <input type="password" class="form-control" id="webhook_secret" name="webhook_secret" 
                           value="${config.webhook_secret || ''}">
                </div>
            `;
            break;
            
        default:
            configFields = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Configuration for ${gateway.display_name} will be available soon.
                </div>
            `;
    }
    
    document.getElementById('configModalBody').innerHTML = configFields;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('configModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
