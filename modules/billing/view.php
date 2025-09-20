<?php
/**
 * View Bill - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Get bill ID
$billId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($billId <= 0) {
    redirectWithMessage('index.php', 'Invalid bill ID.', 'error');
}

$bill = null;
$patient = null;
$admission = null;
$payments = [];

try {
    $connection = getDBConnection();
    
    // Get bill details with patient information
    $query = "SELECT b.*, p.name as patient_name, p.patient_id, p.phone, p.email, p.address,
                     a.admission_date, a.discharge_date, a.reason as admission_reason,
                     d.name as doctor_name, d.specialization
              FROM bills b
              JOIN patients p ON b.patient_id = p.id
              LEFT JOIN admissions a ON b.admission_id = a.id
              LEFT JOIN doctors d ON a.doctor_id = d.id
              WHERE b.id = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param('i', $billId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        redirectWithMessage('index.php', 'Bill not found.', 'error');
    }
    
    $bill = $result->fetch_assoc();
    $stmt->close();
    
    // Get payment history for this bill
    $paymentQuery = "SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC, created_at DESC";
    $paymentStmt = $connection->prepare($paymentQuery);
    $paymentStmt->bind_param('i', $billId);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    
    while ($payment = $paymentResult->fetch_assoc()) {
        $payments[] = $payment;
    }
    $paymentStmt->close();
    
    // Calculate payment summary
    $totalPaid = array_sum(array_column($payments, 'amount'));
    $pendingAmount = $bill['total_amount'] - $totalPaid;
    
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Bill view error: " . $e->getMessage());
    redirectWithMessage('index.php', 'Error loading bill details.', 'error');
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
                        <h1 class="h3 mb-0">Bill Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Billing</a></li>
                                <li class="breadcrumb-item active">Bill #<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <?php if ($bill['status'] != 'Paid'): ?>
                            <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-success">
                                <i class="fas fa-money-bill"></i> Add Payment
                            </a>
                        <?php endif; ?>
                        <a href="print.php?id=<?php echo $bill['id']; ?>" class="btn btn-info ml-2" target="_blank">
                            <i class="fas fa-print"></i> Print Bill
                        </a>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Bill Details -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-invoice-dollar"></i> 
                                        Bill #<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </h5>
                                    <div>
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
                                        <span class="badge <?php echo $statusClass; ?> badge-lg">
                                            <?php echo $bill['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Patient Information -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Patient Information</h6>
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td><strong>Name:</strong></td>
                                                <td><?php echo htmlspecialchars($bill['patient_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Patient ID:</strong></td>
                                                <td><?php echo htmlspecialchars($bill['patient_id']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td><?php echo htmlspecialchars($bill['phone']); ?></td>
                                            </tr>
                                            <?php if (!empty($bill['email'])): ?>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?php echo htmlspecialchars($bill['email']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Bill Information</h6>
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td><strong>Bill Date:</strong></td>
                                                <td><?php echo formatDate($bill['bill_date']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Generated:</strong></td>
                                                <td><?php echo formatDate($bill['created_at'], 'd M Y H:i'); ?></td>
                                            </tr>
                                            <?php if (!empty($bill['admission_date'])): ?>
                                            <tr>
                                                <td><strong>Admission:</strong></td>
                                                <td><?php echo formatDate($bill['admission_date']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($bill['doctor_name'])): ?>
                                            <tr>
                                                <td><strong>Doctor:</strong></td>
                                                <td><?php echo htmlspecialchars($bill['doctor_name']); ?>
                                                    <?php if (!empty($bill['specialization'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($bill['specialization']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>

                                <!-- Bill Breakdown -->
                                <h6>Bill Breakdown</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Description</th>
                                                <th class="text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($bill['doctor_fee'] > 0): ?>
                                            <tr>
                                                <td>Doctor Consultation Fee</td>
                                                <td class="text-right"><?php echo formatCurrency($bill['doctor_fee']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['room_charges'] > 0): ?>
                                            <tr>
                                                <td>Room Charges</td>
                                                <td class="text-right"><?php echo formatCurrency($bill['room_charges']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['medicine_charges'] > 0): ?>
                                            <tr>
                                                <td>Medicine Charges</td>
                                                <td class="text-right"><?php echo formatCurrency($bill['medicine_charges']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['other_charges'] > 0): ?>
                                            <tr>
                                                <td>Other Charges</td>
                                                <td class="text-right"><?php echo formatCurrency($bill['other_charges']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="thead-light">
                                            <tr>
                                                <th>Total Amount</th>
                                                <th class="text-right"><?php echo formatCurrency($bill['total_amount']); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Payment Summary -->
                                <div class="row mt-4">
                                    <div class="col-md-6 offset-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Payment Summary</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <tr>
                                                        <td><strong>Total Amount:</strong></td>
                                                        <td class="text-right"><strong><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Amount Paid:</td>
                                                        <td class="text-right text-success"><?php echo formatCurrency($totalPaid); ?></td>
                                                    </tr>
                                                    <tr class="border-top">
                                                        <td><strong>Pending Amount:</strong></td>
                                                        <td class="text-right">
                                                            <strong class="<?php echo $pendingAmount > 0 ? 'text-danger' : 'text-success'; ?>">
                                                                <?php echo formatCurrency($pendingAmount); ?>
                                                            </strong>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Payment History -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Payment History
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($payments)): ?>
                                    <div class="payment-history">
                                        <?php foreach ($payments as $payment): ?>
                                            <div class="payment-item mb-3 p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo formatCurrency($payment['amount']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo formatDate($payment['payment_date']); ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge badge-success">
                                                        <?php echo htmlspecialchars($payment['payment_method']); ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!empty($payment['transaction_id'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($payment['notes'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-money-bill fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No payments recorded yet</p>
                                        <?php if ($bill['status'] != 'Paid'): ?>
                                            <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary mt-2">
                                                Add First Payment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-bolt"></i> Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($bill['status'] != 'Paid'): ?>
                                        <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-money-bill"></i> Add Payment
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="print.php?id=<?php echo $bill['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="fas fa-print"></i> Print Bill
                                    </a>
                                    
                                    <a href="../patients/view.php?id=<?php echo $bill['patient_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-user"></i> View Patient
                                    </a>
                                    
                                    <a href="history.php?patient_id=<?php echo $bill['patient_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-history"></i> Patient Bills
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-lg {
    font-size: 0.9em;
    padding: 0.5em 0.75em;
}

.payment-item {
    background-color: #f8f9fa;
}

.payment-item:last-child {
    margin-bottom: 0 !important;
}
</style>

<?php include_once '../../includes/footer.php'; ?>