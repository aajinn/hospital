<?php
/**
 * View Patient Details
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage('index.php', 'Invalid patient ID.', 'error');
}

$patientId = (int)$_GET['id'];
$patient = null;
$admissions = [];
$bills = [];

try {
    // Get patient details
    $patient = getRecordById('patients', $patientId);
    
    if (!$patient) {
        redirectWithMessage('index.php', 'Patient not found.', 'error');
    }
    
    // Get patient admissions
    $connection = getDBConnection();
    
    $admissionQuery = "SELECT a.*, d.name as doctor_name, d.specialization 
                      FROM admissions a 
                      LEFT JOIN doctors d ON a.doctor_id = d.id 
                      WHERE a.patient_id = ? 
                      ORDER BY a.admission_date DESC";
    $admissionStmt = $connection->prepare($admissionQuery);
    $admissionStmt->bind_param('i', $patientId);
    $admissionStmt->execute();
    $admissionResult = $admissionStmt->get_result();
    
    while ($row = $admissionResult->fetch_assoc()) {
        $admissions[] = $row;
    }
    $admissionStmt->close();
    
    // Get patient bills
    $billQuery = "SELECT b.*, 
                         COALESCE(SUM(p.amount), 0) as paid_amount,
                         (b.total_amount - COALESCE(SUM(p.amount), 0)) as pending_amount
                  FROM bills b 
                  LEFT JOIN payments p ON b.id = p.bill_id 
                  WHERE b.patient_id = ? 
                  GROUP BY b.id 
                  ORDER BY b.bill_date DESC";
    $billStmt = $connection->prepare($billQuery);
    $billStmt->bind_param('i', $patientId);
    $billStmt->execute();
    $billResult = $billStmt->get_result();
    
    while ($row = $billResult->fetch_assoc()) {
        $bills[] = $row;
    }
    $billStmt->close();
    
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Patient view error: " . $e->getMessage());
    redirectWithMessage('index.php', 'An error occurred while loading patient details.', 'error');
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
                        <h1 class="h3 mb-0">Patient Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Patients</a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($patient['name']); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="assignment-history.php?id=<?php echo $patient['id']; ?>" class="btn btn-info">
                            <i class="fas fa-history"></i> Assignment History
                        </a>
                        <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning ml-2">
                            <i class="fas fa-edit"></i> Edit Patient
                        </a>
                        <a href="admit.php?id=<?php echo $patient['id']; ?>" class="btn btn-success ml-2">
                            <i class="fas fa-hospital"></i> Admit Patient
                        </a>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <div class="row">
                    <!-- Patient Information -->
                    <div class="col-md-8">
                        <div class="card patient-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user"></i> Patient Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless patient-info">
                                            <tr>
                                                <td><strong>Patient ID:</strong></td>
                                                <td><span class="patient-id"><?php echo htmlspecialchars($patient['patient_id']); ?></span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Full Name:</strong></td>
                                                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Age:</strong></td>
                                                <td><?php echo htmlspecialchars($patient['age']); ?> years</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Gender:</strong></td>
                                                <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td>
                                                    <i class="fas fa-phone"></i> 
                                                    <a href="tel:<?php echo htmlspecialchars($patient['phone']); ?>">
                                                        <?php echo htmlspecialchars($patient['phone']); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless patient-info">
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td>
                                                    <?php if (!empty($patient['email'])): ?>
                                                        <i class="fas fa-envelope"></i> 
                                                        <a href="mailto:<?php echo htmlspecialchars($patient['email']); ?>">
                                                            <?php echo htmlspecialchars($patient['email']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Emergency Contact:</strong></td>
                                                <td>
                                                    <?php if (!empty($patient['emergency_contact'])): ?>
                                                        <i class="fas fa-phone"></i> 
                                                        <a href="tel:<?php echo htmlspecialchars($patient['emergency_contact']); ?>">
                                                            <?php echo htmlspecialchars($patient['emergency_contact']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Registered:</strong></td>
                                                <td><?php echo formatDate($patient['created_at'], 'd M Y, h:i A'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Last Updated:</strong></td>
                                                <td><?php echo formatDate($patient['updated_at'], 'd M Y, h:i A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($patient['address'])): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6><strong>Address:</strong></h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($patient['address'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['medical_history'])): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6><strong>Medical History:</strong></h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar"></i> Quick Stats
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?php echo count($admissions); ?></h4>
                                        <small class="text-muted">Total Admissions</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-info"><?php echo count($bills); ?></h4>
                                        <small class="text-muted">Total Bills</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <?php 
                                        $activeAdmissions = array_filter($admissions, function($a) { 
                                            return $a['status'] == 'Admitted'; 
                                        });
                                        ?>
                                        <h4 class="text-success"><?php echo count($activeAdmissions); ?></h4>
                                        <small class="text-muted">Active Admissions</small>
                                    </div>
                                    <div class="col-6">
                                        <?php 
                                        $pendingBills = array_filter($bills, function($b) { 
                                            return $b['status'] == 'Pending' || $b['status'] == 'Partial'; 
                                        });
                                        ?>
                                        <h4 class="text-warning"><?php echo count($pendingBills); ?></h4>
                                        <small class="text-muted">Pending Bills</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admission History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-hospital"></i> Admission History
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($admissions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Admission Date</th>
                                                    <th>Doctor</th>
                                                    <th>Reason</th>
                                                    <th>Discharge Date</th>
                                                    <th>Room Charges</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admissions as $admission): ?>
                                                    <tr>
                                                        <td><?php echo formatDate($admission['admission_date']); ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($admission['doctor_name'] ?? 'N/A'); ?></strong>
                                                            <?php if (!empty($admission['specialization'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($admission['specialization']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($admission['reason'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <?php if (!empty($admission['discharge_date'])): ?>
                                                                <?php echo formatDate($admission['discharge_date']); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo formatCurrency($admission['room_charges']); ?></td>
                                                        <td>
                                                            <?php if ($admission['status'] == 'Admitted'): ?>
                                                                <span class="badge badge-success">Admitted</span>
                                                                <br><small>
                                                                    <a href="discharge.php?admission_id=<?php echo $admission['id']; ?>" 
                                                                       class="btn btn-sm btn-outline-danger mt-1">
                                                                        <i class="fas fa-sign-out-alt"></i> Discharge
                                                                    </a>
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary">Discharged</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-hospital fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No admission history found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-invoice-dollar"></i> Billing History
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($bills)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Bill Date</th>
                                                    <th>Total Amount</th>
                                                    <th>Paid Amount</th>
                                                    <th>Pending Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bills as $bill): ?>
                                                    <tr>
                                                        <td><?php echo formatDate($bill['bill_date']); ?></td>
                                                        <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                                        <td><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                                        <td><?php echo formatCurrency($bill['pending_amount']); ?></td>
                                                        <td>
                                                            <?php if ($bill['status'] == 'Paid'): ?>
                                                                <span class="badge badge-success">Paid</span>
                                                            <?php elseif ($bill['status'] == 'Partial'): ?>
                                                                <span class="badge badge-warning">Partial</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-danger">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="../billing/view.php?id=<?php echo $bill['id']; ?>" 
                                                               class="btn btn-sm btn-info" title="View Bill">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-invoice-dollar fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No billing history found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>