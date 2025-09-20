<?php
/**
 * Medicine Sales - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get database connection
$connection = getDBConnection();

// Pagination settings
$recordsPerPage = 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search functionality
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query with search and filter
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.name LIKE ? OR p.name LIKE ? OR p.patient_id LIKE ? OR s.prescription_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= 'ssss';
}

if (!empty($dateFrom)) {
    $whereConditions[] = "s.sale_date >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "s.sale_date <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM sales s 
               JOIN medicines m ON s.medicine_id = m.id 
               LEFT JOIN patients p ON s.patient_id = p.id 
               $whereClause";
$countStmt = $connection->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
$countStmt->close();

// Get sales with pagination
$query = "SELECT s.*, m.name as medicine_name, m.category, 
                 p.name as patient_name, p.patient_id, p.phone
          FROM sales s 
          JOIN medicines m ON s.medicine_id = m.id 
          LEFT JOIN patients p ON s.patient_id = p.id 
          $whereClause 
          ORDER BY s.sale_date DESC, s.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $connection->prepare($query);
$params[] = $recordsPerPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();

// Get today's sales summary
$todayQuery = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_revenue 
               FROM sales 
               WHERE sale_date = CURDATE()";
$todayResult = $connection->query($todayQuery);
$todayStats = $todayResult->fetch_assoc();

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Sales - Hospital Management System</title>
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
                            <h1><i class="fas fa-cash-register"></i> Medicine Sales</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="new-sale.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> New Sale
                                </a>
                            </div>
                        </div>

                        <?php echo displaySessionMessage(); ?>

                        <!-- Today's Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Today's Sales</h6>
                                                <h3 class="mb-0"><?php echo $todayStats['total_sales'] ?? 0; ?></h3>
                                                <small>Transactions</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-shopping-cart fa-2x"></i>
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
                                                <h6 class="card-title">Today's Revenue</h6>
                                                <h3 class="mb-0"><?php echo formatCurrency($todayStats['total_revenue'] ?? 0); ?></h3>
                                                <small>Total Amount</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-rupee-sign fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                               placeholder="Medicine, patient, or prescription number">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_from" class="form-label">From Date</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from" 
                                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_to" class="form-label">To Date</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to" 
                                               value="<?php echo htmlspecialchars($dateTo); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <a href="sales.php" class="btn btn-secondary">
                                                <i class="fas fa-refresh"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Sales Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Sales Records</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($sales->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Medicine</th>
                                                    <th>Patient</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Price</th>
                                                    <th>Total Amount</th>
                                                    <th>Prescription</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($sale = $sales->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo formatDate($sale['sale_date']); ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($sale['medicine_name']); ?></strong>
                                                            <?php if ($sale['category']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($sale['category']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($sale['patient_name']): ?>
                                                                <strong><?php echo htmlspecialchars($sale['patient_name']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($sale['patient_id']); ?></small>
                                                                <?php if ($sale['phone']): ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($sale['phone']); ?></small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Walk-in Customer</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $sale['quantity']; ?></span>
                                                        </td>
                                                        <td><?php echo formatCurrency($sale['unit_price']); ?></td>
                                                        <td>
                                                            <strong><?php echo formatCurrency($sale['total_amount']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($sale['prescription_number']): ?>
                                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($sale['prescription_number']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="sale-receipt.php?id=<?php echo $sale['id']; ?>" 
                                                                   class="btn btn-sm btn-info" title="View Receipt">
                                                                    <i class="fas fa-receipt"></i>
                                                                </a>
                                                                <a href="edit-sale.php?id=<?php echo $sale['id']; ?>" 
                                                                   class="btn btn-sm btn-warning" title="Edit Sale">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($totalPages > 1): ?>
                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <div>
                                                <p class="text-muted mb-0">
                                                    Showing <?php echo (($currentPage - 1) * $recordsPerPage) + 1; ?> to 
                                                    <?php echo min($currentPage * $recordsPerPage, $totalRecords); ?> of 
                                                    <?php echo $totalRecords; ?> entries
                                                </p>
                                            </div>
                                            <div>
                                                <?php 
                                                $baseUrl = 'sales.php';
                                                if (!empty($searchTerm) || !empty($dateFrom) || !empty($dateTo)) {
                                                    $baseUrl .= '?';
                                                    $params = [];
                                                    if (!empty($searchTerm)) $params[] = 'search=' . urlencode($searchTerm);
                                                    if (!empty($dateFrom)) $params[] = 'date_from=' . urlencode($dateFrom);
                                                    if (!empty($dateTo)) $params[] = 'date_to=' . urlencode($dateTo);
                                                    $baseUrl .= implode('&', $params);
                                                }
                                                echo getPagination($currentPage, $totalPages, $baseUrl);
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-cash-register fa-3x text-muted mb-3"></i>
                                        <h5>No sales found</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($searchTerm) || !empty($dateFrom) || !empty($dateTo)): ?>
                                                No sales match your search criteria. <a href="sales.php">Clear filters</a> to see all sales.
                                            <?php else: ?>
                                                Start by recording your first medicine sale.
                                            <?php endif; ?>
                                        </p>
                                        <a href="new-sale.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Record New Sale
                                        </a>
                                    </div>
                                <?php endif; ?>
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