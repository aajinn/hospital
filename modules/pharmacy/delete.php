<?php
/**
 * Delete Medicine - Pharmacy Module
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

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    $connection = getDBConnection();
    
    try {
        // Check if medicine has any sales or purchases
        $salesCheck = "SELECT COUNT(*) as sales_count FROM sales WHERE medicine_id = ?";
        $salesStmt = $connection->prepare($salesCheck);
        $salesStmt->bind_param('i', $medicineId);
        $salesStmt->execute();
        $salesCount = $salesStmt->get_result()->fetch_assoc()['sales_count'];
        $salesStmt->close();
        
        $purchaseCheck = "SELECT COUNT(*) as purchase_count FROM purchases WHERE medicine_id = ?";
        $purchaseStmt = $connection->prepare($purchaseCheck);
        $purchaseStmt->bind_param('i', $medicineId);
        $purchaseStmt->execute();
        $purchaseCount = $purchaseStmt->get_result()->fetch_assoc()['purchase_count'];
        $purchaseStmt->close();
        
        if ($salesCount > 0 || $purchaseCount > 0) {
            closeDBConnection($connection);
            redirectWithMessage('index.php', 'Cannot delete medicine with existing sales or purchase records', 'error');
        }
        
        // Delete the medicine
        $result = deleteRecord('medicines', ['id' => $medicineId]);
        
        if ($result) {
            logActivity('Medicine Deleted', "Medicine: {$medicine['name']} (ID: $medicineId)");
            closeDBConnection($connection);
            redirectWithMessage('index.php', 'Medicine deleted successfully!', 'success');
        } else {
            closeDBConnection($connection);
            redirectWithMessage('index.php', 'Failed to delete medicine. Please try again.', 'error');
        }
        
    } catch (Exception $e) {
        closeDBConnection($connection);
        error_log("Delete medicine error: " . $e->getMessage());
        redirectWithMessage('index.php', 'An error occurred while deleting the medicine.', 'error');
    }
}

// Get related records count for display
$connection = getDBConnection();

$salesQuery = "SELECT COUNT(*) as sales_count FROM sales WHERE medicine_id = ?";
$salesStmt = $connection->prepare($salesQuery);
$salesStmt->bind_param('i', $medicineId);
$salesStmt->execute();
$salesCount = $salesStmt->get_result()->fetch_assoc()['sales_count'];
$salesStmt->close();

$purchaseQuery = "SELECT COUNT(*) as purchase_count FROM purchases WHERE medicine_id = ?";
$purchaseStmt = $connection->prepare($purchaseQuery);
$purchaseStmt->bind_param('i', $medicineId);
$purchaseStmt->execute();
$purchaseCount = $purchaseStmt->get_result()->fetch_assoc()['purchase_count'];
$purchaseStmt->close();

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Medicine - Hospital Management System</title>
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
                            <h1><i class="fas fa-trash"></i> Delete Medicine</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="view.php?id=<?php echo $medicineId; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>

                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($salesCount > 0 || $purchaseCount > 0): ?>
                                            <div class="alert alert-danger">
                                                <h6><i class="fas fa-ban"></i> Cannot Delete Medicine</h6>
                                                <p class="mb-0">
                                                    This medicine cannot be deleted because it has associated records:
                                                </p>
                                                <ul class="mt-2 mb-0">
                                                    <?php if ($salesCount > 0): ?>
                                                        <li><?php echo $salesCount; ?> sales record(s)</li>
                                                    <?php endif; ?>
                                                    <?php if ($purchaseCount > 0): ?>
                                                        <li><?php echo $purchaseCount; ?> purchase record(s)</li>
                                                    <?php endif; ?>
                                                </ul>
                                                <p class="mt-2 mb-0">
                                                    <small>To maintain data integrity, medicines with transaction history cannot be deleted.</small>
                                                </p>
                                            </div>
                                            
                                            <div class="text-center">
                                                <a href="index.php" class="btn btn-secondary">
                                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                                </a>
                                                <a href="edit.php?id=<?php echo $medicineId; ?>" class="btn btn-warning">
                                                    <i class="fas fa-edit"></i> Edit Medicine Instead
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <h6><i class="fas fa-exclamation-triangle"></i> Warning</h6>
                                                <p class="mb-0">
                                                    You are about to permanently delete this medicine from the inventory. 
                                                    This action cannot be undone.
                                                </p>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Medicine Details:</h6>
                                                    <table class="table table-borderless table-sm">
                                                        <tr>
                                                            <td><strong>Name:</strong></td>
                                                            <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Category:</strong></td>
                                                            <td><?php echo htmlspecialchars($medicine['category'] ?? '-'); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Price:</strong></td>
                                                            <td><?php echo formatCurrency($medicine['price']); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Current Stock:</strong></td>
                                                            <td><?php echo $medicine['quantity']; ?> units</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Additional Information:</h6>
                                                    <table class="table table-borderless table-sm">
                                                        <tr>
                                                            <td><strong>Supplier:</strong></td>
                                                            <td><?php echo htmlspecialchars($medicine['supplier'] ?? '-'); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Batch Number:</strong></td>
                                                            <td><?php echo htmlspecialchars($medicine['batch_number'] ?? '-'); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Expiry Date:</strong></td>
                                                            <td><?php echo $medicine['expiry_date'] ? formatDate($medicine['expiry_date']) : '-'; ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Added On:</strong></td>
                                                            <td><?php echo formatDate($medicine['created_at'], 'd-m-Y'); ?></td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>

                                            <hr>

                                            <form method="POST" id="deleteForm">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                                                    <label class="form-check-label" for="confirmCheck">
                                                        I understand that this action cannot be undone and I want to permanently delete this medicine.
                                                    </label>
                                                </div>

                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="index.php" class="btn btn-secondary">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                    <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                                        <i class="fas fa-trash"></i> Delete Medicine
                                                    </button>
                                                </div>
                                            </form>
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

    <script>
        // Enable/disable delete button based on checkbox
        document.getElementById('confirmCheck')?.addEventListener('change', function() {
            document.getElementById('deleteBtn').disabled = !this.checked;
        });

        // Confirm deletion
        document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure you want to delete this medicine? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>