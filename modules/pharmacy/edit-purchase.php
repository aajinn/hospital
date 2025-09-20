<?php
/**
 * Edit Medicine Purchase - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get purchase ID
$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchaseId <= 0) {
    redirectWithMessage('purchases.php', 'Invalid purchase ID', 'error');
}

// Get purchase details
$connection = getDBConnection();

$purchaseQuery = "SELECT p.*, m.name as medicine_name, m.quantity as current_stock
                  FROM purchases p 
                  JOIN medicines m ON p.medicine_id = m.id 
                  WHERE p.id = ?";

$purchaseStmt = $connection->prepare($purchaseQuery);
$purchaseStmt->bind_param('i', $purchaseId);
$purchaseStmt->execute();
$purchase = $purchaseStmt->get_result()->fetch_assoc();
$purchaseStmt->close();

if (!$purchase) {
    closeDBConnection($connection);
    redirectWithMessage('purchases.php', 'Purchase not found', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Get and sanitize form data
    $supplier = sanitizeInput($_POST['supplier'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit_price = sanitizeInput($_POST['unit_price'] ?? '');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $batch_number = sanitizeInput($_POST['batch_number'] ?? '');
    $expiry_date = sanitizeInput($_POST['expiry_date'] ?? '');
    $invoice_number = sanitizeInput($_POST['invoice_number'] ?? '');
    
    // Validate required fields
    $requiredFields = [
        'supplier' => $supplier,
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'purchase_date' => $purchase_date
    ];
    
    $errors = array_merge($errors, validateRequired($requiredFields));
    
    // Validate numeric fields
    if (!empty($quantity) && (!validateNumeric($quantity, 1) || (int)$quantity != $quantity)) {
        $errors[] = 'Quantity must be a valid positive integer';
    }
    
    if (!empty($unit_price) && !validateNumeric($unit_price, 0)) {
        $errors[] = 'Unit price must be a valid positive number';
    }
    
    // Validate dates
    if (!empty($purchase_date) && !validateDate($purchase_date)) {
        $errors[] = 'Please enter a valid purchase date';
    }
    
    if (!empty($expiry_date) && !validateDate($expiry_date)) {
        $errors[] = 'Please enter a valid expiry date';
    }
    
    // Check stock implications (if quantity is reduced, ensure we don't go negative)
    if (empty($errors) && !empty($quantity)) {
        $quantityDifference = (int)$quantity - $purchase['quantity'];
        
        // If reducing quantity, check if we have enough stock
        if ($quantityDifference < 0) {
            $reductionAmount = abs($quantityDifference);
            if ($purchase['current_stock'] < $reductionAmount) {
                $errors[] = "Cannot reduce quantity by $reductionAmount units. Current stock: {$purchase['current_stock']} units";
            }
        }
    }
    
    // If no errors, update the purchase
    if (empty($errors)) {
        try {
            // Start transaction
            $connection->autocommit(false);
            
            // Calculate new total amount
            $total_amount = (float)$unit_price * (int)$quantity;
            
            // Calculate stock adjustment needed
            $quantityDifference = (int)$quantity - $purchase['quantity'];
            
            // Update purchase record
            $purchaseData = [
                'supplier' => $supplier,
                'quantity' => (int)$quantity,
                'unit_price' => (float)$unit_price,
                'total_amount' => $total_amount,
                'purchase_date' => $purchase_date,
                'batch_number' => !empty($batch_number) ? $batch_number : null,
                'expiry_date' => !empty($expiry_date) ? $expiry_date : null,
                'invoice_number' => !empty($invoice_number) ? $invoice_number : null
            ];
            
            $result = updateRecord('purchases', $purchaseData, ['id' => $purchaseId]);
            
            if (!$result) {
                throw new Exception('Failed to update purchase');
            }
            
            // Adjust medicine stock if quantity changed
            if ($quantityDifference != 0) {
                $stockUpdateQuery = "UPDATE medicines SET quantity = quantity + ? WHERE id = ?";
                $stockStmt = $connection->prepare($stockUpdateQuery);
                $stockStmt->bind_param('ii', $quantityDifference, $purchase['medicine_id']);
                $stockResult = $stockStmt->execute();
                $stockStmt->close();
                
                if (!$stockResult) {
                    throw new Exception('Failed to update medicine stock');
                }
            }
            
            // Update medicine details if provided
            $updateData = [];
            if (!empty($batch_number)) {
                $updateData['batch_number'] = $batch_number;
            }
            if (!empty($expiry_date)) {
                $updateData['expiry_date'] = $expiry_date;
            }
            if (!empty($supplier)) {
                $updateData['supplier'] = $supplier;
            }
            
            if (!empty($updateData)) {
                updateRecord('medicines', $updateData, ['id' => $purchase['medicine_id']]);
            }
            
            // Commit transaction
            $connection->commit();
            $connection->autocommit(true);
            
            logActivity('Medicine Purchase Updated', "Purchase ID: $purchaseId, Medicine: {$purchase['medicine_name']}");
            closeDBConnection($connection);
            
            redirectWithMessage('purchases.php', 'Purchase updated successfully!', 'success');
            
        } catch (Exception $e) {
            // Rollback transaction
            $connection->rollback();
            $connection->autocommit(true);
            
            error_log("Purchase update error: " . $e->getMessage());
            $errors[] = 'Failed to update purchase. Please try again.';
        }
    }
}

// Get recent suppliers for dropdown
$supplierQuery = "SELECT DISTINCT supplier FROM purchases WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier LIMIT 20";
$supplierResult = $connection->query($supplierQuery);
$recentSuppliers = [];
while ($row = $supplierResult->fetch_assoc()) {
    $recentSuppliers[] = $row['supplier'];
}

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine Purchase - Hospital Management System</title>
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
                            <h1><i class="fas fa-edit"></i> Edit Medicine Purchase</h1>
                            <div class="page-actions">
                                <a href="purchases.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Purchases
                                </a>
                                <a href="purchase-receipt.php?id=<?php echo $purchaseId; ?>" class="btn btn-info">
                                    <i class="fas fa-receipt"></i> View Receipt
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-circle"></i> Please correct the following errors:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Purchase Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="editPurchaseForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Medicine</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo htmlspecialchars($purchase['medicine_name']); ?>" 
                                                       readonly>
                                                <small class="form-text text-muted">Medicine cannot be changed after purchase</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Medicine Stock</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo $purchase['current_stock']; ?> units" 
                                                       readonly>
                                                <small class="form-text text-muted">Available in inventory</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="supplier" class="form-label">Supplier <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                                       value="<?php echo htmlspecialchars($_POST['supplier'] ?? $purchase['supplier']); ?>" 
                                                       list="supplierList" required maxlength="100">
                                                <datalist id="supplierList">
                                                    <?php foreach ($recentSuppliers as $supplier): ?>
                                                        <option value="<?php echo htmlspecialchars($supplier); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                                <input type="text" class="form-control" id="invoice_number" name="invoice_number" 
                                                       value="<?php echo htmlspecialchars($_POST['invoice_number'] ?? $purchase['invoice_number']); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? $purchase['quantity']); ?>" 
                                                       min="1" required>
                                                <small class="form-text text-muted">Original: <?php echo $purchase['quantity']; ?> units</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="unit_price" class="form-label">Unit Price <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="unit_price" name="unit_price" 
                                                           value="<?php echo htmlspecialchars($_POST['unit_price'] ?? $purchase['unit_price']); ?>" 
                                                           step="0.01" min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="total_amount" class="form-label">Total Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="text" class="form-control" id="total_amount" readonly>
                                                </div>
                                                <small class="form-text text-muted">Original: <?php echo formatCurrency($purchase['total_amount']); ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                                       value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? $purchase['purchase_date']); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="batch_number" class="form-label">Batch Number</label>
                                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                                       value="<?php echo htmlspecialchars($_POST['batch_number'] ?? $purchase['batch_number']); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                                       value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? $purchase['expiry_date']); ?>" 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Important Notes:</h6>
                                        <ul class="mb-0">
                                            <li>Medicine cannot be changed after purchase creation</li>
                                            <li>Stock will be automatically adjusted based on quantity changes</li>
                                            <li>Reducing quantity below current stock may not be possible if medicine has been sold</li>
                                            <li>Original purchase details are shown for reference</li>
                                        </ul>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="purchases.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Update Purchase
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Calculate total amount
        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const unitPrice = parseFloat(document.getElementById('unit_price').value) || 0;
            const total = quantity * unitPrice;
            
            document.getElementById('total_amount').value = total.toFixed(2);
        }

        // Add event listeners for calculation
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('unit_price').addEventListener('input', calculateTotal);

        // Form validation
        document.getElementById('editPurchaseForm').addEventListener('submit', function(e) {
            const unitPrice = parseFloat(document.getElementById('unit_price').value);
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (unitPrice <= 0) {
                alert('Unit price must be greater than 0');
                e.preventDefault();
                return;
            }
            
            if (quantity <= 0) {
                alert('Quantity must be greater than 0');
                e.preventDefault();
                return;
            }
            
            // Check expiry date
            const expiryDate = document.getElementById('expiry_date').value;
            if (expiryDate) {
                const today = new Date();
                const expiry = new Date(expiryDate);
                
                if (expiry <= today) {
                    if (!confirm('The expiry date is today or in the past. Are you sure you want to update this purchase?')) {
                        e.preventDefault();
                        return;
                    }
                }
            }
        });

        // Initialize calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>