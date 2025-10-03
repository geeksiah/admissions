<?php
// Get payment history for current student
$payments = [];

// Initialize currency helper
require_once '../classes/Currency.php';
$currency = new Currency($systemConfig);
if (!empty($student['id'])) {
    // This would typically come from a Payment model
    // For now, we'll create sample data
    $payments = [
        [
            'id' => 1,
            'amount' => 150.00,
            'currency' => 'USD',
            'description' => 'Application Fee - Computer Science',
            'status' => 'completed',
            'payment_method' => 'Credit Card',
            'transaction_id' => 'TXN123456789',
            'created_at' => '2024-01-15 10:30:00'
        ],
        [
            'id' => 2,
            'amount' => 75.00,
            'currency' => 'USD',
            'description' => 'Processing Fee',
            'status' => 'completed',
            'payment_method' => 'Bank Transfer',
            'transaction_id' => 'TXN987654321',
            'created_at' => '2024-01-10 14:20:00'
        ]
    ];
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <i class="bi bi-credit-card me-2"></i>
            <h5 class="mb-0">Payment History</h5>
        </div>
        <button class="btn btn-primary btn-sm">
            <i class="bi bi-plus me-1"></i>Make Payment
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-credit-card text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3 text-muted">No Payment History</h5>
                <p class="text-muted">Your payment transactions will appear here.</p>
                <button class="btn btn-primary">
                    <i class="bi bi-plus me-1"></i>Make First Payment
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                <td>
                                    <strong><?php echo $currency->format($payment['amount'], $payment['currency']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo ucfirst($payment['status']); ?></span>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($payment['transaction_id']); ?></code>
                                </td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" onclick="downloadReceipt(<?php echo $payment['id']; ?>)">
                                        <i class="bi bi-download me-1"></i>Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadReceipt(paymentId) {
    // Create a simple receipt PDF download
    const receiptData = {
        paymentId: paymentId,
        studentName: '<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>',
        date: new Date().toLocaleDateString(),
        amount: '$150.00',
        description: 'Application Fee'
    };
    
    // For now, show an alert. In production, this would generate and download a PDF
    alert('Receipt for Payment ID: ' + paymentId + '\n\nThis would normally download a PDF receipt.');
    
    // In production, you would:
    // 1. Make an AJAX call to generate PDF
    // 2. Return the PDF file for download
    // fetch('/api/generate-receipt.php', {
    //     method: 'POST',
    //     body: JSON.stringify(receiptData)
    // })
    // .then(response => response.blob())
    // .then(blob => {
    //     const url = window.URL.createObjectURL(blob);
    //     const a = document.createElement('a');
    //     a.href = url;
    //     a.download = 'receipt-' + paymentId + '.pdf';
    //     a.click();
    // });
}
</script>
