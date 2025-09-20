<?php
/**
 * Billing Reports - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today

// Get report data based on type
$reportData = [];
$chartData = [];

try {
    $connection = getDBConnection();
    
    switch ($reportType) {
        case 'summary':
            // Billing summary report
            $query = "SELECT 
                        COUNT(*) as total_bills,
                        SUM(b.total_amount) as total_billed,
                        SUM(COALESCE(payments.paid_amount, 0)) as total_collected,
                        SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending,
                        COUNT(CASE WHEN b.status = 'Paid' THEN 1 END) as paid_bills,
                        COUNT(CASE WHEN b.status = 'Partial' THEN 1 END) as partial_bills,
                        COUNT(CASE WHEN b.status = 'Pending' THEN 1 END) as pending_bills,
                        AVG(b.total_amount) as average_bill_amount
                      FROM bills b
                      LEFT JOIN (
                          SELECT bill_id, SUM(amount) as paid_amount
                          FROM payments
                          GROUP BY bill_id
                      ) payments ON b.id = payments.bill_id
                      WHERE b.bill_date BETWEEN ? AND ?";
            
            $stmt = $connection->prepare($query);
            $stmt->bind_param('ss', $dateFrom, $dateTo);
            $stmt->execute();
            $result = $stmt->get_result();
            $reportData = $result->fetch_assoc();
            $stmt->close();
            break;
            
        case 'daily':
            // Daily billing report
            $query = "SELECT 
                        DATE(b.bill_date) as bill_date,
                        COUNT(*) as bills_count,
                        SUM(b.total_amount) as total_billed,
                        SUM(COALESCE(payments.paid_amount, 0)) as total_collected
                      FROM bills b
                      LEFT JOIN (
                          SELECT bill_id, SUM(amount) as paid_amount
                          FROM payments
                          GROUP BY bill_id
                      ) payments ON b.id = payments.bill_id
                      WHERE b.bill_date BETWEEN ? AND ?
                      GROUP BY DATE(b.bill_date)
                      ORDER BY bill_date DESC";
            
            $stmt = $connection->prepare($query);
            $stmt->bind_param('ss', $dateFrom, $dateTo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $stmt->close();
            break;
            
        case 'pending':
            // Pending dues report
            $query = "SELECT 
                        b.id, b.bill_date, b.total_amount,
                        p.name as patient_name, p.patient_id, p.phone,
                        COALESCE(payments.paid_amount, 0) as paid_amount,
                        (b.total_amount - COALESCE(payments.paid_amount, 0)) as pending_amount,
                        DATEDIFF(CURDATE(), b.bill_date) as days_pending
                      FROM bills b
                      JOIN patients p ON b.patient_id = p.id
                      LEFT JOIN (
                          SELECT bill_id, SUM(amount) as paid_amount
                          FROM payments
                          GROUP BY bill_id
                      ) payments ON b.id = payments.bill_id
                      WHERE b.status IN ('Pending', 'Partial')
                      AND b.bill_date BETWEEN ? AND ?
                      ORDER BY days_pending DESC, pending_amount DESC";
            
            $stmt = $connection->prepare($query);
            $stmt->bind_param('ss', $dateFrom, $dateTo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $stmt->close();
            break;
            
        case 'payments':
            // Payment method report
            $query = "SELECT 
                        p.payment_method,
                        COUNT(*) as payment_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_amount
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY p.payment_method
                      ORDER BY total_amount DESC";
            
            $stmt = $connection->prepare($query);
            $stmt->bind_param('ss', $dateFrom, $dateTo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $stmt->close();
            break;
    }
    
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "An error occurred while generating the report.";
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
                        <h1 class="h3 mb-0">Billing Reports</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Billing</a></li>
                                <li class="breadcrumb-item active">Reports</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn btn-info">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display errors -->
                <?php if (isset($error)): ?>
                    <?php echo showErrorMessage($error); ?>
                <?php endif; ?>

                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter"></i> Report Filters
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end">
                            <div class="col-md-3">
                                <label for="type">Report Type</label>
                                <select class="form-control" id="type" name="type">
                                    <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                    <option value="daily" <?php echo $reportType == 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                                    <option value="pending" <?php echo $reportType == 'pending' ? 'selected' : ''; ?>>Pending Dues</option>
                                    <option value="payments" <?php echo $reportType == 'payments' ? 'selected' : ''; ?>>Payment Methods</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <?php if ($reportType == 'summary'): ?>
                    <!-- Summary Report -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie"></i> Billing Summary Report
                                <small class="text-muted">(<?php echo formatDate($dateFrom); ?> to <?php echo formatDate($dateTo); ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo $reportData['total_bills'] ?? 0; ?></h3>
                                            <p class="mb-0">Total Bills</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo formatCurrency($reportData['total_billed'] ?? 0); ?></h3>
                                            <p class="mb-0">Total Billed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo formatCurrency($reportData['total_collected'] ?? 0); ?></h3>
                                            <p class="mb-0">Total Collected</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo formatCurrency($reportData['total_pending'] ?? 0); ?></h3>
                                            <p class="mb-0">Total Pending</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6>Bill Status Distribution</h6>
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>Paid Bills</td>
                                            <td class="text-right"><?php echo $reportData['paid_bills'] ?? 0; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Partial Bills</td>
                                            <td class="text-right"><?php echo $reportData['partial_bills'] ?? 0; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Pending Bills</td>
                                            <td class="text-right"><?php echo $reportData['pending_bills'] ?? 0; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>Financial Metrics</h6>
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>Average Bill Amount</td>
                                            <td class="text-right"><?php echo formatCurrency($reportData['average_bill_amount'] ?? 0); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Collection Rate</td>
                                            <td class="text-right">
                                                <?php 
                                                $collectionRate = 0;
                                                if (($reportData['total_billed'] ?? 0) > 0) {
                                                    $collectionRate = (($reportData['total_collected'] ?? 0) / ($reportData['total_billed'] ?? 1)) * 100;
                                                }
                                                echo number_format($collectionRate, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pending Rate</td>
                                            <td class="text-right">
                                                <?php 
                                                $pendingRate = 0;
                                                if (($reportData['total_billed'] ?? 0) > 0) {
                                                    $pendingRate = (($reportData['total_pending'] ?? 0) / ($reportData['total_billed'] ?? 1)) * 100;
                                                }
                                                echo number_format($pendingRate, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($reportType == 'daily'): ?>
                    <!-- Daily Report -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-calendar-day"></i> Daily Billing Report
                                <small class="text-muted">(<?php echo formatDate($dateFrom); ?> to <?php echo formatDate($dateTo); ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($reportData)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Bills Count</th>
                                                <th>Total Billed</th>
                                                <th>Total Collected</th>
                                                <th>Collection Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData as $row): ?>
                                                <tr>
                                                    <td><?php echo formatDate($row['bill_date']); ?></td>
                                                    <td><?php echo $row['bills_count']; ?></td>
                                                    <td><?php echo formatCurrency($row['total_billed']); ?></td>
                                                    <td><?php echo formatCurrency($row['total_collected']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $rate = $row['total_billed'] > 0 ? ($row['total_collected'] / $row['total_billed']) * 100 : 0;
                                                        echo number_format($rate, 1) . '%';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Data Found</h5>
                                    <p class="text-muted">No billing data found for the selected date range.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($reportType == 'pending'): ?>
                    <!-- Pending Dues Report -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle"></i> Pending Dues Report
                                <small class="text-muted">(<?php echo formatDate($dateFrom); ?> to <?php echo formatDate($dateTo); ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($reportData)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Bill ID</th>
                                                <th>Patient</th>
                                                <th>Bill Date</th>
                                                <th>Total Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Pending Amount</th>
                                                <th>Days Pending</th>
                                                <th>Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData as $row): ?>
                                                <tr>
                                                    <td>
                                                        <a href="view.php?id=<?php echo $row['id']; ?>">
                                                            #<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($row['patient_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($row['patient_id']); ?></small>
                                                    </td>
                                                    <td><?php echo formatDate($row['bill_date']); ?></td>
                                                    <td><?php echo formatCurrency($row['total_amount']); ?></td>
                                                    <td class="text-success"><?php echo formatCurrency($row['paid_amount']); ?></td>
                                                    <td class="text-danger"><strong><?php echo formatCurrency($row['pending_amount']); ?></strong></td>
                                                    <td>
                                                        <span class="badge <?php echo $row['days_pending'] > 30 ? 'badge-danger' : ($row['days_pending'] > 7 ? 'badge-warning' : 'badge-info'); ?>">
                                                            <?php echo $row['days_pending']; ?> days
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h5 class="text-success">No Pending Dues</h5>
                                    <p class="text-muted">All bills are fully paid for the selected date range.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($reportType == 'payments'): ?>
                    <!-- Payment Methods Report -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-credit-card"></i> Payment Methods Report
                                <small class="text-muted">(<?php echo formatDate($dateFrom); ?> to <?php echo formatDate($dateTo); ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($reportData)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Payment Count</th>
                                                <th>Total Amount</th>
                                                <th>Average Amount</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalPayments = array_sum(array_column($reportData, 'total_amount'));
                                            foreach ($reportData as $row): 
                                            ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-<?php 
                                                        switch($row['payment_method']) {
                                                            case 'Cash': echo 'money-bill';
                                                                break;
                                                            case 'Card': echo 'credit-card';
                                                                break;
                                                            case 'UPI': echo 'mobile-alt';
                                                                break;
                                                            case 'Bank Transfer': echo 'university';
                                                                break;
                                                            case 'Cheque': echo 'file-invoice';
                                                                break;
                                                            default: echo 'money-bill';
                                                        }
                                                        ?>"></i>
                                                        <?php echo htmlspecialchars($row['payment_method']); ?>
                                                    </td>
                                                    <td><?php echo $row['payment_count']; ?></td>
                                                    <td><?php echo formatCurrency($row['total_amount']); ?></td>
                                                    <td><?php echo formatCurrency($row['average_amount']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $percentage = $totalPayments > 0 ? ($row['total_amount'] / $totalPayments) * 100 : 0;
                                                        echo number_format($percentage, 1) . '%';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="thead-light">
                                            <tr>
                                                <th>Total</th>
                                                <th><?php echo array_sum(array_column($reportData, 'payment_count')); ?></th>
                                                <th><?php echo formatCurrency($totalPayments); ?></th>
                                                <th>-</th>
                                                <th>100%</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Payments Found</h5>
                                    <p class="text-muted">No payments found for the selected date range.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .content-header, .btn, .card-header .btn {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include_once '../../includes/footer.php'; ?>