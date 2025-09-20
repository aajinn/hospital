<?php
/**
 * Pharmacy Alerts - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get database connection
$connection = getDBConnection();

// Get low stock medicines
$lowStockQuery = "SELECT *, 
                  CASE 
                      WHEN quantity = 0 THEN 'Out of Stock'
                      WHEN quantity <= min_quantity THEN 'Low Stock'
                      ELSE 'Normal'
                  END as stock_status
                  FROM medicines 
                  WHERE quantity <= min_quantity 
                  ORDER BY quantity ASC, name ASC";
$lowStockResult = $connection->query($lowStockQuery);

// Get medicines near expiry (within 30 days)
$nearExpiryQuery = "SELECT *, 
                    DATEDIFF(expiry_date, CURDATE()) as days_to_expiry,
                    CASE 
                        WHEN expiry_date < CURDATE() THEN 'Expired'
                        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Critical'
                        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Warning'
                        ELSE 'Normal'
                    END as expiry_status
                    FROM medicines 
                    WHERE expiry_date IS NOT NULL 
                    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    ORDER BY expiry_date ASC, name ASC";
$nearExpiryResult = $connection->query($nearExpiryQuery);

// Get expired medicines
$expiredQuery = "SELECT *, 
                 ABS(DATEDIFF(CURDATE(), expiry_date)) as days_expired
                 FROM medicines 
                 WHERE expiry_date IS NOT NULL 
                 AND expiry_date < CURDATE()
                 ORDER BY expiry_date ASC, name ASC";
$expiredResult = $connection->query($expiredQuery);

// Get summary statistics
$statsQuery = "SELECT 
                COUNT(*) as total_medicines,
                SUM(CASE WHEN quantity <= min_quantity THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as near_expiry_count,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
                SUM(quantity * price) as total_inventory_value
                FROM medicines";
$statsResult = $connection->query($statsQuery);
$stats = $statsResult->fetch_assoc();

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Alerts - Hospital Management System</title>
    <?php include '../../includes/header.php'; ?>
</head>
<body>
    <div class="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-header">
                            <h1><i class="fas fa-exclamation-triangle"></i> Pharmacy Alerts & Monitoring</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="purchase.php" class="btn btn-success">
                                    <i class="fas fa-shopping-cart"></i> Record Purchase
                                </a>
                            </div>
                        </div>

                        <?php echo displaySessionMessage(); ?>

                        <!-- Alert Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Out of Stock</h6>
                                                <h3 class="mb-0"><?php echo $stats['out_of_stock_count']; ?></h3>
                                                <small>Medicines</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-times-circle fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Low Stock</h6>
                                                <h3 class="mb-0"><?php echo $stats['low_stock_count']; ?></h3>
                                                <small>Medicines</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Near Expiry</h6>
                                                <h3 class="mb-0"><?php echo $stats['near_expiry_count']; ?></h3>
                                                <small>Medicines</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-dark text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Expired</h6>
                                                <h3 class="mb-0"><?php echo $stats['expired_count']; ?></h3>
                                                <small>Medicines</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-ban fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Value Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Total Medicines</h6>
                                                <h3 class="mb-0"><?php echo $stats['total_medicines']; ?></h3>
                                                <small>In Inventory</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-pills fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Inventory Value</h6>
                                                <h3 class="mb-0"><?php echo formatCurrency($stats['total_inventory_value']); ?></h3>
                                                <small>Total Worth</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-rupee-sign fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Low Stock Alerts -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($lowStockResult->num_rows > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Medicine</th>
                                                            <th>Current</th>
                                                            <th>Min Level</th>
                                                            <th>Status</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($medicine = $lowStockResult->fetch_assoc()): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                                                    <?php if ($medicine['category']): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($medicine['category']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?php echo ($medicine['quantity'] == 0) ? 'bg-danger' : 'bg-warning'; ?>">
                                                                        <?php echo $medicine['quantity']; ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $medicine['min_quantity']; ?></td>
                                                                <td>
                                                                    <span class="badge <?php echo ($medicine['stock_status'] == 'Out of Stock') ? 'bg-danger' : 'bg-warning'; ?>">
                                                                        <?php echo $medicine['stock_status']; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <a href="purchase.php?medicine_id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-success" title="Record Purchase">
                                                                        <i class="fas fa-shopping-cart"></i>
                                                                    </a>
                                                                    <a href="edit.php?id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-warning" title="Edit">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="text-muted mb-0">No low stock alerts</p>
                                                <small>All medicines are above minimum stock level</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Expiry Alerts -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-clock"></i> Expiry Alerts (Next 30 Days)
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($nearExpiryResult->num_rows > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Medicine</th>
                                                            <th>Expiry Date</th>
                                                            <th>Days Left</th>
                                                            <th>Status</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($medicine = $nearExpiryResult->fetch_assoc()): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                                                    <br><small class="text-muted">Stock: <?php echo $medicine['quantity']; ?></small>
                                                                </td>
                                                                <td><?php echo formatDate($medicine['expiry_date']); ?></td>
                                                                <td>
                                                                    <?php if ($medicine['days_to_expiry'] >= 0): ?>
                                                                        <?php echo $medicine['days_to_expiry']; ?> days
                                                                    <?php else: ?>
                                                                        <span class="text-danger">Expired</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = 'bg-info';
                                                                    if ($medicine['expiry_status'] == 'Critical') {
                                                                        $statusClass = 'bg-danger';
                                                                    } elseif ($medicine['expiry_status'] == 'Warning') {
                                                                        $statusClass = 'bg-warning';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $statusClass; ?>">
                                                                        <?php echo $medicine['expiry_status']; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <a href="view.php?id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-info" title="View Details">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a href="edit.php?id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-warning" title="Edit">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="text-muted mb-0">No expiry alerts</p>
                                                <small>No medicines expiring in the next 30 days</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expired Medicines -->
                        <?php if ($expiredResult->num_rows > 0): ?>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-danger text-white">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-ban"></i> Expired Medicines
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-danger">
                                                <h6><i class="fas fa-exclamation-triangle"></i> Important Notice</h6>
                                                <p class="mb-0">The following medicines have expired and should not be sold. Please remove them from active inventory or dispose of them properly.</p>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th>Medicine</th>
                                                            <th>Category</th>
                                                            <th>Expiry Date</th>
                                                            <th>Days Expired</th>
                                                            <th>Stock</th>
                                                            <th>Value Lost</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($medicine = $expiredResult->fetch_assoc()): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                                                    <?php if ($medicine['batch_number']): ?>
                                                                        <br><small class="text-muted">Batch: <?php echo htmlspecialchars($medicine['batch_number']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($medicine['category'] ?? '-'); ?></td>
                                                                <td>
                                                                    <span class="text-danger">
                                                                        <?php echo formatDate($medicine['expiry_date']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-danger">
                                                                        <?php echo $medicine['days_expired']; ?> days
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $medicine['quantity']; ?> units</td>
                                                                <td>
                                                                    <span class="text-danger">
                                                                        <?php echo formatCurrency($medicine['quantity'] * $medicine['price']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <a href="view.php?id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-info" title="View Details">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a href="edit.php?id=<?php echo $medicine['id']; ?>" 
                                                                       class="btn btn-sm btn-warning" title="Update Stock">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
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
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Auto-refresh alerts every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes

        // Add notification sound for critical alerts (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const outOfStock = <?php echo $stats['out_of_stock_count']; ?>;
            const expired = <?php echo $stats['expired_count']; ?>;
            
            if (outOfStock > 0 || expired > 0) {
                // You can add a notification sound here if needed
                console.log('Critical alerts detected:', {outOfStock, expired});
            }
        });
    </script>
</body>
</html>