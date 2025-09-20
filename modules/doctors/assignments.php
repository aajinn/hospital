<?php
/**
 * Doctor Assignments and Workload Tracking
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$doctorId = 0;
$doctor = null;
$assignments = [];
$workloadStats = [];
$currentPage = 1;
$totalPages = 1;
$totalRecords = 0;
$statusFilter = '';
$dateFilter = '';

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

// Get filter parameters
if (isset($_GET['status'])) {
    $statusFilter = trim($_GET['status']);
}
if (isset($_GET['date_from'])) {
    $dateFilter = trim($_GET['date_from']);
}

// Get current page
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int)$_GET['page']);
}

try {
    $connection = getDBConnection();
    
    // Get doctor workload statistics
    $workloadQuery = "SELECT 
        COUNT(*) as total_assignments,
        COUNT(CASE WHEN status = 'Admitted' THEN 1 END) as active_assignments,
        COUNT(CASE WHEN status = 'Discharged' THEN 1 END) as completed_assignments,
        AVG(CASE WHEN discharge_date IS NOT NULL THEN DATEDIFF(discharge_date, admission_date) END) as avg_stay_days,
        COUNT(CASE WHEN admission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_assignments
    FROM admissions 
    WHERE doctor_id = ?";
    
    $workloadStmt = $connection->prepare($workloadQuery);
    $workloadStmt->bind_param('i', $doctorId);
    $workloadStmt->execute();
    $workloadResult = $workloadStmt->get_result();
    $workloadStats = $workloadResult->fetch_assoc();
    $workloadStmt->close();
    
    // Build assignment query with filters
    $whereConditions = ['a.doctor_id = ?'];
    $params = [$doctorId];
    $types = 'i';
    
    if (!empty($statusFilter)) {
        $whereConditions[] = 'a.status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }
    
    if (!empty($dateFilter)) {
        $whereConditions[] = 'a.admission_date >= ?';
        $params[] = $dateFilter;
        $types .= 's';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total 
                   FROM admissions a 
                   JOIN patients p ON a.patient_id = p.id 
                   $whereClause";
    $countStmt = $connection->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / RECORDS_PER_PAGE);
    $offset = ($currentPage - 1) * RECORDS_PER_PAGE;
    
    // Get assignments with pagination
    $assignmentQuery = "SELECT 
        a.id as admission_id,
        a.admission_date,
        a.discharge_date,
        a.reason,
        a.status,
        a.room_charges,
        p.id as patient_id,
        p.patient_id as patient_code,
        p.name as patient_name,
        p.age,
        p.gender,
        p.phone as patient_phone,
        CASE 
            WHEN a.discharge_date IS NOT NULL THEN DATEDIFF(a.discharge_date, a.admission_date)
            ELSE DATEDIFF(CURDATE(), a.admission_date)
        END as stay_days
    FROM admissions a 
    JOIN patients p ON a.patient_id = p.id 
    $whereClause 
    ORDER BY a.admission_date DESC 
    LIMIT ? OFFSET ?";
    
    // Add limit and offset parameters
    $params[] = RECORDS_PER_PAGE;
    $params[] = $offset;
    $types .= 'ii';
    
    $assignmentStmt = $connection->prepare($assignmentQuery);
    $assignmentStmt->bind_param($types, ...$params);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();
    
    while ($row = $assignmentResult->fetch_assoc()) {
        $assignments[] = $row;
    }
    
    $assignmentStmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Doctor assignments error: " . $e->getMessage());
    $assignments = [];
    $workloadStats = [];
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
                        <h1 class="h3 mb-0">Doctor Assignments & Workload</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Doctors</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $doctorId; ?>">Dr. <?php echo htmlspecialchars($doctor['name']); ?></a></li>
                                <li class="breadcrumb-item active">Assignments</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $doctorId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <!-- Doctor Info Header -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-md bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo htmlspecialchars($doctor['name']); ?></h4>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="badge badge-info">Total Assignments: <?php echo $workloadStats['total_assignments'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workload Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                                <h3 class="text-primary"><?php echo $workloadStats['total_assignments'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Total Assignments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-bed fa-2x text-warning"></i>
                                </div>
                                <h3 class="text-warning"><?php echo $workloadStats['active_assignments'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Active Patients</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                                <h3 class="text-success"><?php echo $workloadStats['completed_assignments'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-calendar fa-2x text-info"></i>
                                </div>
                                <h3 class="text-info"><?php echo round($workloadStats['avg_stay_days'] ?? 0, 1); ?></h3>
                                <p class="text-muted mb-0">Avg Stay (Days)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end">
                            <input type="hidden" name="id" value="<?php echo $doctorId; ?>">
                            <div class="col-md-3">
                                <label for="status">Status Filter</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="Admitted" <?php echo $statusFilter == 'Admitted' ? 'selected' : ''; ?>>Active (Admitted)</option>
                                    <option value="Discharged" <?php echo $statusFilter == 'Discharged' ? 'selected' : ''; ?>>Discharged</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($dateFilter); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="assignments.php?id=<?php echo $doctorId; ?>" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">
                                    Showing <?php echo count($assignments); ?> of <?php echo $totalRecords; ?> assignments
                                </small>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assignments List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Patient Assignments
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No assignments found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($statusFilter) || !empty($dateFilter)): ?>
                                        No assignments match your filter criteria. <a href="assignments.php?id=<?php echo $doctorId; ?>">View all assignments</a>
                                    <?php else: ?>
                                        This doctor has no patient assignments yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Patient</th>
                                            <th>Admission Date</th>
                                            <th>Status</th>
                                            <th>Stay Duration</th>
                                            <th>Reason</th>
                                            <th>Room Charges</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['patient_name']); ?></strong>
                                                        <br><small class="text-muted">
                                                            ID: <?php echo htmlspecialchars($assignment['patient_code']); ?> | 
                                                            <?php echo $assignment['age']; ?>Y, <?php echo $assignment['gender']; ?>
                                                        </small>
                                                        <?php if (!empty($assignment['patient_phone'])): ?>
                                                            <br><small class="text-muted">
                                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($assignment['patient_phone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <i class="fas fa-calendar text-muted mr-1"></i>
                                                    <?php echo formatDate($assignment['admission_date']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['status'] == 'Admitted'): ?>
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-bed"></i> Admitted
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-check"></i> Discharged
                                                        </span>
                                                        <?php if ($assignment['discharge_date']): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo formatDate($assignment['discharge_date']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo $assignment['stay_days']; ?> days</strong>
                                                    <?php if ($assignment['status'] == 'Admitted'): ?>
                                                        <br><small class="text-muted">(ongoing)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($assignment['reason'])): ?>
                                                        <span title="<?php echo htmlspecialchars($assignment['reason']); ?>">
                                                            <?php echo htmlspecialchars(substr($assignment['reason'], 0, 40)); ?>
                                                            <?php if (strlen($assignment['reason']) > 40): ?>...<?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['room_charges'] > 0): ?>
                                                        <strong><?php echo formatCurrency($assignment['room_charges']); ?></strong>
                                                        <br><small class="text-muted">per day</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="../patients/view.php?id=<?php echo $assignment['patient_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View Patient">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($assignment['status'] == 'Admitted'): ?>
                                                            <a href="../patients/discharge.php?id=<?php echo $assignment['patient_id']; ?>" 
                                                               class="btn btn-sm btn-outline-success" title="Discharge Patient">
                                                                <i class="fas fa-sign-out-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="mt-4">
                                    <?php
                                    $baseUrl = 'assignments.php?id=' . $doctorId;
                                    $params = [];
                                    if (!empty($statusFilter)) $params[] = 'status=' . urlencode($statusFilter);
                                    if (!empty($dateFilter)) $params[] = 'date_from=' . urlencode($dateFilter);
                                    if (!empty($params)) $baseUrl .= '&' . implode('&', $params);
                                    
                                    echo getPagination($currentPage, $totalPages, $baseUrl);
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-md {
    width: 50px;
    height: 50px;
}

.stat-icon {
    opacity: 0.7;
}

.card .stat-icon i {
    margin-bottom: 10px;
}

.badge {
    font-size: 0.85em;
}

.table td {
    vertical-align: middle;
}
</style>

<script>
$(document).ready(function() {
    // Auto-submit form on filter change
    $('#status, #date_from').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Tooltip for long text
    $('[title]').tooltip();
});
</script>

<?php include_once '../../includes/footer.php'; ?>