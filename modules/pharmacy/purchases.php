<?php
/**
 * Medicine Purchases - Pharmacy Module
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
$supplierFilter = isset($_GET['supplier']) ? sanitizeInput($_GET['supplier']) : '';

// Build query with search and filter
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.name LIKE ? OR p.supplier LIKE ? OR p.invoice_number LIKE ? OR p.batch_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= 'ssss';
}

if (!empty($dateFrom)) {
    $whereConditions[] = "p.purchase_date >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "p.purchase_date <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

if (!empty($supplierFilter)) {
    $whereConditions[] = "p.supplier = ?";
    $params[] = $supplierFilter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM purchases p 
               JOIN medicines m ON p.medicine_id = m.id 
               $whereClause";
$countStmt = $connection->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
$countStmt->close();

// Get purchases with pagination
$query = "SELECT p.*, m.name as medicine_name, m.category
          FROM purchases p 
          JOIN medicines m ON p.medicine_id = m.id 
          $whereClause 
          ORDER BY p.purchase_date DESC, p.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $connection->prepare($query);
$params[] = $recordsPerPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$purchases = $stmt->get_result();
$stmt->close();

// Get suppliers for filter dropdown
$supplierQuery = "SELECT DISTINCT supplier FROM purchases WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier";
$supplierResult = $connection->query($supplierQuery);
$suppliers = [];
while ($row = $supplierResult->fetch_assoc()) {
    $suppliers[] = $row['supplier'];
}

// Get summary statistics
$summaryQuery = "SELECT 
                    COUNT(*) as total_purchases,
                    SUM(total_amount) as total_spent,
                    SUM(quantity) as total_quantity
                 FROM purchases p
                 JOIN medicines m ON p.medicine_id = m.id
                 $whereClause";
$summaryStmt = $connection->prepare($summaryQuery);
if (!empty($whereConditions)) {
    // Remove the last two parameters (LIMIT and OFFSET) for summary
    $summaryParams = array_slice($params, 0, -2);
    $summaryTypes = substr($types, 0, -2);
    if (!empty($summaryParams)) {
        $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
    }
}
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Purchases - Hospital Management System</title>
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
                            <h1><i class="fas fa-truck"></i> Medicine Purchases</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="purchase.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Record Purchase
                                </a>
                            </div>
                        </div>

                        <?php echo displaySessionMessage(); ?>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Total Purchases</h6>
                                                <h3 class="mb-0"><?php echo $summary['total_purchases'] ?? 0; ?></h3>
                                                <small>Transactions</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-shopping-cart fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Total Amount</h6>
                                                <h3 class="mb-0"><?php echo formatCurrency($summary['total_spent'] ?? 0); ?></h3>
                                                <small>Spent</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-rupee-sign fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">Total Quantity</h6>
                                                <h3 class="mb-0"><?php echo $summary['total_quantity'] ?? 0; ?></h3>
                                                <small>Units Purchased</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-boxes fa-2x"></i>
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
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                               placeholder="Medicine, supplier, invoice, or batch">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_from" class="form-label">From Date</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from" 
                                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_to" class="form-label">To Date</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to" 
                                               value="<?php echo htmlspecialchars($dateTo); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="supplier" class="form-label">Supplier</label>
                                        <select class="form-control" id="supplier" name="supplier">
                                            <option value="">All Suppliers</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?php echo htmlspecialchars($supplier); ?>" 
                                                        <?php echo ($supplierFilter == $supplier) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($supplier); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <a href="purchases.php" class="btn btn-secondary">
                                                <i class="fas fa-refresh"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Purchases Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Purchase Records</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($purchases->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Medicine</th>
                                                    <th>Supplier</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Price</th>
                                                    <th>Total Amount</th>
                                                    <th>Invoice</th>
                                                    <th>Batch/Expiry</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($purchase = $purchases->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo formatDate($purchase['purchase_date']); ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($purchase['medicine_name']); ?></strong>
                                                            <?php if ($purchase['category']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($purchase['category']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($purchase['supplier']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $purchase['quantity']; ?></span>
                                                        </td>
                                                        <td><?php echo formatCurrency($purchase['unit_price']); ?></td>
                                                        <td>
                                                            <strong><?php echo formatCurrency($purchase['total_amount']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($purchase['invoice_number']): ?>
                                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($purchase['invoice_number']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($purchase['batch_number']): ?>
                                                                <small><strong>Batch:</strong> <?php echo htmlspecialchars($purchase['batch_number']); ?></small><br>
                                                            <?php endif; ?>
                                                            <?php if ($purchase['expiry_date']): ?>
                                                                <small><strong>Exp:</strong> <?php echo formatDate($purchase['expiry_date']); ?></small>
                                                            <?php endif; ?>
                                                            <?php if (!$purchase['batch_number'] && !$purchase['expiry_date']): ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="purchase-receipt.php?id=<?php echo $purchase['id']; ?>" 
                                                                   class="btn btn-sm btn-info" title="View Receipt">
                                                                    <i class="fas fa-receipt"></i>
                                                                </a>
                                                                <a href="edit-purchase.php?id=<?php echo $purchase['id']; ?>" 
                                                                   class="btn btn-sm btn-warning" title="Edit Purchase">
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
                                                $baseUrl = 'purchases.php';
                                                if (!empty($searchTerm) || !empty($dateFrom) || !empty($dateTo) || !empty($supplierFilter)) {
                                                    $baseUrl .= '?';
                                                    $params = [];
                                                    if (!empty($searchTerm)) $params[] = 'search=' . urlencode($searchTerm);
                                                    if (!empty($dateFrom)) $params[] = 'date_from=' . urlencode($dateFrom);
                                                    if (!empty($dateTo)) $params[] = 'date_to=' . urlencode($dateTo);
                                                    if (!empty($supplierFilter)) $params[] = 'supplier=' . urlencode($supplierFilter);
                                                    $baseUrl .= implode('&', $params);
                                                }
                                                echo getPagination($currentPage, $totalPages, $baseUrl);
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                                        <h5>No purchases found</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($searchTerm) || !empty($dateFrom) || !empty($dateTo) || !empty($supplierFilter)): ?>
                                                No purchases match your search criteria. <a href="purchases.php">Clear filters</a> to see all purchases.
                                            <?php else: ?>
                                                Start by recording your first medicine purchase.
                                            <?php endif; ?>
                                        </p>
                                        <a href="purchase.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Record Purchase
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