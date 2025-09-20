<?php
/**
 * Patient Assignment History
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$patientId = 0;
$patient = null;
$assignmentHistory = [];

// Get patient ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $patientId = (int)$_GET['id'];
    $patient = getRecordById('patients', $patientId);
    
    if (!$patient) {
        redirectWithMessage('index.php', 'Patient not found.', 'error');
    }
} else {
    redirectWithMessage('index.php', 'Invalid patient ID.', 'error');
}

// Get patient's doctor assignment history
$assignmentHistory = getPatientDoctorHistory($patientId);

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
                        <h1 class="h3 mb-0">Patient Assignment History</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Patients</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $patientId; ?>"><?php echo htmlspecialchars($patient['name']); ?></a></li>
                                <li class="breadcrumb-item active">Assignment History</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Patient
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <!-- Patient Info Header -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-md bg-info text-white rounded-circle d-flex align-items-center justify-content-center mr-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0"><?php echo htmlspecialchars($patient['name']); ?></h4>
                                        <p class="text-muted mb-0">
                                            Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?> | 
                                            <?php echo $patient['age']; ?> years, <?php echo $patient['gender']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="badge badge-primary">Total Assignments: <?php echo count($assignmentHistory); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history"></i> Doctor Assignment History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignmentHistory)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No assignment history found</h5>
                                <p class="text-muted">This patient has not been assigned to any doctors yet.</p>
                                <a href="admit.php?id=<?php echo $patientId; ?>" class="btn btn-primary">
                                    <i class="fas fa-hospital"></i> Admit Patient
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($assignmentHistory as $index => $assignment): ?>
                                    <div class="timeline-item <?php echo $assignment['status'] == 'Admitted' ? 'active' : 'completed'; ?>">
                                        <div class="timeline-marker">
                                            <?php if ($assignment['status'] == 'Admitted'): ?>
                                                <i class="fas fa-bed text-warning"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <h6 class="card-title mb-2">
                                                                <i class="fas fa-user-md text-primary mr-1"></i>
                                                                Dr. <?php echo htmlspecialchars($assignment['doctor_name']); ?>
                                                                <?php if (!empty($assignment['specialization'])): ?>
                                                                    <span class="badge badge-info ml-2"><?php echo htmlspecialchars($assignment['specialization']); ?></span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            
                                                            <div class="assignment-details">
                                                                <p class="mb-2">
                                                                    <strong>Admission Date:</strong> 
                                                                    <i class="fas fa-calendar text-muted mr-1"></i>
                                                                    <?php echo formatDate($assignment['admission_date']); ?>
                                                                </p>
                                                                
                                                                <?php if ($assignment['discharge_date']): ?>
                                                                    <p class="mb-2">
                                                                        <strong>Discharge Date:</strong> 
                                                                        <i class="fas fa-calendar-check text-muted mr-1"></i>
                                                                        <?php echo formatDate($assignment['discharge_date']); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                                
                                                                <p class="mb-2">
                                                                    <strong>Duration:</strong> 
                                                                    <i class="fas fa-clock text-muted mr-1"></i>
                                                                    <?php echo $assignment['stay_days']; ?> days
                                                                    <?php if ($assignment['status'] == 'Admitted'): ?>
                                                                        <span class="text-muted">(ongoing)</span>
                                                                    <?php endif; ?>
                                                                </p>
                                                                
                                                                <?php if (!empty($assignment['reason'])): ?>
                                                                    <p class="mb-0">
                                                                        <strong>Reason:</strong> 
                                                                        <?php echo nl2br(htmlspecialchars($assignment['reason'])); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 text-right">
                                                            <div class="assignment-status mb-3">
                                                                <?php if ($assignment['status'] == 'Admitted'): ?>
                                                                    <span class="badge badge-warning badge-lg">
                                                                        <i class="fas fa-bed"></i> Currently Admitted
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-success badge-lg">
                                                                        <i class="fas fa-check"></i> Discharged
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="assignment-actions">
                                                                <a href="../doctors/view.php?id=<?php echo $assignment['doctor_id']; ?>" 
                                                                   class="btn btn-sm btn-outline-primary mb-1" title="View Doctor">
                                                                    <i class="fas fa-user-md"></i> View Doctor
                                                                </a>
                                                                
                                                                <?php if ($assignment['status'] == 'Admitted'): ?>
                                                                    <a href="discharge.php?id=<?php echo $patientId; ?>" 
                                                                       class="btn btn-sm btn-outline-success" title="Discharge Patient">
                                                                        <i class="fas fa-sign-out-alt"></i> Discharge
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Summary Statistics -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Assignment Summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $totalAssignments = count($assignmentHistory);
                                            $activeAssignments = count(array_filter($assignmentHistory, function($a) { return $a['status'] == 'Admitted'; }));
                                            $completedAssignments = $totalAssignments - $activeAssignments;
                                            $totalStayDays = array_sum(array_column($assignmentHistory, 'stay_days'));
                                            $avgStayDays = $totalAssignments > 0 ? round($totalStayDays / $totalAssignments, 1) : 0;
                                            ?>
                                            <div class="summary-stats">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span>Total Assignments:</span>
                                                    <strong class="text-primary"><?php echo $totalAssignments; ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span>Currently Active:</span>
                                                    <strong class="text-warning"><?php echo $activeAssignments; ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span>Completed:</span>
                                                    <strong class="text-success"><?php echo $completedAssignments; ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Average Stay:</span>
                                                    <strong class="text-info"><?php echo $avgStayDays; ?> days</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Doctors Involved</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $doctorCounts = [];
                                            foreach ($assignmentHistory as $assignment) {
                                                $doctorKey = $assignment['doctor_id'];
                                                if (!isset($doctorCounts[$doctorKey])) {
                                                    $doctorCounts[$doctorKey] = [
                                                        'name' => $assignment['doctor_name'],
                                                        'specialization' => $assignment['specialization'],
                                                        'count' => 0
                                                    ];
                                                }
                                                $doctorCounts[$doctorKey]['count']++;
                                            }
                                            
                                            // Sort by count
                                            uasort($doctorCounts, function($a, $b) {
                                                return $b['count'] - $a['count'];
                                            });
                                            ?>
                                            <?php foreach ($doctorCounts as $doctorId => $doctor): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                                        <?php if (!empty($doctor['specialization'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge badge-primary"><?php echo $doctor['count']; ?> assignments</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 15px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.timeline-item.active .timeline-marker {
    border-color: #ffc107;
    background: #fff3cd;
}

.timeline-item.completed .timeline-marker {
    border-color: #28a745;
    background: #d4edda;
}

.timeline-content {
    margin-left: 20px;
}

.badge-lg {
    font-size: 0.9em;
    padding: 0.5em 0.75em;
}

.assignment-details p {
    font-size: 0.95em;
}

.summary-stats {
    font-size: 0.95em;
}
</style>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[title]').tooltip();
});
</script>

<?php include_once '../../includes/footer.php'; ?>