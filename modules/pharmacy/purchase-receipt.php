<?php
/**
 * Purchase Receipt - Pharmacy Module
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

$purchaseQuery = "SELECT p.*, m.name as medicine_name, m.category
                  FROM purchases p 
                  JOIN medicines m ON p.medicine_id = m.id 
                  WHERE p.id = ?";

$purchaseStmt = $connection->prepare($purchaseQuery);
$purchaseStmt->bind_param('i', $purchaseId);
$purchaseStmt->execute();
$purchase = $purchaseStmt->get_result()->fetch_assoc();
$purchaseStmt->close();

closeDBConnection($connection);

if (!$purchase) {
    redirectWithMessage('purchases.php', 'Purchase not found', 'error');
}

// Check if this is a print request
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Receipt - Hospital Management System</title>
    <?php if (!$isPrint): ?>
        <?php include '../../includes/header.php'; ?>
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            @media print {
                .no-print { display: none !important; }
                .print-only { display: block !important; }
                body { font-size: 12px; }
                .receipt-container { max-width: 600px; margin: 0 auto; }
            }
            .receipt-container { 
                max-width: 600px; 
                margin: 20px auto; 
                border: 1px solid #ddd; 
                padding: 20px;
                font-family: 'Courier New', monospace;
            }
            .receipt-header { text-align: center; margin-bottom: 20px; }
            .receipt-line { border-bottom: 1px dashed #ccc; margin: 10px 0; }
            .receipt-total { font-weight: bold; font-size: 1.1em; }
        </style>
    <?php endif; ?>
</head>
<body>
    <?php if (!$isPrint): ?>
        <div class="wrapper">
            <?php include '../../includes/sidebar.php'; ?>
            
            <div class="main-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-header no-print">
                                <h1><i class="fas fa-receipt"></i> Purchase Receipt</h1>
                                <div class="page-actions">
                                    <a href="purchases.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Purchases
                                    </a>
                                    <a href="?id=<?php echo $purchaseId; ?>&print=1" target="_blank" class="btn btn-primary">
                                        <i class="fas fa-print"></i> Print Receipt
                                    </a>
                                    <a href="edit-purchase.php?id=<?php echo $purchaseId; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit Purchase
                                    </a>
                                </div>
                            </div>

                            <?php echo displaySessionMessage(); ?>
    <?php endif; ?>

                            <div class="receipt-container">
                                <div class="receipt-header">
                                    <h4><strong>HOSPITAL PHARMACY</strong></h4>
                                    <p class="mb-1">Hospital Management System</p>
                                    <p class="mb-1">Medicine Purchase Receipt</p>
                                    <div class="receipt-line"></div>
                                </div>

                                <div class="receipt-body">
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Receipt No:</strong></div>
                                        <div class="col-6 text-end">#PUR<?php echo str_pad($purchase['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Purchase Date:</strong></div>
                                        <div class="col-6 text-end"><?php echo formatDate($purchase['purchase_date'], 'd-m-Y'); ?></div>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Record Time:</strong></div>
                                        <div class="col-6 text-end"><?php echo date('H:i:s', strtotime($purchase['created_at'])); ?></div>
                                    </div>

                                    <div class="receipt-line"></div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-12"><strong>SUPPLIER DETAILS</strong></div>
                                    </div>
                                    <div class="row mb-1">
                                        <div class="col-4">Name:</div>
                                        <div class="col-8"><?php echo htmlspecialchars($purchase['supplier']); ?></div>
                                    </div>
                                    <?php if ($purchase['invoice_number']): ?>
                                        <div class="row mb-2">
                                            <div class="col-4">Invoice:</div>
                                            <div class="col-8"><?php echo htmlspecialchars($purchase['invoice_number']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="receipt-line"></div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-12"><strong>MEDICINE DETAILS</strong></div>
                                    </div>
                                    
                                    <div class="row mb-1">
                                        <div class="col-6">Medicine:</div>
                                        <div class="col-6 text-end"><?php echo htmlspecialchars($purchase['medicine_name']); ?></div>
                                    </div>
                                    
                                    <?php if ($purchase['category']): ?>
                                        <div class="row mb-1">
                                            <div class="col-6">Category:</div>
                                            <div class="col-6 text-end"><?php echo htmlspecialchars($purchase['category']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($purchase['batch_number']): ?>
                                        <div class="row mb-1">
                                            <div class="col-6">Batch Number:</div>
                                            <div class="col-6 text-end"><?php echo htmlspecialchars($purchase['batch_number']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($purchase['expiry_date']): ?>
                                        <div class="row mb-1">
                                            <div class="col-6">Expiry Date:</div>
                                            <div class="col-6 text-end"><?php echo formatDate($purchase['expiry_date']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mb-1">
                                        <div class="col-6">Quantity:</div>
                                        <div class="col-6 text-end"><?php echo $purchase['quantity']; ?> units</div>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6">Unit Price:</div>
                                        <div class="col-6 text-end"><?php echo formatCurrency($purchase['unit_price']); ?></div>
                                    </div>

                                    <div class="receipt-line"></div>
                                    
                                    <div class="row receipt-total">
                                        <div class="col-6">TOTAL AMOUNT:</div>
                                        <div class="col-6 text-end"><?php echo formatCurrency($purchase['total_amount']); ?></div>
                                    </div>
                                    
                                    <div class="receipt-line"></div>
                                    
                                    <div class="text-center mt-3">
                                        <p class="mb-1"><small>Purchase recorded successfully</small></p>
                                        <p class="mb-1"><small>Medicine stock has been updated</small></p>
                                        <?php if ($isPrint): ?>
                                            <p class="mb-0"><small>Printed on: <?php echo date('d-m-Y H:i:s'); ?></small></p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!$isPrint): ?>
                                        <div class="receipt-line"></div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <h6><strong>PURCHASE SUMMARY</strong></h6>
                                                <table class="table table-sm table-borderless">
                                                    <tr>
                                                        <td><strong>Medicine Added to Inventory:</strong></td>
                                                        <td><?php echo $purchase['quantity']; ?> units</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Total Investment:</strong></td>
                                                        <td><?php echo formatCurrency($purchase['total_amount']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Cost per Unit:</strong></td>
                                                        <td><?php echo formatCurrency($purchase['unit_price']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Purchase Date:</strong></td>
                                                        <td><?php echo formatDate($purchase['purchase_date'], 'd-m-Y'); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

    <?php if (!$isPrint): ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../includes/footer.php'; ?>
    <?php else: ?>
        <script>
            // Auto-print when page loads
            window.onload = function() {
                window.print();
            };
        </script>
    <?php endif; ?>
</body>
</html>