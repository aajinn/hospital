<?php
/**
 * View Doctor Profile
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$doctor = null;
$doctorId = 0;
$admissions = [];
$totalPatients = 0;
$activeAdmissions = 0;

// Get doctor ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $doctorId = (int)$_GET['id'];
    $doctor = getRecordById('doctors', $doctorId);
    
    if (!$doctor) {
        redirectWithMessage('index.php', 'Doctor not found.', 'error');
    }
} else {
    redirectWithMessage('index.php', 'Invalid doctor ID.', 'error');
}

// Get doctor's admission statistics
try {
    $connection = getDBConnection();
    
    // Get total patients treated by this doctor
    $totalQuery = "SELECT COUNT(DISTINCT patient_id) as total FROM admissions WHERE doctor_id = ?";
    $totalStmt = $connection->prepare($totalQuery);
    $totalStmt->bind_param('i', $doctorId);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalPatients = $totalResult->fetch_assoc()['total'];
    $totalStmt->close();
    
    // Get active admissions count
    $activeQuery = "SELECT COUNT(*) as active FROM admissions WHERE doctor_id = ? AND status = 'Admitted'";
    $activeStmt = $connection->prepare($activeQuery);
    $activeStmt->bind_param('i', $doctorId);
    $activeStmt->execute();
    $activeResult = $activeStmt->get_result();
    $activeAdmissions = $activeResult->fetch_assoc()['active'];
    $activeStmt->close();
    
    // Get recent admissions
    $admissionsQuery = "SELECT a.*, p.name as patient_name, p.patient_id, p.phone as patient_phone 
                       FROM admissions a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? 
                       ORDER BY a.admission_date DESC 
                       LIMIT 10";
    $admissionsStmt = $connection->prepare($admissionsQuery);
    $admissionsStmt->bind_param('i', $doctorId);
    $admissionsStmt->execute();
    $admissionsResult = $admissionsStmt->get_result();
    
    while ($row = $admissionsResult->fetch_assoc()) {
        $admissions[] = $row;
    }
    
    $admissionsStmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Doctor statistics error: " . $e->getMessage());
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
                        <h1 class="h3 mb-0">Doctor Profile</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Doctors</a></li>
                                <li class="breadcrumb-item active">Dr. <?php echo htmlspecialchars($doctor['name']); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="edit.php?id=<?php echo $doctorId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
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
                    <!-- Doctor Information Card -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-md"></i> Doctor Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="doctor-avatar text-center mb-4">
                                            <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center">
                                                <i class="fas fa-user-md fa-2x"></i>
                                            </div>
                                            <h4 class="mt-2 mb-0"><?php echo htmlspecialchars($doctor['name']); ?></h4>
                                            <p class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Specialization:</strong></td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td>
                                                    <i class="fas fa-phone text-muted mr-1"></i>
                                                    <?php echo htmlspecialchars($doctor['phone']); ?>
                                                </td>
                                            </tr>
                                            <?php if (!empty($doctor['email'])): ?>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td>
                                                    <i class="fas fa-envelope text-muted mr-1"></i>
                                                    <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>">
                                                        <?php echo htmlspecialchars($doctor['email']); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td><strong>Consultation Fee:</strong></td>
                                                <td>
                                                    <?php if ($doctor['consultation_fee'] > 0): ?>
                                                        <strong class="text-success"><?php echo formatCurrency($doctor['consultation_fee']); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Registered:</strong></td>
                                                <td>
                                                    <i class="fas fa-calendar text-muted mr-1"></i>
                                                    <?php echo formatDate($doctor['created_at'], 'd M Y'); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <?php if (!empty($doctor['schedule'])): ?>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h6 class="text-primary">Schedule & Availability</h6>
                                        <div class="bg-light p-3 rounded">
                                            <?php echo nl2br(htmlspecialchars($doctor['schedule'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar"></i> Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="stat-item mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0 text-primary"><?php echo $totalPatients; ?></h3>
                                            <small class="text-muted">Total Patients Treated</small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-users fa-2x text-primary"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="stat-item mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0 text-warning"><?php echo $activeAdmissions; ?></h3>
                                            <small class="text-muted">Active Admissions</small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-bed fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0 text-success"><?php echo count($admissions); ?></h3>
                                            <small class="text-muted">Recent Admissions</small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-history fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="assignments.php?id=<?php echo $doctorId; ?>" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-clipboard-list"></i> View All Assignments
                                    </a>
                                    <a href="../patients/admit.php?doctor_id=<?php echo $doctorId; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-plus"></i> Admit New Patient
                                    </a>
                                    <a href="edit.php?id=<?php echo $doctorId; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Edit Profile
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="confirmDelete(<?php echo $doctorId; ?>, '<?php echo htmlspecialchars($doctor['name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-trash"></i> Delete Doctor
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Admissions -->
                <?php if (!empty($admissions)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Recent Patient Admissions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Patient</th>
                                                <th>Admission Date</th>
                                                <th>Reason</th>
                                                <th>Status</th>
                                                <th>Discharge Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($admissions as $admission): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($admission['patient_name']); ?></strong>
                                                            <br><small class="text-muted">ID: <?php echo htmlspecialchars($admission['patient_id']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-calendar text-muted mr-1"></i>
                                                        <?php echo formatDate($admission['admission_date']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($admission['reason'])): ?>
                                                            <?php echo htmlspecialchars(substr($admission['reason'], 0, 50)); ?>
                                                            <?php if (strlen($admission['reason']) > 50): ?>...<?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($admission['status'] == 'Admitted'): ?>
                                                            <span class="badge badge-warning">Admitted</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">Discharged</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($admission['discharge_date']): ?>
                                                            <?php echo formatDate($admission['discharge_date']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="../patients/view.php?id=<?php echo $admission['patient_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View Patient">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete Dr. <strong id="doctorName"></strong>?</p>
                <p class="text-danger"><small><i class="fas fa-exclamation-triangle"></i> This action cannot be undone and will affect all related records.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete Doctor</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(doctorId, doctorName) {
    $('#doctorName').text(doctorName);
    $('#deleteConfirmBtn').attr('href', 'delete.php?id=' + doctorId);
    $('#deleteModal').modal('show');
}
</script>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
}

.stat-item {
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
}

.stat-icon {
    opacity: 0.3;
}

.doctor-avatar {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 20px;
    margin-bottom: 20px;
}
</style>

<?php include_once '../../includes/footer.php'; ?>