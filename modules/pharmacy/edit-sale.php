<?php
/**
 * Edit Medicine Sale - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get sale ID
$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($saleId <= 0) {
    redirectWithMessage('sales.php', 'Invalid sale ID', 'error');
}

// Get sale details
$connection = getDBConnection();

$saleQuery = "SELECT s.*, m.name as medicine_name, m.quantity as current_stock
              FROM sales s 
              JOIN medicines m ON s.medicine_id = m.id 
              WHERE s.id = ?";

$saleStmt = $connection->prepare($saleQuery);
$saleStmt->bind_param('i', $saleId);
$saleStmt->execute();
$sale = $saleStmt->get_result()->fetch_assoc();
$saleStmt->close();

if (!$sale) {
    closeDBConnection($connection);
    redirectWithMessage('sales.php', 'Sale not found', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Get and sanitize form data
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit_price = sanitizeInput($_POST['unit_price'] ?? '');
    $sale_date = sanitizeInput($_POST['sale_date'] ?? '');
    $prescription_number = sanitizeInput($_POST['prescription_number'] ?? '');
    
    // Validate required fields
    $requiredFields = [
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'sale_date' => $sale_date
    ];
    
    $errors = array_merge($errors, validateRequired($requiredFields));
    
    // Validate numeric fields
    if (!empty($quantity) && (!validateNumeric($quantity, 1) || (int)$quantity != $quantity)) {
        $errors[] = 'Quantity must be a valid positive integer';
    }
    
    if (!empty($unit_price) && !validateNumeric($unit_price, 0)) {
        $errors[] = 'Unit price must be a valid positive number';
    }
    
    // Validate date
    if (!empty($sale_date) && !validateDate($sale_date)) {
        $errors[] = 'Please enter a valid sale date';
    }
    
    // Check stock availability (considering the current sale quantity)
    if (empty($errors) && !empty($quantity)) {
        $quantityDifference = (int)$quantity - $sale['quantity'];
        $availableStock = $sale['current_stock'] + $sale['quantity']; // Add back the original sale quantity
        
        if ((int)$quantity > $availableStock) {
            $errors[] = "Insufficient stock. Available: $availableStock units (including current sale)";
        }
    }
    
    // If no errors, update the sale
    if (empty($errors)) {
        try {
            // Start transaction
            $connection->autocommit(false);
            
            // Calculate new total amount
            $total_amount = (float)$unit_price * (int)$quantity;
            
            // Calculate stock adjustment needed
            $quantityDifference = (int)$quantity - $sale['quantity'];
            
            // Update sale record
            $saleData = [
                'quantity' => (int)$quantity,
                'unit_price' => (float)$unit_price,
                'total_amount' => $total_amount,
                'sale_date' => $sale_date,
                'prescription_number' => !empty($prescription_number) ? $prescription_number : null
            ];
            
            $result = updateRecord('sales', $saleData, ['id' => $saleId]);
            
            if (!$result) {
                throw new Exception('Failed to update sale');
            }
            
            // Adjust medicine stock if quantity changed
            if ($quantityDifference != 0) {
                $stockUpdateQuery = "UPDATE medicines SET quantity = quantity - ? WHERE id = ?";
                $stockStmt = $connection->prepare($stockUpdateQuery);
                $stockStmt->bind_param('ii', $quantityDifference, $sale['medicine_id']);
                $stockResult = $stockStmt->execute();
                $stockStmt->close();
                
                if (!$stockResult) {
                    throw new Exception('Failed to update medicine stock');
                }
            }
            
            // Commit transaction
            $connection->commit();
            $connection->autocommit(true);
            
            logActivity('Medicine Sale Updated', "Sale ID: $saleId, Medicine: {$sale['medicine_name']}");
            closeDBConnection($connection);
            
            redirectWithMessage('sales.php', 'Sale updated successfully!', 'success');
            
        } catch (Exception $e) {
            // Rollback transaction
            $connection->rollback();
            $connection->autocommit(true);
            
            error_log("Sale update error: " . $e->getMessage());
            $errors[] = 'Failed to update sale. Please try again.';
        }
    }
}

// Get patients for dropdown (optional)
$patientQuery = "SELECT id, name, patient_id FROM patients ORDER BY name LIMIT 100";
$patients = $connection->query($patientQuery);

closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine Sale - Hospital Management System</title>
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
                            <h1><i class="fas fa-edit"></i> Edit Medicine Sale</h1>
                            <div class="page-actions">
                                <a href="sales.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Sales
                                </a>
                                <a href="sale-receipt.php?id=<?php echo $saleId; ?>" class="btn btn-info">
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
                                <h5 class="card-title mb-0">Sale Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="editSaleForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Medicine</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo htmlspecialchars($sale['medicine_name']); ?>" 
                                                       readonly>
                                                <small class="form-text text-muted">Medicine cannot be changed after sale</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Available Stock</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo ($sale['current_stock'] + $sale['quantity']); ?> units (including current sale)" 
                                                       readonly>
                                                <small class="form-text text-muted">Current stock + original sale quantity</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? $sale['quantity']); ?>" 
                                                       min="1" max="<?php echo ($sale['current_stock'] + $sale['quantity']); ?>" required>
                                                <small class="form-text text-muted">Original: <?php echo $sale['quantity']; ?> units</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="unit_price" class="form-label">Unit Price <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="unit_price" name="unit_price" 
                                                           value="<?php echo htmlspecialchars($_POST['unit_price'] ?? $sale['unit_price']); ?>" 
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
                                                <small class="form-text text-muted">Original: <?php echo formatCurrency($sale['total_amount']); ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="sale_date" class="form-label">Sale Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                                       value="<?php echo htmlspecialchars($_POST['sale_date'] ?? $sale['sale_date']); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="prescription_number" class="form-label">Prescription Number</label>
                                                <input type="text" class="form-control" id="prescription_number" name="prescription_number" 
                                                       value="<?php echo htmlspecialchars($_POST['prescription_number'] ?? $sale['prescription_number']); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Important Notes:</h6>
                                        <ul class="mb-0">
                                            <li>Medicine and patient cannot be changed after sale creation</li>
                                            <li>Stock will be automatically adjusted based on quantity changes</li>
                                            <li>Original sale details are shown for reference</li>
                                        </ul>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="sales.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Update Sale
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
        document.getElementById('editSaleForm').addEventListener('submit', function(e) {
            const maxStock = parseInt(document.getElementById('quantity').getAttribute('max'));
            const requestedQuantity = parseInt(document.getElementById('quantity').value) || 0;
            
            if (requestedQuantity > maxStock) {
                alert(`Insufficient stock. Maximum available: ${maxStock} units`);
                e.preventDefault();
                return;
            }
            
            const unitPrice = parseFloat(document.getElementById('unit_price').value);
            if (unitPrice <= 0) {
                alert('Unit price must be greater than 0');
                e.preventDefault();
                return;
            }
            
            if (requestedQuantity <= 0) {
                alert('Quantity must be greater than 0');
                e.preventDefault();
                return;
            }
        });

        // Initialize calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>