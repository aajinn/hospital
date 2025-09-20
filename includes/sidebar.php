<?php
/**
 * Sidebar Navigation Component
 * Hospital Management System
 */
?>

<div class="col-md-3 col-lg-2">
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h6 class="mb-0"><i class="fas fa-bars"></i> Navigation</h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <a href="<?php echo APP_URL; ?>" class="list-group-item list-group-item-action <?php echo (!isset($current_module) || $current_module == 'dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                
                <!-- Patient Management -->
                <div class="list-group-item bg-light">
                    <strong><i class="fas fa-user-injured"></i> Patient Management</strong>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/patients/" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'patients' && !isset($current_page)) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Patients
                </a>
                <a href="<?php echo APP_URL; ?>/modules/patients/add.php" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'patients' && isset($current_page) && $current_page == 'add') ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Add Patient
                </a>
                
                <!-- Doctor Management -->
                <div class="list-group-item bg-light">
                    <strong><i class="fas fa-user-md"></i> Doctor Management</strong>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/doctors/" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'doctors' && !isset($current_page)) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Doctors
                </a>
                <a href="<?php echo APP_URL; ?>/modules/doctors/add.php" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'doctors' && isset($current_page) && $current_page == 'add') ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Add Doctor
                </a>
                
                <!-- Billing Management -->
                <div class="list-group-item bg-light">
                    <strong><i class="fas fa-file-invoice-dollar"></i> Billing</strong>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/billing/" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'billing' && !isset($current_page)) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Bills
                </a>
                <a href="<?php echo APP_URL; ?>/modules/billing/add.php" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'billing' && isset($current_page) && $current_page == 'add') ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Generate Bill
                </a>
                
                <!-- Pharmacy Management -->
                <div class="list-group-item bg-light">
                    <strong><i class="fas fa-pills"></i> Pharmacy</strong>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/pharmacy/" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'pharmacy' && !isset($current_page)) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Medicine Inventory
                </a>
                <a href="<?php echo APP_URL; ?>/modules/pharmacy/add.php" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'pharmacy' && isset($current_page) && $current_page == 'add') ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Add Medicine
                </a>
                <a href="<?php echo APP_URL; ?>/modules/pharmacy/sales.php" class="list-group-item list-group-item-action pl-4 <?php echo (isset($current_module) && $current_module == 'pharmacy' && isset($current_page) && $current_page == 'sales') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> Medicine Sales
                </a>
            </div>
        </div>
    </div>
</div>