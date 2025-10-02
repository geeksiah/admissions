<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$paymentModel = new Payment($database);
$applicationModel = new Application($database);
$voucherModel = new Voucher($database);
$programModel = new Program($database);
$paymentGatewayManager = new PaymentGatewayManager($database);

// Check student access
requireRole(['student']);

$pageTitle = 'Payment Processing';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'My Applications', 'url' => '/student/applications.php'],
    ['name' => 'Payment', 'url' => '/student/payment.php']
];

// Get application ID from URL
$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$applicationId) {
    redirect('/student/applications.php');
}

// Get application details
$application = $applicationModel->getById($applicationId);
if (!$application) {
    redirect('/student/applications.php');
}

// Check if application belongs to current user
$student = $applicationModel->getStudentByApplicationId($applicationId);
if (!$student || $student['email'] !== $_SESSION['email']) {
    redirect('/unauthorized.php');
}

// Get program details
$program = $programModel->getById($application['program_id']);

// Check if payment is already completed
$hasPayment = $paymentModel->hasCompletedPayment($applicationId);
$totalPaid = $paymentModel->getTotalPaid($applicationId);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'apply_voucher':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $voucherCode = sanitizeInput($_POST['voucher_code']);
                    $result = $voucherModel->validateAndApply($voucherCode, $applicationId, $program['application_fee'], $program['id'], $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $message = 'Voucher applied successfully! Discount: $' . number_format($result['discount_amount'], 2);
                        $messageType = 'success';
                        $finalAmount = $result['final_amount'];
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'process_payment':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $gatewayId = $_POST['gateway_id'];
                    $amount = $_POST['amount'];
                    $currency = $_POST['currency'] ?? 'USD';
                    $description = "Application fee for {$program['program_name']} - {$application['application_number']}";
                    $metadata = [
                        'application_id' => $applicationId,
                        'application_number' => $application['application_number'],
                        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                        'student_email' => $student['email']
                    ];
                    
                    // Process payment through selected gateway
                    $paymentResult = $paymentGatewayManager->processPayment($gatewayId, $amount, $currency, $description, $metadata);
                    
                    if ($paymentResult['success']) {
                        // Create payment record
                        $paymentData = [
                            'application_id' => $applicationId,
                            'transaction_id' => $paymentResult['payment_intent_id'] ?? $paymentResult['order_id'] ?? $paymentResult['reference'] ?? 'TXN_' . time(),
                            'amount' => $amount,
                            'currency' => $currency,
                            'payment_method' => $_POST['payment_method'],
                            'payment_gateway' => $paymentGatewayManager->getGatewayById($gatewayId)['gateway_name'],
                            'gateway_transaction_id' => $paymentResult['payment_intent_id'] ?? $paymentResult['order_id'] ?? $paymentResult['reference'] ?? '',
                            'payment_status' => 'pending',
                            'processed_by' => $_SESSION['user_id']
                        ];
                        
                        $paymentId = $paymentModel->create($paymentData);
                        if ($paymentId) {
                            $message = 'Payment initiated successfully! Redirecting to payment gateway...';
                            $messageType = 'success';
                            
                            // Store payment result for redirection
                            $_SESSION['payment_redirect'] = $paymentResult;
                            $_SESSION['payment_id'] = $paymentId;
                        } else {
                            $message = 'Failed to create payment record. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = $paymentResult['error'] ?? 'Payment processing failed. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Calculate amounts
$applicationFee = $program['application_fee'] ?? 0;
$discountAmount = 0; // This would come from voucher application
$finalAmount = $applicationFee - $discountAmount;

// Get available payment gateways
$availableGateways = $paymentGatewayManager->getAvailableGateways();
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

<div class="row">
    <div class="col-lg-8">
        <!-- Payment Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-credit-card me-2"></i>Payment Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Application Details</h6>
                        <p><strong>Application Number:</strong> <?php echo $application['application_number']; ?></p>
                        <p><strong>Program:</strong> <?php echo $program['program_name']; ?></p>
                        <p><strong>Department:</strong> <?php echo $program['department']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Payment Summary</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr>
                                    <td>Application Fee:</td>
                                    <td class="text-end"><?php echo formatCurrency($applicationFee); ?></td>
                                </tr>
                                <?php if ($discountAmount > 0): ?>
                                <tr class="text-success">
                                    <td>Discount:</td>
                                    <td class="text-end">-<?php echo formatCurrency($discountAmount); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="fw-bold">
                                    <td>Total Amount:</td>
                                    <td class="text-end"><?php echo formatCurrency($finalAmount); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$hasPayment): ?>
        <!-- Voucher Application -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-ticket-perforated me-2"></i>Apply Voucher or Waiver Code
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="apply_voucher">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="col-md-8">
                        <label for="voucher_code" class="form-label">Voucher Code</label>
                        <input type="text" class="form-control" id="voucher_code" name="voucher_code" 
                               placeholder="Enter your voucher or waiver code">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-check-circle me-2"></i>Apply Voucher
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-wallet2 me-2"></i>Payment Methods
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="amount" value="<?php echo $finalAmount; ?>">
                    <input type="hidden" name="currency" value="USD">
                    <input type="hidden" name="transaction_id" id="transaction_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Payment Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                    <label class="form-check-label" for="credit_card">
                                        <i class="bi bi-credit-card me-2"></i>Credit/Debit Card
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                                    <label class="form-check-label" for="bank_transfer">
                                        <i class="bi bi-bank me-2"></i>Bank Transfer
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash">
                                    <label class="form-check-label" for="cash">
                                        <i class="bi bi-cash me-2"></i>Cash Payment
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gateway_id" class="form-label">Payment Gateway</label>
                                <select class="form-select" id="gateway_id" name="gateway_id" required>
                                    <option value="">Select Payment Gateway</option>
                                    <?php foreach ($availableGateways as $gateway): ?>
                                        <option value="<?php echo $gateway['id']; ?>" 
                                                <?php echo ($defaultGateway && $gateway['id'] == $defaultGateway['id']) ? 'selected' : ''; ?>
                                                data-gateway-type="<?php echo $gateway['gateway_type']; ?>"
                                                data-processing-fee="<?php echo $gateway['processing_fee_percentage']; ?>"
                                                data-fixed-fee="<?php echo $gateway['processing_fee_fixed']; ?>">
                                            <?php echo $gateway['display_name']; ?>
                                            <?php if ($gateway['is_default']): ?>
                                                (Default)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select your preferred payment method</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Credit Card Form (shown by default) -->
                    <div id="creditCardForm" class="payment-method-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="card_number" class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="expiry_date" placeholder="MM/YY" maxlength="5">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="cvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cardholder_name" class="form-label">Cardholder Name</label>
                                    <input type="text" class="form-control" id="cardholder_name" placeholder="John Doe">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_address" class="form-label">Billing Address</label>
                                    <input type="text" class="form-control" id="billing_address" placeholder="123 Main St, City, State">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Form -->
                    <div id="bankTransferForm" class="payment-method-form" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Bank Transfer Instructions:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Transfer the amount to our bank account</li>
                                <li>Include your application number in the reference</li>
                                <li>Upload the transfer receipt after payment</li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <label for="bank_receipt" class="form-label">Upload Transfer Receipt</label>
                            <input type="file" class="form-control" id="bank_receipt" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    
                    <!-- Cash Payment Form -->
                    <div id="cashForm" class="payment-method-form" style="display: none;">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Cash Payment:</strong> Please visit our office to make cash payment. Bring your application number and a valid ID.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-credit-card me-2"></i>Pay <?php echo formatCurrency($finalAmount); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Payment Completed -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-check-circle me-2"></i>Payment Completed
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Payment Successful!</strong> Your application fee has been paid. You can now track your application status.
                </div>
                <p><strong>Total Paid:</strong> <?php echo formatCurrency($totalPaid); ?></p>
                <a href="/student/applications.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Applications
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Payment History -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Payment History
                </h5>
            </div>
            <div class="card-body">
                <?php
                $payments = $paymentModel->getByApplication($applicationId);
                if (!empty($payments)):
                ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($payments as $payment): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></h6>
                                    <small class="text-muted"><?php echo formatDateTime($payment['created_at']); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $payment['payment_status'] === 'completed' ? 'success' : ($payment['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                    <div class="fw-bold"><?php echo formatCurrency($payment['amount']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No payment history available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help & Support -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-question-circle me-2"></i>Need Help?
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">Having trouble with payment? We're here to help!</p>
                <div class="d-grid gap-2">
                    <a href="mailto:support@university.edu" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-envelope me-2"></i>Email Support
                    </a>
                    <a href="tel:+1234567890" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-telephone me-2"></i>Call Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Generate transaction ID
document.getElementById('transaction_id').value = 'TXN' + Date.now() + Math.random().toString(36).substr(2, 5).toUpperCase();

// Payment method toggle
document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Hide all payment forms
        document.querySelectorAll('.payment-method-form').forEach(function(form) {
            form.style.display = 'none';
        });
        
        // Show selected payment form
        const selectedForm = document.getElementById(this.value + 'Form');
        if (selectedForm) {
            selectedForm.style.display = 'block';
        }
    });
});

