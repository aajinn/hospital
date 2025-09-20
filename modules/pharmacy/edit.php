<?php
/**
 * Edit Medicine - Pharmacy Module
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Get and sanitize form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price = sanitizeInput($_POST['price'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $min_quantity = sanitizeInput($_POST['min_quantity'] ?? '');
    $expiry_date = sanitizeInput($_POST['expiry_date'] ?? '');
    $supplier = sanitizeInput($_POST['supplier'] ?? '');
    $batch_number = sanitizeInput($_POST['batch_number'] ?? '');
    
    // Validate required fields
    $requiredFields = [
        'name' => $name,
        'price' => $price,
        'quantity' => $quantity,
        'min_quantity' => $min_quantity
    ];
    
    $errors = array_merge($errors, validateRequired($requiredFields));
    
    // Validate numeric fields
    if (!empty($price) && !validateNumeric($price, 0)) {
        $errors[] = 'Price must be a valid positive number';
    }
    
    if (!empty($quantity) && !validateNumeric($quantity, 0)) {
        $errors[] = 'Quantity must be a valid positive number';
    }
    
    if (!empty($min_quantity) && !validateNumeric($min_quantity, 0)) {
        $errors[] = 'Minimum quantity must be a valid positive number';
    }
    
    // Validate expiry date
    if (!empty($expiry_date) && !validateDate($expiry_date)) {
        $errors[] = 'Please enter a valid expiry date';
    }
    
    // Check if medicine name already exists (excluding current record)
    if (empty($errors)) {
        $connection = getDBConnection();
        $checkQuery = "SELECT id FROM medicines WHERE name = ? AND batch_number = ? AND id != ?";
        $checkStmt = $connection->prepare($checkQuery);
        $checkStmt->bind_param('ssi', $name, $batch_number, $medicineId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'Medicine with this name and batch number already exists';
        }
        
        $checkStmt->close();
        closeDBConnection($connection);
    }
    
    // If no errors, update the medicine
    if (empty($errors)) {
        $medicineData = [
            'name' => $name,
            'category' => $category,
            'price' => (float)$price,
            'quantity' => (int)$quantity,
            'min_quantity' => (int)$min_quantity,
            'expiry_date' => !empty($expiry_date) ? $expiry_date : null,
            'supplier' => $supplier,
            'batch_number' => $batch_number
        ];
        
        $result = updateRecord('medicines', $medicineData, ['id' => $medicineId]);
        
        if ($result) {
            logActivity('Medicine Updated', "Medicine: $name (ID: $medicineId)");
            redirectWithMessage('index.php', 'Medicine updated successfully!', 'success');
        } else {
            $errors[] = 'Failed to update medicine. Please try again.';
        }
    }
}

// Get existing categories for dropdown
$connection = getDBConnection();
$categoryQuery = "SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categoryResult = $connection->query($categoryQuery);
$existingCategories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $existingCategories[] = $row['category'];
}
closeDBConnection($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - Hospital Management System</title>
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
                            <h1><i class="fas fa-edit"></i> Edit Medicine</h1>
                            <div class="page-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <a href="view.php?id=<?php echo $medicineId; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
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
                                <h5 class="card-title mb-0">Medicine Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="editMedicineForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($_POST['name'] ?? $medicine['name']); ?>" 
                                                       required maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Category</label>
                                                <input type="text" class="form-control" id="category" name="category" 
                                                       value="<?php echo htmlspecialchars($_POST['category'] ?? $medicine['category']); ?>" 
                                                       list="categoryList" maxlength="50">
                                                <datalist id="categoryList">
                                                    <?php foreach ($existingCategories as $cat): ?>
                                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                                <small class="form-text text-muted">e.g., Antibiotics, Pain Relief, Vitamins</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="price" class="form-label">Price per Unit <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="price" name="price" 
                                                           value="<?php echo htmlspecialchars($_POST['price'] ?? $medicine['price']); ?>" 
                                                           step="0.01" min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Current Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? $medicine['quantity']); ?>" 
                                                       min="0" required>
                                                <small class="form-text text-muted">Current: <?php echo $medicine['quantity']; ?> units</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="min_quantity" class="form-label">Minimum Stock Level <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="min_quantity" name="min_quantity" 
                                                       value="<?php echo htmlspecialchars($_POST['min_quantity'] ?? $medicine['min_quantity']); ?>" 
                                                       min="0" required>
                                                <small class="form-text text-muted">Alert when stock falls below this level</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                                       value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? $medicine['expiry_date']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="supplier" class="form-label">Supplier</label>
                                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                                       value="<?php echo htmlspecialchars($_POST['supplier'] ?? $medicine['supplier']); ?>" 
                                                       maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="batch_number" class="form-label">Batch Number</label>
                                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                                       value="<?php echo htmlspecialchars($_POST['batch_number'] ?? $medicine['batch_number']); ?>" 
                                                       maxlength="50">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="index.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Update Medicine
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
        // Form validation
        document.getElementById('editMedicineForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const quantity = parseInt(document.getElementById('quantity').value);
            const minQuantity = parseInt(document.getElementById('min_quantity').value);
            
            if (price < 0) {
                alert('Price cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (quantity < 0) {
                alert('Quantity cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (minQuantity < 0) {
                alert('Minimum quantity cannot be negative');
                e.preventDefault();
                return;
            }
            
            // Check expiry date
            const expiryDate = document.getElementById('expiry_date').value;
            if (expiryDate) {
                const today = new Date();
                const expiry = new Date(expiryDate);
                
                if (expiry < today) {
                    if (!confirm('The expiry date is in the past. Are you sure you want to update this medicine?')) {
                        e.preventDefault();
                        return;
                    }
                }
            }
        });
    </script>
</body>
</html>