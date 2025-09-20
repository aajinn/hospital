<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>">
                <i class="fas fa-hospital"></i> <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (!isset($current_module) || $current_module == 'dashboard') ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($current_module) && $current_module == 'patients') ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/modules/patients/">
                            <i class="fas fa-user-injured"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($current_module) && $current_module == 'doctors') ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/modules/doctors/">
                            <i class="fas fa-user-md"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($current_module) && $current_module == 'billing') ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/modules/billing/">
                            <i class="fas fa-file-invoice-dollar"></i> Billing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($current_module) && $current_module == 'pharmacy') ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/modules/pharmacy/">
                            <i class="fas fa-pills"></i> Pharmacy
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid mt-4">
        <div class="row">