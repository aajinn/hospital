<?php
/**
 * Pharmacy Module - Main Index
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get database connection
$connection = getDBConnection();

// Pagination settings
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search functionality
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

// Build query with search and filter
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereConditions[] = "(name LIKE ? OR supplier LIKE ? OR batch_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= 'sss';
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM medicines $whereClause";
$countStmt = $connection->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
$countStmt->close();

// Get medicines with pagination
$query = "SELECT *, 
          CASE 
              WHEN quantity <= min_quantity THEN 'Low Stock'
              WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 'Near Expiry'
              WHEN expiry_date < CURDATE() THEN 'Expired'
              ELSE 'Normal'
          END as alert_status
          FROM medicines 
          $whereClause 
          ORDER BY name ASC 
          LIMIT ? OFFSET ?";

$stmt = $connection->prepare($query);
$params[] = $recordsPerPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$medicines = $stmt->get_result();
$stmt->close();

// Get categories for filter dropdown
$categoryQuery = "SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categoryResult = $connection->query($categoryQuery);
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Hospital Management System</title>
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
                            <h1><i class="fas fa-pills"></i> Pharmacy Management</h1>
                            <div class="page-actions">
                                <a href="add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Medicine
                                </a>
                                <a href="purchase.php" class="btn btn-success">
                                    <i class="fas fa-shopping-cart"></i> Record Purchase
                                </a>
                                <a href="sales.php" class="btn btn-info">
                                    <i class="fas fa-cash-register"></i> Medicine Sales
                                </a>
                                <a href="alerts.php" class="btn btn-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Alerts & Monitoring
                                </a>
                            </div>
                        </div>

                        <?php echo displaySessionMessage(); ?>

                        <!-- Pharmacy Alerts Notification -->
                        <?php
                        $alertCounts = getPharmacyAlertCounts();
                        $totalAlerts = $alertCounts['out_of_stock_count'] + $alertCounts['expired_count'] + $alertCounts['low_stock_count'] + $alertCounts['near_expiry_count'];
                        
                        if ($totalAlerts > 0):
                        ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <h6><i class="fas fa-exclamation-triangle"></i> Pharmacy Alerts (<?php echo $totalAlerts; ?>)</h6>
                                <div class="row">
                                    <?php if ($alertCounts['out_of_stock_count'] > 0): ?>
                                        <div class="col-md-3">
                                            <span class="badge bg-danger"><?php echo $alertCounts['out_of_stock_count']; ?></span>
                                            Out of Stock
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($alertCounts['expired_count'] > 0): ?>
                                        <div class="col-md-3">
                                            <span class="badge bg-dark"><?php echo $alertCounts['expired_count']; ?></span>
                                            Expired
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($alertCounts['low_stock_count'] > 0): ?>
                                        <div class="col-md-3">
                                            <span class="badge bg-warning"><?php echo $alertCounts['low_stock_count']; ?></span>
                                            Low Stock
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($alertCounts['near_expiry_count'] > 0): ?>
                                        <div class="col-md-3">
                                            <span class="badge bg-info"><?php echo $alertCounts['near_expiry_count']; ?></span>
                                            Near Expiry
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <a href="alerts.php" class="btn btn-sm btn-warning">
                                        <i class="fas fa-eye"></i> View All Alerts
                                    </a>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Search and Filter Section -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Search Medicine</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                               placeholder="Search by name, supplier, or batch number">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-control" id="category" name="category">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                                        <?php echo ($categoryFilter == $category) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category); ?>
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
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label>&nbsp;</label>
                                        <div>
                                            <a href="index.php" class="btn btn-secondary">
                                                <i class="fas fa-refresh"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Medicines Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Medicine Inventory</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($medicines->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Price</th>
                                                    <th>Stock</th>
                                                    <th>Min. Stock</th>
                                                    <th>Expiry Date</th>
                                                    <th>Supplier</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($medicine = $medicines->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                                            <?php if (!empty($medicine['batch_number'])): ?>
                                                                <br><small class="text-muted">Batch: <?php echo htmlspecialchars($medicine['batch_number']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($medicine['category'] ?? '-'); ?></td>
                                                        <td><?php echo formatCurrency($medicine['price']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo ($medicine['quantity'] <= $medicine['min_quantity']) ? 'bg-danger' : 'bg-success'; ?>">
                                                                <?php echo $medicine['quantity']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $medicine['min_quantity']; ?></td>
                                                        <td>
                                                            <?php 
                                                            $expiryDate = $medicine['expiry_date'];
                                                            $alertClass = '';
                                                            if ($expiryDate < date('Y-m-d')) {
                                                                $alertClass = 'text-danger';
                                                            } elseif ($expiryDate <= date('Y-m-d', strtotime('+30 days'))) {
                                                                $alertClass = 'text-warning';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $alertClass; ?>">
                                                                <?php echo formatDate($expiryDate); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($medicine['supplier'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'bg-success';
                                                            if ($medicine['alert_status'] == 'Low Stock') {
                                                                $statusClass = 'bg-warning';
                                                            } elseif ($medicine['alert_status'] == 'Near Expiry') {
                                                                $statusClass = 'bg-info';
                                                            } elseif ($medicine['alert_status'] == 'Expired') {
                                                                $statusClass = 'bg-danger';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $statusClass; ?>">
                                                                <?php echo $medicine['alert_status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="view.php?id=<?php echo $medicine['id']; ?>" 
                                                                   class="btn btn-sm btn-info" title="View Details">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="edit.php?id=<?php echo $medicine['id']; ?>" 
                                                                   class="btn btn-sm btn-warning" title="Edit">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="delete.php?id=<?php echo $medicine['id']; ?>" 
                                                                   class="btn btn-sm btn-danger" title="Delete"
                                                                   onclick="return confirm('Are you sure you want to delete this medicine?')">
                                                                    <i class="fas fa-trash"></i>
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
                                                $baseUrl = 'index.php';
                                                if (!empty($searchTerm) || !empty($categoryFilter)) {
                                                    $baseUrl .= '?';
                                                    $params = [];
                                                    if (!empty($searchTerm)) $params[] = 'search=' . urlencode($searchTerm);
                                                    if (!empty($categoryFilter)) $params[] = 'category=' . urlencode($categoryFilter);
                                                    $baseUrl .= implode('&', $params);
                                                }
                                                echo getPagination($currentPage, $totalPages, $baseUrl);
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                                        <h5>No medicines found</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($searchTerm) || !empty($categoryFilter)): ?>
                                                No medicines match your search criteria. <a href="index.php">Clear filters</a> to see all medicines.
                                            <?php else: ?>
                                                Start by adding your first medicine to the inventory.
                                            <?php endif; ?>
                                        </p>
                                        <a href="add.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Medicine
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