// Card number formatting
document.getElementById('card_number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Expiry date formatting
document.getElementById('expiry_date').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    e.target.value = value;
});

// CVV formatting
document.getElementById('cvv').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// Payment gateway selection handler
document.getElementById('gateway_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const processingFee = parseFloat(selectedOption.dataset.processingFee) || 0;
    const fixedFee = parseFloat(selectedOption.dataset.fixedFee) || 0;
    const gatewayType = selectedOption.dataset.gatewayType;
    
    // Calculate and display processing fee
    const baseAmount = <?php echo $finalAmount; ?>;
    let totalFee = 0;
    
    if (processingFee > 0) {
        totalFee += (baseAmount * processingFee) / 100;
    }
    if (fixedFee > 0) {
        totalFee += fixedFee;
    }
    
    const totalAmount = baseAmount + totalFee;
    
    // Update the amount field
    document.querySelector('input[name="amount"]').value = totalAmount.toFixed(2);
    
    // Update the pay button
    const payButton = document.querySelector('button[type="submit"]');
    if (totalFee > 0) {
        payButton.innerHTML = `<i class="bi bi-credit-card me-2"></i>Pay $${totalAmount.toFixed(2)} (includes $${totalFee.toFixed(2)} processing fee)`;
    } else {
        payButton.innerHTML = `<i class="bi bi-credit-card me-2"></i>Pay $${totalAmount.toFixed(2)}`;
    }
    
    // Show/hide payment forms based on gateway type
    const creditCardForm = document.getElementById('creditCardForm');
    const bankTransferForm = document.getElementById('bankTransferForm');
    const cashForm = document.getElementById('cashForm');
    
    // Hide all forms first
    creditCardForm.style.display = 'none';
    bankTransferForm.style.display = 'none';
    cashForm.style.display = 'none';
    
    // Show appropriate form based on gateway type
    if (['stripe', 'paystack', 'flutterwave', 'razorpay'].includes(gatewayType)) {
        creditCardForm.style.display = 'block';
    } else if (gatewayType === 'paypal') {
        // PayPal will redirect to their site
        creditCardForm.style.display = 'none';
    } else if (gatewayType === 'hubtel') {
        // Hubtel supports mobile money and cards
        creditCardForm.style.display = 'block';
    }
});

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const gatewayId = document.getElementById('gateway_id').value;
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (!gatewayId) {
        e.preventDefault();
        alert('Please select a payment gateway.');
        return;
    }
    
    if (paymentMethod === 'credit_card') {
        const selectedOption = document.getElementById('gateway_id').options[document.getElementById('gateway_id').selectedIndex];
        const gatewayType = selectedOption.dataset.gatewayType;
        
        // Only validate card details for gateways that require them
        if (['stripe', 'paystack', 'flutterwave', 'razorpay'].includes(gatewayType)) {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const expiryDate = document.getElementById('expiry_date').value;
            const cvv = document.getElementById('cvv').value;
            const cardholderName = document.getElementById('cardholder_name').value;
            
            if (!cardNumber || cardNumber.length < 13) {
                e.preventDefault();
                alert('Please enter a valid card number.');
                return;
            }
            
            if (!expiryDate || expiryDate.length !== 5) {
                e.preventDefault();
                alert('Please enter a valid expiry date (MM/YY).');
                return;
            }
            
            if (!cvv || cvv.length < 3) {
                e.preventDefault();
                alert('Please enter a valid CVV.');
                return;
            }
            
            if (!cardholderName.trim()) {
                e.preventDefault();
                alert('Please enter the cardholder name.');
                return;
            }
        }
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
    submitBtn.disabled = true;
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Trigger gateway change event to set initial values
    const gatewaySelect = document.getElementById('gateway_id');
    if (gatewaySelect.value) {
        gatewaySelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include '../includes/footer.php'; ?>
