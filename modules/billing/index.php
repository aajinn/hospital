<?php
/**
 * Billing Module - Main Index
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$bills = [];
$totalBills = 0;
$currentPage = 1;
$totalPages = 1;
$searchTerm = '';
$statusFilter = '';

// Get search parameters
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}
if (isset($_GET['status'])) {
    $statusFilter = $_GET['status'];
}

// Get current page
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = (int)$_GET['page'];
    if ($currentPage < 1) $currentPage = 1;
}

// Calculate offset for pagination
$offset = ($currentPage - 1) * RECORDS_PER_PAGE;

try {
    $connection = getDBConnection();
    
    // Build search query
    $whereClause = '';
    $params = [];
    $types = '';
    
    $conditions = [];
    
    if (!empty($searchTerm)) {
        $conditions[] = "(p.name LIKE ? OR p.patient_id LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $types .= 'ss';
    }
    
    if (!empty($statusFilter)) {
        $conditions[] = "b.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total 
                   FROM bills b 
                   JOIN patients p ON b.patient_id = p.id 
                   $whereClause";
    $countStmt = $connection->prepare($countQuery);
    
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalBills = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Calculate total pages
    $totalPages = ceil($totalBills / RECORDS_PER_PAGE);
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * RECORDS_PER_PAGE;
    }
    
    // Get bills with pagination
    $query = "SELECT b.*, p.name as patient_name, p.patient_id,
                     COALESCE(SUM(pay.amount), 0) as paid_amount,
                     (b.total_amount - COALESCE(SUM(pay.amount), 0)) as pending_amount
              FROM bills b 
              JOIN patients p ON b.patient_id = p.id 
              LEFT JOIN payments pay ON b.id = pay.bill_id
              $whereClause
              GROUP BY b.id, p.name, p.patient_id
              ORDER BY b.created_at DESC 
              LIMIT ? OFFSET ?";
    $stmt = $connection->prepare($query);
    
    // Add limit and offset parameters
    $params[] = RECORDS_PER_PAGE;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }
    
    $stmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Billing listing error: " . $e->getMessage());
    $error = "An error occurred while loading bills.";
}

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
                        <h1 class="h3 mb-0">Billing & Payment Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Billing</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="generate.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Generate Bill
                        </a>
                        <a href="reports.php" class="btn btn-info ml-2">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>
                
                <!-- Display errors -->
                <?php if (isset($error)): ?>
                    <?php echo showErrorMessage($error); ?>
                <?php endif; ?>

                <!-- Search and Filter Box -->
                <div class="search-box">
                    <form method="GET" action="" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="search">Search Bills</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                   placeholder="Patient name or ID...">
                        </div>
                        <div class="col-md-3">
                            <label for="status">Filter by Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Partial" <?php echo $statusFilter == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                                <option value="Paid" <?php echo $statusFilter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="index.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                        <div class="col-md-2 text-right">
                            <small class="text-muted">
                                Total: <?php echo $totalBills; ?> bills
                            </small>
                        </div>
                    </form>
                </div>

                <!-- Bills List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-invoice-dollar"></i> Bills List
                            <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>
                                <small class="text-muted">- Filtered results</small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($bills)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bill ID</th>
                                            <th>Patient</th>
                                            <th>Bill Date</th>
                                            <th>Total Amount</th>
                                            <th>Paid Amount</th>
                                            <th>Pending</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td>
                                                    <span class="bill-id">#<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bill['patient_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($bill['patient_id']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($bill['bill_date']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($bill['total_amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo formatCurrency($bill['paid_amount']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($bill['pending_amount'] > 0): ?>
                                                        <span class="text-danger">
                                                            <?php echo formatCurrency($bill['pending_amount']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-success">₹0.00</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($bill['status']) {
                                                        case 'Paid':
                                                            $statusClass = 'badge-success';
                                                            break;
                                                        case 'Partial':
                                                            $statusClass = 'badge-warning';
                                                            break;
                                                        case 'Pending':
                                                            $statusClass = 'badge-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $bill['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="view.php?id=<?php echo $bill['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="View Bill">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($bill['status'] != 'Paid'): ?>
                                                            <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" 
                                                               class="btn btn-sm btn-success" title="Add Payment">
                                                                <i class="fas fa-money-bill"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="print.php?id=<?php echo $bill['id']; ?>" 
                                                           class="btn btn-sm btn-secondary" title="Print Bill" target="_blank">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Bills Found</h5>
                                <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>
                                    <p class="text-muted">No bills match your search criteria.</p>
                                    <a href="index.php" class="btn btn-secondary">View All Bills</a>
                                <?php else: ?>
                                    <p class="text-muted">No bills have been generated yet.</p>
                                    <a href="generate.php" class="btn btn-primary">Generate First Bill</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <small class="text-muted">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + RECORDS_PER_PAGE, $totalBills); ?> 
                                of <?php echo $totalBills; ?> bills
                            </small>
                        </div>
                        <div>
                            <?php
                            $baseUrl = 'index.php';
                            $queryParams = [];
                            if (!empty($searchTerm)) {
                                $queryParams[] = 'search=' . urlencode($searchTerm);
                            }
                            if (!empty($statusFilter)) {
                                $queryParams[] = 'status=' . urlencode($statusFilter);
                            }
                            if (!empty($queryParams)) {
                                $baseUrl .= '?' . implode('&', $queryParams);
                            }
                            echo getPagination($currentPage, $totalPages, $baseUrl);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-submit search form on enter
    $('#search').on('keypress', function(e) {
        if (e.which == 13) {
            $(this).closest('form').submit();
        }
    });
    
    // Auto-submit on status change
    $('#status').on('change', function() {
        $(this).closest('form').submit();
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>