<?php
/**
 * Add Payment - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Get bill ID
$billId = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if ($billId <= 0) {
    redirectWithMessage('index.php', 'Invalid bill ID.', 'error');
}

$bill = null;
$errors = [];

// Get bill details
try {
    $bill = getBillDetails($billId);
    
    if (!$bill) {
        redirectWithMessage('index.php', 'Bill not found.', 'error');
    }
    
    // Check if bill is already fully paid
    if ($bill['status'] == 'Paid') {
        redirectWithMessage('view.php?id=' . $billId, 'This bill is already fully paid.', 'warning');
    }
    
} catch (Exception $e) {
    error_log("Payment page error: " . $e->getMessage());
    redirectWithMessage('index.php', 'Error loading bill details.', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $requiredFields = [
            'amount' => $_POST['amount'] ?? '',
            'payment_date' => $_POST['payment_date'] ?? '',
            'payment_method' => $_POST['payment_method'] ?? ''
        ];
        
        $validationErrors = validateRequired([
            'amount' => $requiredFields['amount'],
            'payment_date' => $requiredFields['payment_date'],
            'payment_method' => $requiredFields['payment_method']
        ]);
        
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }
        
        // Validate amount
        if (!empty($requiredFields['amount'])) {
            if (!validateNumeric($requiredFields['amount'], 0.01)) {
                $errors[] = "Payment amount must be greater than 0.";
            } else {
                $paymentAmount = (float)$requiredFields['amount'];
                $pendingAmount = $bill['pending_amount'];
                
                if ($paymentAmount > $pendingAmount) {
                    $errors[] = "Payment amount cannot exceed pending amount of " . formatCurrency($pendingAmount) . ".";
                }
            }
        }
        
        // Validate date
        if (!empty($requiredFields['payment_date']) && !validateDate($requiredFields['payment_date'])) {
            $errors[] = "Please enter a valid payment date.";
        }
        
        // Validate payment method
        $validMethods = ['Cash', 'Card', 'UPI', 'Bank Transfer', 'Cheque'];
        if (!empty($requiredFields['payment_method']) && !in_array($requiredFields['payment_method'], $validMethods)) {
            $errors[] = "Please select a valid payment method.";
        }
        
        if (empty($errors)) {
            $connection = getDBConnection();
            
            // Prepare payment data
            $paymentData = [
                'bill_id' => $billId,
                'payment_date' => $requiredFields['payment_date'],
                'amount' => $paymentAmount,
                'payment_method' => $requiredFields['payment_method'],
                'transaction_id' => !empty($_POST['transaction_id']) ? trim($_POST['transaction_id']) : null,
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null
            ];
            
            // Insert payment
            $paymentId = insertRecord('payments', $paymentData);
            
            if ($paymentId) {
                // Update bill status
                updateBillStatus($billId);
                
                logActivity('Payment Added', "Payment ID: $paymentId for Bill ID: $billId, Amount: " . formatCurrency($paymentAmount));
                redirectWithMessage('view.php?id=' . $billId, 'Payment added successfully!', 'success');
            } else {
                $errors[] = "Failed to add payment. Please try again.";
            }
            
            closeDBConnection($connection);
        }
    } catch (Exception $e) {
        error_log("Payment processing error: " . $e->getMessage());
        $errors[] = "An error occurred while processing the payment.";
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <?php include_once '../../includes/sidebar.php'; ?>
        </div>
        
        <!-- Main content -->
        <div class="col-md-10 main-content">
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Add Payment</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Billing</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $bill['id']; ?>">Bill #<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></a></li>
                                <li class="breadcrumb-item active">Add Payment</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $bill['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bill
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Payment Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-money-bill"></i> Payment Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="paymentForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="amount">Payment Amount <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="number" class="form-control" id="amount" name="amount" 
                                                           value="<?php echo $_POST['amount'] ?? $bill['pending_amount']; ?>" 
                                                           min="0.01" max="<?php echo $bill['pending_amount']; ?>" 
                                                           step="0.01" required>
                                                </div>
                                                <small class="form-text text-muted">
                                                    Maximum: <?php echo formatCurrency($bill['pending_amount']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                                       value="<?php echo $_POST['payment_date'] ?? date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                                <select class="form-control" id="payment_method" name="payment_method" required>
                                                    <option value="">Select Method...</option>
                                                    <option value="Cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                                    <option value="Card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Card') ? 'selected' : ''; ?>>Card</option>
                                                    <option value="UPI" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                                    <option value="Bank Transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                                    <option value="Cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="transaction_id">Transaction ID / Reference</label>
                                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                                       value="<?php echo $_POST['transaction_id'] ?? ''; ?>" 
                                                       placeholder="Optional reference number">
                                                <small class="form-text text-muted">
                                                    For card, UPI, or bank transfer payments
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Optional payment notes..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                                    </div>

                                    <hr>

                                    <!-- Payment Summary -->
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">Payment Summary</h6>
                                                    <table class="table table-sm table-borderless mb-0">
                                                        <tr>
                                                            <td>Current Payment:</td>
                                                            <td class="text-right" id="current-payment">₹0.00</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Remaining Balance:</td>
                                                            <td class="text-right" id="remaining-balance"><?php echo formatCurrency($bill['pending_amount']); ?></td>
                                                        </tr>
                                                        <tr class="border-top">
                                                            <td><strong>New Status:</strong></td>
                                                            <td class="text-right">
                                                                <span id="new-status" class="badge badge-warning">Partial</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group mt-3">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Add Payment
                                        </button>
                                        <a href="view.php?id=<?php echo $bill['id']; ?>" class="btn btn-secondary ml-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Bill Summary -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-file-invoice"></i> Bill Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Bill ID:</strong></td>
                                        <td>#<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Patient:</strong></td>
                                        <td><?php echo htmlspecialchars($bill['patient_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Bill Date:</strong></td>
                                        <td><?php echo formatDate($bill['bill_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Amount:</strong></td>
                                        <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Paid Amount:</strong></td>
                                        <td class="text-success"><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Pending Amount:</strong></td>
                                        <td class="text-danger"><strong><?php echo formatCurrency($bill['pending_amount']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current Status:</strong></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($bill['status']) {
                                                case 'Paid':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'Partial':
                                                    $statusClass = 'badge-warning';
                                                    break;
                                                case 'Pending':
                                                    $statusClass = 'badge-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo $bill['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Quick Payment Options -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-bolt"></i> Quick Payment
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPaymentAmount(<?php echo $bill['pending_amount']; ?>)">
                                        Pay Full Amount (<?php echo formatCurrency($bill['pending_amount']); ?>)
                                    </button>
                                    
                                    <?php if ($bill['pending_amount'] >= 1000): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(1000)">
                                            Pay ₹1,000
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($bill['pending_amount'] >= 500): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(500)">
                                            Pay ₹500
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(<?php echo round($bill['pending_amount'] / 2, 2); ?>)">
                                        Pay Half (<?php echo formatCurrency(round($bill['pending_amount'] / 2, 2)); ?>)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var pendingAmount = <?php echo $bill['pending_amount']; ?>;
    
    // Update payment summary when amount changes
    $('#amount').on('input', function() {
        updatePaymentSummary();
    });
    
    function updatePaymentSummary() {
        var paymentAmount = parseFloat($('#amount').val()) || 0;
        var remainingBalance = pendingAmount - paymentAmount;
        
        // Update display
        $('#current-payment').text('₹' + paymentAmount.toFixed(2));
        $('#remaining-balance').text('₹' + remainingBalance.toFixed(2));
        
        // Update status badge
        var statusBadge = $('#new-status');
        if (remainingBalance <= 0) {
            statusBadge.removeClass('badge-warning badge-danger').addClass('badge-success').text('Paid');
        } else if (paymentAmount > 0) {
            statusBadge.removeClass('badge-success badge-danger').addClass('badge-warning').text('Partial');
        } else {
            statusBadge.removeClass('badge-success badge-warning').addClass('badge-danger').text('Pending');
        }
    }
    
    // Set payment amount function
    window.setPaymentAmount = function(amount) {
        $('#amount').val(amount.toFixed(2));
        updatePaymentSummary();
    };
    
    // Show/hide transaction ID field based on payment method
    $('#payment_method').on('change', function() {
        var method = $(this).val();
        var transactionField = $('#transaction_id').closest('.form-group');
        
        if (method === 'Cash') {
            transactionField.hide();
            $('#transaction_id').val('');
        } else {
            transactionField.show();
        }
    });
    
    // Initial update
    updatePaymentSummary();
    
    // Trigger payment method change to set initial state
    $('#payment_method').trigger('change');
    
    // Form validation
    $('#paymentForm').on('submit', function(e) {
        var amount = parseFloat($('#amount').val()) || 0;
        
        if (amount <= 0) {
            e.preventDefault();
            alert('Please enter a valid payment amount.');
            return false;
        }
        
        if (amount > pendingAmount) {
            e.preventDefault();
            alert('Payment amount cannot exceed pending amount of ₹' + pendingAmount.toFixed(2));
            return false;
        }
        
        return true;
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>