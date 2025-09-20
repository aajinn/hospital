<?php
/**
 * Main Dashboard
 * Hospital Management System
 */

require_once 'config/config.php';
require 'includes/functions.php';

$page_title = 'Dashboard';
?>

<?php include 'includes/header.php'; ?>

<!-- Dashboard Content -->
<div class="col-md-9 col-lg-10">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <small class="text-muted">Welcome to <?php echo APP_NAME; ?></small>
    </div>
    
    <!-- Alert Container -->
    <div id="alertContainer"></div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Patients</h6>
                            <h2 class="display-4">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-injured fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="modules/patients/" class="text-white">
                        <small>View Details <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Doctors</h6>
                            <h2 class="display-4">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-md fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="modules/doctors/" class="text-white">
                        <small>View Details <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending Bills</h6>
                            <h2 class="display-4">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-invoice-dollar fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="modules/billing/" class="text-white">
                        <small>View Details <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Medicines</h6>
                            <h2 class="display-4">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-pills fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="modules/pharmacy/" class="text-white">
                        <small>View Details <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="modules/patients/add.php" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> Add Patient
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/doctors/add.php" class="btn btn-success btn-block">
                                <i class="fas fa-plus"></i> Add Doctor
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/billing/add.php" class="btn btn-warning btn-block">
                                <i class="fas fa-plus"></i> Generate Bill
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/pharmacy/add.php" class="btn btn-info btn-block">
                                <i class="fas fa-plus"></i> Add Medicine
                            </a>
                        </div>
                    </div>
                    
                    <!-- Management Actions -->
                    <div class="row mt-3">
                        <div class="col-md-6 mb-2">
                            <a href="modules/doctors/workload.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-chart-bar"></i> Doctor Workload Dashboard
                            </a>
                        </div>
                        <div class="col-md-6 mb-2">
                            <a href="modules/patients/index.php" class="btn btn-outline-secondary btn-block">
                                <i class="fas fa-list"></i> View All Patients
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-clock"></i> Recent Activities</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">No recent activities to display.</p>
                    <small class="text-muted">Activities will appear here once you start using the system.</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Alerts</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">No alerts at this time.</p>
                    <small class="text-muted">Low stock and expiry alerts will appear here.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/sidebar.php'; ?>

<?php include 'includes/footer.php'; ?>