<?php
/**
 * Doctor Workload Dashboard
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$doctors = [];
$workloadData = [];

try {
    $connection = getDBConnection();
    
    // Get all doctors with their workload statistics
    $workloadQuery = "SELECT 
        d.id,
        d.name,
        d.specialization,
        d.consultation_fee,
        COUNT(a.id) as total_assignments,
        COUNT(CASE WHEN a.status = 'Admitted' THEN 1 END) as active_assignments,
        COUNT(CASE WHEN a.status = 'Discharged' THEN 1 END) as completed_assignments,
        COUNT(CASE WHEN a.admission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as recent_assignments,
        COUNT(CASE WHEN a.admission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as monthly_assignments,
        AVG(CASE WHEN a.discharge_date IS NOT NULL THEN DATEDIFF(a.discharge_date, a.admission_date) END) as avg_stay_days,
        MAX(a.admission_date) as last_assignment_date
    FROM doctors d
    LEFT JOIN admissions a ON d.id = a.doctor_id
    GROUP BY d.id, d.name, d.specialization, d.consultation_fee
    ORDER BY active_assignments DESC, total_assignments DESC";
    
    $result = $connection->query($workloadQuery);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $workloadData[] = $row;
        }
    }
    
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Doctor workload error: " . $e->getMessage());
    $workloadData = [];
}

// Calculate overall statistics
$totalDoctors = count($workloadData);
$totalActiveAssignments = array_sum(array_column($workloadData, 'active_assignments'));
$totalAssignments = array_sum(array_column($workloadData, 'total_assignments'));
$avgWorkload = $totalDoctors > 0 ? round($totalActiveAssignments / $totalDoctors, 1) : 0;

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
                        <h1 class="h3 mb-0">Doctor Workload Dashboard</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Doctors</a></li>
                                <li class="breadcrumb-item active">Workload</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Doctors
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-user-md fa-2x text-primary"></i>
                                </div>
                                <h3 class="text-primary"><?php echo $totalDoctors; ?></h3>
                                <p class="text-muted mb-0">Total Doctors</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-bed fa-2x text-warning"></i>
                                </div>
                                <h3 class="text-warning"><?php echo $totalActiveAssignments; ?></h3>
                                <p class="text-muted mb-0">Active Assignments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-chart-line fa-2x text-success"></i>
                                </div>
                                <h3 class="text-success"><?php echo $totalAssignments; ?></h3>
                                <p class="text-muted mb-0">Total Assignments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-balance-scale fa-2x text-info"></i>
                                </div>
                                <h3 class="text-info"><?php echo $avgWorkload; ?></h3>
                                <p class="text-muted mb-0">Avg Workload</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Workload Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar"></i> Doctor Workload Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($workloadData)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No doctors found</h5>
                                <p class="text-muted">Add doctors to the system to view workload analysis.</p>
                                <a href="add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First Doctor
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Active Patients</th>
                                            <th>Total Assignments</th>
                                            <th>Recent Activity</th>
                                            <th>Performance</th>
                                            <th>Last Assignment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($workloadData as $doctor): ?>
                                            <?php
                                            // Calculate workload level
                                            $workloadLevel = 'low';
                                            $workloadClass = 'success';
                                            if ($doctor['active_assignments'] >= 10) {
                                                $workloadLevel = 'high';
                                                $workloadClass = 'danger';
                                            } elseif ($doctor['active_assignments'] >= 5) {
                                                $workloadLevel = 'medium';
                                                $workloadClass = 'warning';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2">
                                                            <i class="fas fa-user-md"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                                            <?php if (!empty($doctor['specialization'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge badge-<?php echo $workloadClass; ?> mr-2">
                                                            <?php echo $doctor['active_assignments']; ?>
                                                        </span>
                                                        <small class="text-muted"><?php echo ucfirst($workloadLevel); ?> load</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo $doctor['total_assignments']; ?></strong>
                                                    <br><small class="text-muted">
                                                        <?php echo $doctor['completed_assignments']; ?> completed
                                                    </small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo $doctor['recent_assignments']; ?></strong> this week
                                                        <br><small class="text-muted">
                                                            <?php echo $doctor['monthly_assignments']; ?> this month
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($doctor['avg_stay_days'] > 0): ?>
                                                        <div>
                                                            <strong><?php echo round($doctor['avg_stay_days'], 1); ?> days</strong>
                                                            <br><small class="text-muted">avg stay</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($doctor['last_assignment_date']): ?>
                                                        <?php echo formatDate($doctor['last_assignment_date']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $doctor['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View Profile">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="assignments.php?id=<?php echo $doctor['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info" title="View Assignments">
                                                            <i class="fas fa-clipboard-list"></i>
                                                        </a>
                                                        <a href="../patients/admit.php?doctor_id=<?php echo $doctor['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" title="Assign New Patient">
                                                            <i class="fas fa-plus"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Workload Distribution Chart -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Workload Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $lowLoad = 0;
                                            $mediumLoad = 0;
                                            $highLoad = 0;
                                            
                                            foreach ($workloadData as $doctor) {
                                                if ($doctor['active_assignments'] >= 10) {
                                                    $highLoad++;
                                                } elseif ($doctor['active_assignments'] >= 5) {
                                                    $mediumLoad++;
                                                } else {
                                                    $lowLoad++;
                                                }
                                            }
                                            ?>
                                            <div class="workload-stats">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-success">Low Load (0-4 patients)</span>
                                                    <strong class="text-success"><?php echo $lowLoad; ?> doctors</strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-warning">Medium Load (5-9 patients)</span>
                                                    <strong class="text-warning"><?php echo $mediumLoad; ?> doctors</strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-danger">High Load (10+ patients)</span>
                                                    <strong class="text-danger"><?php echo $highLoad; ?> doctors</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">Top Performers</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            // Sort by total assignments for top performers
                                            $topPerformers = $workloadData;
                                            usort($topPerformers, function($a, $b) {
                                                return $b['total_assignments'] - $a['total_assignments'];
                                            });
                                            $topPerformers = array_slice($topPerformers, 0, 5);
                                            ?>
                                            <?php if (!empty($topPerformers)): ?>
                                                <?php foreach ($topPerformers as $index => $doctor): ?>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div>
                                                            <span class="badge badge-primary mr-2"><?php echo $index + 1; ?></span>
                                                            <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                                        </div>
                                                        <span class="text-muted"><?php echo $doctor['total_assignments']; ?> assignments</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No data available</p>
                                            <?php endif; ?>
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
.avatar-sm {
    width: 35px;
    height: 35px;
    font-size: 0.9rem;
}

.stat-icon {
    opacity: 0.7;
}

.workload-stats {
    font-size: 0.95rem;
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
    // Initialize tooltips
    $('[title]').tooltip();
    
    // Add basic table sorting if DataTables is available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.table').DataTable({
            "pageLength": 25,
            "order": [[ 1, "desc" ]], // Sort by active assignments by default
            "columnDefs": [
                { "orderable": false, "targets": -1 } // Disable sorting on actions column
            ]
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>