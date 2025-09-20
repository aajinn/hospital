<?php
/**
 * View Medicine Details - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get medicine ID
$medicineId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($medicineId <= 0) {
    redirectWithMessage('index.php', 'Invalid medicine ID', 'error');
}

// Get medicine details
$medicine = getRecordById('medicines', $medicineId);

if (!$medicine) {
    redirectWithMessage('index.php', 'Medicine not found', 'error');
}

// Get database connection for additional queries
$connection = getDBConnection();

// Get recent sales for this medicine
$salesQuery = "SELECT s.*, p.name as patient_name, p.patient_id 
               FROM sales s 
               LEFT JOIN patients p ON s.patient_id = p.id 
               WHERE s.medicine_id = ? 
               ORDER BY s.sale_date DESC, s.created_at DESC 
               LIMIT 10";
$salesStmt = $connection->prepare($salesQuery);
$salesStmt->bind_param('i', $medicineId);
$salesStmt->execute();
$recentSales = $salesStmt->get_result();
$salesStmt->close();

// Get recent purchases for this medicine
$purchaseQuery = "SELECT * FROM purchases 
                  WHERE medicine_id = ? 
                  ORDER BY purchase_date DESC, created_at DESC 
                  LIMIT 10";
$purchaseStmt = $connection->prepare($purchaseQuery);
$purchaseStmt->bind_param('i', $medicineId);
$purchaseStmt->execute();
$recentPurchases = $purchaseStmt->get_result();
$purchaseStmt->close();

// Calculate total sales and purchases
$totalSalesQuery = "SELECT COUNT(*) as total_sales, SUM(quantity) as total_quantity_sold, SUM(total_amount) as total_revenue 
                    FROM sales WHERE medicine_id = ?";
$totalSalesStmt = $connection->prepare($totalSalesQuery);
$totalSalesStmt->bind_param('i', $medicineId);
$totalSalesStmt->execute();
$salesStats = $totalSalesStmt->get_result()->fetch_assoc();
$totalSalesStmt->close();

$totalPurchasesQuery = "SELECT COUNT(*) as total_purchases, SUM(quantity) as total_quantity_purchased, SUM(total_amount) as total_cost 
                        FROM purchases WHERE medicine_id = ?";
$totalPurchasesStmt = $connection->prepare($totalPurchasesQuery);
$totalPurchasesStmt->bind_param('i', $medicineId);
$totalPurchasesStmt->execute();
$purchaseStats = $totalPurchasesStmt->get_result()->fetch_assoc();
$totalPurchasesStmt->close();

closeDBConnection($connection);

// Calculate alert status
$alertStatus = 'Normal';
$alertClass = 'success';
if ($medicine['quantity'] <= $medicine['min_quantity']) {
    $alertStatus = 'Low Stock';
    $alertClass = 'warning';
}
if ($medicine['expiry_date'] && $medicine['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) {
    if ($medicine['expiry_date'] < date('Y-m-d')) {
        $alertStatus = 'Expired';
        $alertClass = 'danger';
    } else {
        $alertStatus = 'Near Expiry';
        $alertClass = 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Details - Hospital Management System</title>
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
                            <h1><i class="fas fa-pills"></i> Medicine Details</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="edit.php?id=<?php echo $medicineId; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Medicine
                                </a>
                                <a href="delete.php?id=<?php echo $medicineId; ?>" class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this medicine?')">
                                    <i class="fas fa-trash"></i> Delete Medicine
                                </a>
                            </div>
                        </div>

                        <?php echo displaySessionMessage(); ?>

                        <div class="row">
                            <!-- Medicine Information -->
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-info-circle"></i> Medicine Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td><strong>Name:</strong></td>
                                                        <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Category:</strong></td>
                                                        <td><?php echo htmlspecialchars($medicine['category'] ?? '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Price per Unit:</strong></td>
                                                        <td><?php echo formatCurrency($medicine['price']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Current Stock:</strong></td>
                                                        <td>
                                                            <span class="badge <?php echo ($medicine['quantity'] <= $medicine['min_quantity']) ? 'bg-danger' : 'bg-success'; ?>">
                                                                <?php echo $medicine['quantity']; ?> units
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Minimum Stock:</strong></td>
                                                        <td><?php echo $medicine['min_quantity']; ?> units</td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td><strong>Expiry Date:</strong></td>
                                                        <td>
                                                            <?php if ($medicine['expiry_date']): ?>
                                                                <?php
                                                                $expiryClass = '';
                                                                if ($medicine['expiry_date'] < date('Y-m-d')) {
                                                                    $expiryClass = 'text-danger';
                                                                } elseif ($medicine['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) {
                                                                    $expiryClass = 'text-warning';
                                                                }
                                                                ?>
                                                                <span class="<?php echo $expiryClass; ?>">
                                                                    <?php echo formatDate($medicine['expiry_date']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Supplier:</strong></td>
                                                        <td><?php echo htmlspecialchars($medicine['supplier'] ?? '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Batch Number:</strong></td>
                                                        <td><?php echo htmlspecialchars($medicine['batch_number'] ?? '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Added On:</strong></td>
                                                        <td><?php echo formatDate($medicine['created_at'], 'd-m-Y H:i'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Last Updated:</strong></td>
                                                        <td><?php echo formatDate($medicine['updated_at'], 'd-m-Y H:i'); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status and Statistics -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-chart-bar"></i> Status & Statistics
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Current Status:</strong></label>
                                            <br>
                                            <span class="badge bg-<?php echo $alertClass; ?> fs-6">
                                                <?php echo $alertStatus; ?>
                                            </span>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label"><strong>Total Sales:</strong></label>
                                            <br>
                                            <span class="text-primary"><?php echo $salesStats['total_sales'] ?? 0; ?> transactions</span>
                                            <br>
                                            <span class="text-muted"><?php echo $salesStats['total_quantity_sold'] ?? 0; ?> units sold</span>
                                            <br>
                                            <span class="text-success"><?php echo formatCurrency($salesStats['total_revenue'] ?? 0); ?> revenue</span>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label"><strong>Total Purchases:</strong></label>
                                            <br>
                                            <span class="text-info"><?php echo $purchaseStats['total_purchases'] ?? 0; ?> transactions</span>
                                            <br>
                                            <span class="text-muted"><?php echo $purchaseStats['total_quantity_purchased'] ?? 0; ?> units purchased</span>
                                            <br>
                                            <span class="text-warning"><?php echo formatCurrency($purchaseStats['total_cost'] ?? 0); ?> cost</span>
                                        </div>

                                        <?php if (($salesStats['total_revenue'] ?? 0) > 0 && ($purchaseStats['total_cost'] ?? 0) > 0): ?>
                                            <div class="mb-3">
                                                <label class="form-label"><strong>Profit Margin:</strong></label>
                                                <br>
                                                <?php 
                                                $profit = $salesStats['total_revenue'] - $purchaseStats['total_cost'];
                                                $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                                                ?>
                                                <span class="<?php echo $profitClass; ?>">
                                                    <?php echo formatCurrency($profit); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Sales -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-shopping-cart"></i> Recent Sales
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($recentSales->num_rows > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Patient</th>
                                                            <th>Qty</th>
                                                            <th>Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                                            <tr>
                                                                <td><?php echo formatDate($sale['sale_date']); ?></td>
                                                                <td>
                                                                    <?php if ($sale['patient_name']): ?>
                                                                        <?php echo htmlspecialchars($sale['patient_name']); ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($sale['patient_id']); ?></small>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Walk-in</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo $sale['quantity']; ?></td>
                                                                <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center">No sales recorded yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Purchases -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-truck"></i> Recent Purchases
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($recentPurchases->num_rows > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Supplier</th>
                                                            <th>Qty</th>
                                                            <th>Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($purchase = $recentPurchases->fetch_assoc()): ?>
                                                            <tr>
                                                                <td><?php echo formatDate($purchase['purchase_date']); ?></td>
                                                                <td>
                                                                    <?php echo htmlspecialchars($purchase['supplier']); ?>
                                                                    <?php if ($purchase['invoice_number']): ?>
                                                                        <br><small class="text-muted">Inv: <?php echo htmlspecialchars($purchase['invoice_number']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo $purchase['quantity']; ?></td>
                                                                <td><?php echo formatCurrency($purchase['total_amount']); ?></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center">No purchases recorded yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>