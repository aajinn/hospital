<?php
/**
 * Record Medicine Purchase - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Get pre-selected medicine if provided
$preSelectedMedicine = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Get and sanitize form data
    $medicine_id = sanitizeInput($_POST['medicine_id'] ?? '');
    $supplier = sanitizeInput($_POST['supplier'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit_price = sanitizeInput($_POST['unit_price'] ?? '');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $batch_number = sanitizeInput($_POST['batch_number'] ?? '');
    $expiry_date = sanitizeInput($_POST['expiry_date'] ?? '');
    $invoice_number = sanitizeInput($_POST['invoice_number'] ?? '');
    
    // Validate required fields
    $requiredFields = [
        'medicine_id' => $medicine_id,
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
    
    // Validate medicine exists
    if (empty($errors) && !empty($medicine_id)) {
        $connection = getDBConnection();
        
        $medicineQuery = "SELECT * FROM medicines WHERE id = ?";
        $medicineStmt = $connection->prepare($medicineQuery);
        $medicineStmt->bind_param('i', $medicine_id);
        $medicineStmt->execute();
        $medicine = $medicineStmt->get_result()->fetch_assoc();
        $medicineStmt->close();
        
        if (!$medicine) {
            $errors[] = 'Selected medicine not found';
        }
        
        closeDBConnection($connection);
    }
    
    // If no errors, process the purchase
    if (empty($errors)) {
        $connection = getDBConnection();
        
        try {
            // Start transaction
            $connection->autocommit(false);
            
            // Calculate total amount
            $total_amount = (float)$unit_price * (int)$quantity;
            
            // Insert purchase record
            $purchaseData = [
                'medicine_id' => (int)$medicine_id,
                'supplier' => $supplier,
                'quantity' => (int)$quantity,
                'unit_price' => (float)$unit_price,
                'total_amount' => $total_amount,
                'purchase_date' => $purchase_date,
                'batch_number' => !empty($batch_number) ? $batch_number : null,
                'expiry_date' => !empty($expiry_date) ? $expiry_date : null,
                'invoice_number' => !empty($invoice_number) ? $invoice_number : null
            ];
            
            $purchaseId = insertRecord('purchases', $purchaseData);
            
            if (!$purchaseId) {
                throw new Exception('Failed to record purchase');
            }
            
            // Update medicine quantity (this is handled by trigger, but we'll do it manually for safety)
            $updateQuery = "UPDATE medicines SET quantity = quantity + ? WHERE id = ?";
            $updateStmt = $connection->prepare($updateQuery);
            $updateStmt->bind_param('ii', $quantity, $medicine_id);
            $updateResult = $updateStmt->execute();
            $updateStmt->close();
            
            if (!$updateResult) {
                throw new Exception('Failed to update medicine stock');
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
                updateRecord('medicines', $updateData, ['id' => $medicine_id]);
            }
            
            // Commit transaction
            $connection->commit();
            $connection->autocommit(true);
            
            logActivity('Medicine Purchase Recorded', "Purchase ID: $purchaseId, Medicine: {$medicine['name']}, Quantity: $quantity");
            closeDBConnection($connection);
            
            redirectWithMessage('purchases.php', 'Purchase recorded successfully!', 'success');
            
        } catch (Exception $e) {
            // Rollback transaction
            $connection->rollback();
            $connection->autocommit(true);
            closeDBConnection($connection);
            
            error_log("Purchase processing error: " . $e->getMessage());
            $errors[] = 'Failed to process purchase. Please try again.';
        }
    }
}

// Get medicines for dropdown
$connection = getDBConnection();
$medicineQuery = "SELECT id, name, category, price, supplier FROM medicines ORDER BY name";
$medicines = $connection->query($medicineQuery);

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
    <title>Record Medicine Purchase - Hospital Management System</title>
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
                            <h1><i class="fas fa-shopping-cart"></i> Record Medicine Purchase</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="purchases.php" class="btn btn-info">
                                    <i class="fas fa-list"></i> View Purchases
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
                                <form method="POST" id="purchaseForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="medicine_id" class="form-label">Medicine <span class="text-danger">*</span></label>
                                                <select class="form-control" id="medicine_id" name="medicine_id" required>
                                                    <option value="">Select Medicine</option>
                                                    <?php while ($medicine = $medicines->fetch_assoc()): ?>
                                                        <option value="<?php echo $medicine['id']; ?>" 
                                                                data-price="<?php echo $medicine['price']; ?>"
                                                                data-supplier="<?php echo htmlspecialchars($medicine['supplier'] ?? ''); ?>"
                                                                <?php echo (($preSelectedMedicine == $medicine['id']) || (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $medicine['id'])) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($medicine['name']); ?>
                                                            <?php if ($medicine['category']): ?>
                                                                (<?php echo htmlspecialchars($medicine['category']); ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="supplier" class="form-label">Supplier <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                                       value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>" 
                                                       list="supplierList" required maxlength="100">
                                                <datalist id="supplierList">
                                                    <?php foreach ($recentSuppliers as $supplier): ?>
                                                        <option value="<?php echo htmlspecialchars($supplier); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" 
                                                       min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="unit_price" class="form-label">Unit Price <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="unit_price" name="unit_price" 
                                                           value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>" 
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
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                                       value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="batch_number" class="form-label">Batch Number</label>
                                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                                       value="<?php echo htmlspecialchars($_POST['batch_number'] ?? ''); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                                       value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>" 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                                <input type="text" class="form-control" id="invoice_number" name="invoice_number" 
                                                       value="<?php echo htmlspecialchars($_POST['invoice_number'] ?? ''); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Important Notes:</h6>
                                        <ul class="mb-0">
                                            <li>Medicine stock will be automatically updated after recording the purchase</li>
                                            <li>Batch number and expiry date will update the medicine record if provided</li>
                                            <li>Supplier information will be saved for future reference</li>
                                        </ul>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="index.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Record Purchase
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
        // Update supplier and price when medicine is selected
        document.getElementById('medicine_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const supplier = selectedOption.getAttribute('data-supplier');
            
            if (price) {
                document.getElementById('unit_price').value = price;
            }
            
            if (supplier && !document.getElementById('supplier').value) {
                document.getElementById('supplier').value = supplier;
            }
            
            calculateTotal();
        });

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
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
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
                    if (!confirm('The expiry date is today or in the past. Are you sure you want to record this purchase?')) {
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