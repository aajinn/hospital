<?php
/**
 * Database Installation Script
 * Hospital Management System
 */

// Include configuration
require_once 'config/config.php';

$page_title = 'Database Installation';
$installation_complete = false;
$error_message = '';

// Check if installation is requested
if (isset($_POST['install_db'])) {
    try {
        // Create connection without selecting database
        $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD);
        
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }
        
        // Read SQL file
        $sql_file = 'sql/hospital_db.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("SQL file not found: " . $sql_file);
        }
        
        $sql_content = file_get_contents($sql_file);
        
        // Execute SQL statements
        if ($connection->multi_query($sql_content)) {
            do {
                // Store first result set
                if ($result = $connection->store_result()) {
                    $result->free();
                }
            } while ($connection->next_result());
            
            $installation_complete = true;
        } else {
            throw new Exception("Error executing SQL: " . $connection->error);
        }
        
        $connection->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Check if database already exists
$db_exists = false;
try {
    $connection = getDBConnection();
    $db_exists = true;
    closeDBConnection($connection);
} catch (Exception $e) {
    // Database doesn't exist or connection failed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-database"></i> <?php echo APP_NAME; ?> - Database Installation
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($installation_complete): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                <strong>Installation Complete!</strong> 
                                The database has been successfully created and configured.
                            </div>
                            <div class="text-center">
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right"></i> Go to Dashboard
                                </a>
                            </div>
                        <?php elseif ($db_exists): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Database Already Exists!</strong> 
                                The hospital management database is already installed and configured.
                            </div>
                            <div class="text-center">
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right"></i> Go to Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> 
                                    <strong>Installation Error:</strong> <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>Database Not Found!</strong> 
                                Please install the database to continue.
                            </div>
                            
                            <h5>Installation Requirements:</h5>
                            <ul>
                                <li>MySQL 8.0 or higher</li>
                                <li>PHP 7.4 or higher</li>
                                <li>Proper database credentials configured in <code>config/database.php</code></li>
                            </ul>
                            
                            <h5>What will be installed:</h5>
                            <ul>
                                <li>Database: <strong>hospital_management</strong></li>
                                <li>Tables: patients, doctors, admissions, bills, payments, medicines, sales, purchases</li>
                                <li>Indexes and foreign key constraints</li>
                                <li>Triggers for automatic calculations</li>
                                <li>Views for common queries</li>
                            </ul>
                            
                            <form method="POST" class="mt-4">
                                <div class="text-center">
                                    <button type="submit" name="install_db" class="btn btn-success btn-lg">
                                        <i class="fas fa-download"></i> Install Database
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted text-center">
                        <small>
                            <i class="fas fa-hospital"></i> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>