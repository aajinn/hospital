<?php
/**
 * Patient Billing History - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Get patient ID
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patientId <= 0) {
    redirectWithMessage('index.php', 'Invalid patient ID.', 'error');
}

$patient = null;
$bills = [];
$totalBilled = 0;
$totalPaid = 0;
$totalPending = 0;

try {
    $connection = getDBConnection();
    
    // Get patient details
    $patientQuery = "SELECT * FROM patients WHERE id = ?";
    $patientStmt = $connection->prepare($patientQuery);
    $patientStmt->bind_param('i', $patientId);
    $patientStmt->execute();
    $patientResult = $patientStmt->get_result();
    
    if ($patientResult->num_rows == 0) {
        redirectWithMessage('index.php', 'Patient not found.', 'error');
    }
    
    $patient = $patientResult->fetch_assoc();
    $patientStmt->close();
    
    // Get billing history
    $bills = getPatientBillingHistory($patientId);
    
    // Calculate totals
    foreach ($bills as $bill) {
        $totalBilled += $bill['total_amount'];
        $totalPaid += $bill['paid_amount'];
        $totalPending += $bill['pending_amount'];
    }
    
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Billing history error: " . $e->getMessage());
    redirectWithMessage('index.php', 'Error loading billing history.', 'error');
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
                        <h1 class="h3 mb-0">Patient Billing History</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Billing</a></li>
                                <li class="breadcrumb-item active">Patient History</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="generate.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Generate New Bill
                        </a>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Patient Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user"></i> Patient Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Patient ID:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo !empty($patient['email']) ? htmlspecialchars($patient['email']) : '-'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h4 class="text-primary"><?php echo formatCurrency($totalBilled); ?></h4>
                                            <small class="text-muted">Total Billed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h4 class="text-success"><?php echo formatCurrency($totalPaid); ?></h4>
                                            <small class="text-muted">Total Paid</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h4 class="<?php echo $totalPending > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatCurrency($totalPending); ?>
                                            </h4>
                                            <small class="text-muted">Total Pending</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history"></i> Billing History (<?php echo count($bills); ?> bills)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($bills)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bill ID</th>
                                            <th>Bill Date</th>
                                            <th>Components</th>
                                            <th>Total Amount</th>
                                            <th>Paid Amount</th>
                                            <th>Pending</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td>
                                                    <span class="bill-id">#<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($bill['bill_date']); ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php
                                                        $components = [];
                                                        if ($bill['doctor_fee'] > 0) $components[] = 'Doctor: ' . formatCurrency($bill['doctor_fee']);
                                                        if ($bill['room_charges'] > 0) $components[] = 'Room: ' . formatCurrency($bill['room_charges']);
                                                        if ($bill['medicine_charges'] > 0) $components[] = 'Medicine: ' . formatCurrency($bill['medicine_charges']);
                                                        if ($bill['other_charges'] > 0) $components[] = 'Other: ' . formatCurrency($bill['other_charges']);
                                                        echo implode('<br>', $components);
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($bill['total_amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-success"><?php echo formatCurrency($bill['paid_amount']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($bill['pending_amount'] > 0): ?>
                                                        <span class="text-danger"><?php echo formatCurrency($bill['pending_amount']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success">₹0.00</span>
                                                    <?php endif; ?>
                                                </td>
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
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="view.php?id=<?php echo $bill['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="View Bill">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($bill['status'] != 'Paid'): ?>
                                                            <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" 
                                                               class="btn btn-sm btn-success" title="Add Payment">
                                                                <i class="fas fa-money-bill"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="print.php?id=<?php echo $bill['id']; ?>" 
                                                           class="btn btn-sm btn-secondary" title="Print Bill" target="_blank">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Bills Found</h5>
                                <p class="text-muted">No bills have been generated for this patient yet.</p>
                                <a href="generate.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                    Generate First Bill
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>