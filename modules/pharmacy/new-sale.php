<?php
/**
 * New Medicine Sale - Pharmacy Module
 * Hospital Management System
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Get and sanitize form data
    $medicine_id = sanitizeInput($_POST['medicine_id'] ?? '');
    $patient_id = sanitizeInput($_POST['patient_id'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit_price = sanitizeInput($_POST['unit_price'] ?? '');
    $sale_date = sanitizeInput($_POST['sale_date'] ?? '');
    $prescription_number = sanitizeInput($_POST['prescription_number'] ?? '');
    
    // Validate required fields
    $requiredFields = [
        'medicine_id' => $medicine_id,
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
    
    // Check medicine availability and get details
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
        } elseif ($medicine['quantity'] < (int)$quantity) {
            $errors[] = "Insufficient stock. Available: {$medicine['quantity']} units";
        }
        
        // Validate patient if provided
        if (!empty($patient_id)) {
            $patientQuery = "SELECT id FROM patients WHERE id = ?";
            $patientStmt = $connection->prepare($patientQuery);
            $patientStmt->bind_param('i', $patient_id);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            
            if ($patientResult->num_rows == 0) {
                $errors[] = 'Selected patient not found';
            }
            $patientStmt->close();
        }
        
        closeDBConnection($connection);
    }
    
    // If no errors, process the sale
    if (empty($errors)) {
        $connection = getDBConnection();
        
        try {
            // Start transaction
            $connection->autocommit(false);
            
            // Calculate total amount
            $total_amount = (float)$unit_price * (int)$quantity;
            
            // Insert sale record
            $saleData = [
                'medicine_id' => (int)$medicine_id,
                'patient_id' => !empty($patient_id) ? (int)$patient_id : null,
                'quantity' => (int)$quantity,
                'unit_price' => (float)$unit_price,
                'total_amount' => $total_amount,
                'sale_date' => $sale_date,
                'prescription_number' => !empty($prescription_number) ? $prescription_number : null
            ];
            
            $saleId = insertRecord('sales', $saleData);
            
            if (!$saleId) {
                throw new Exception('Failed to record sale');
            }
            
            // Update medicine quantity (this is handled by trigger, but we'll do it manually for safety)
            $updateQuery = "UPDATE medicines SET quantity = quantity - ? WHERE id = ?";
            $updateStmt = $connection->prepare($updateQuery);
            $updateStmt->bind_param('ii', $quantity, $medicine_id);
            $updateResult = $updateStmt->execute();
            $updateStmt->close();
            
            if (!$updateResult) {
                throw new Exception('Failed to update medicine stock');
            }
            
            // Commit transaction
            $connection->commit();
            $connection->autocommit(true);
            
            logActivity('Medicine Sale Recorded', "Sale ID: $saleId, Medicine: {$medicine['name']}, Quantity: $quantity");
            closeDBConnection($connection);
            
            redirectWithMessage('sales.php', 'Sale recorded successfully!', 'success');
            
        } catch (Exception $e) {
            // Rollback transaction
            $connection->rollback();
            $connection->autocommit(true);
            closeDBConnection($connection);
            
            error_log("Sale processing error: " . $e->getMessage());
            $errors[] = 'Failed to process sale. Please try again.';
        }
    }
}

// Get medicines for dropdown
$connection = getDBConnection();
$medicineQuery = "SELECT id, name, category, price, quantity FROM medicines WHERE quantity > 0 ORDER BY name";
$medicines = $connection->query($medicineQuery);

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
    <title>New Medicine Sale - Hospital Management System</title>
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
                            <h1><i class="fas fa-plus"></i> New Medicine Sale</h1>
                            <div class="page-actions">
                                <a href="sales.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Sales
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
                                <form method="POST" id="newSaleForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="medicine_id" class="form-label">Medicine <span class="text-danger">*</span></label>
                                                <select class="form-control" id="medicine_id" name="medicine_id" required>
                                                    <option value="">Select Medicine</option>
                                                    <?php while ($medicine = $medicines->fetch_assoc()): ?>
                                                        <option value="<?php echo $medicine['id']; ?>" 
                                                                data-price="<?php echo $medicine['price']; ?>"
                                                                data-stock="<?php echo $medicine['quantity']; ?>"
                                                                <?php echo (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $medicine['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($medicine['name']); ?>
                                                            <?php if ($medicine['category']): ?>
                                                                (<?php echo htmlspecialchars($medicine['category']); ?>)
                                                            <?php endif; ?>
                                                            - Stock: <?php echo $medicine['quantity']; ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="patient_id" class="form-label">Patient (Optional)</label>
                                                <select class="form-control" id="patient_id" name="patient_id">
                                                    <option value="">Walk-in Customer</option>
                                                    <?php while ($patient = $patients->fetch_assoc()): ?>
                                                        <option value="<?php echo $patient['id']; ?>"
                                                                <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($patient['name']); ?> 
                                                            (<?php echo htmlspecialchars($patient['patient_id']); ?>)
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" 
                                                       min="1" required>
                                                <small class="form-text text-muted" id="stockInfo">Available stock will be shown here</small>
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
                                                <label for="sale_date" class="form-label">Sale Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                                       value="<?php echo htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="prescription_number" class="form-label">Prescription Number</label>
                                                <input type="text" class="form-control" id="prescription_number" name="prescription_number" 
                                                       value="<?php echo htmlspecialchars($_POST['prescription_number'] ?? ''); ?>" 
                                                       maxlength="50" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="sales.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Record Sale
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
        // Update price and stock info when medicine is selected
        document.getElementById('medicine_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const stock = selectedOption.getAttribute('data-stock');
            
            if (price) {
                document.getElementById('unit_price').value = price;
                document.getElementById('stockInfo').textContent = `Available stock: ${stock} units`;
                document.getElementById('quantity').max = stock;
                calculateTotal();
            } else {
                document.getElementById('unit_price').value = '';
                document.getElementById('stockInfo').textContent = 'Available stock will be shown here';
                document.getElementById('quantity').removeAttribute('max');
            }
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
        document.getElementById('newSaleForm').addEventListener('submit', function(e) {
            const medicineSelect = document.getElementById('medicine_id');
            const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
            const availableStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const requestedQuantity = parseInt(document.getElementById('quantity').value) || 0;
            
            if (requestedQuantity > availableStock) {
                alert(`Insufficient stock. Available: ${availableStock} units`);
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