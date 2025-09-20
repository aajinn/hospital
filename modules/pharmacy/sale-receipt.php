<?php
/**
 * Sale Receipt - Pharmacy Module
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

$saleQuery = "SELECT s.*, m.name as medicine_name, m.category, m.batch_number,
                     p.name as patient_name, p.patient_id, p.phone, p.address
              FROM sales s 
              JOIN medicines m ON s.medicine_id = m.id 
              LEFT JOIN patients p ON s.patient_id = p.id 
              WHERE s.id = ?";

$saleStmt = $connection->prepare($saleQuery);
$saleStmt->bind_param('i', $saleId);
$saleStmt->execute();
$sale = $saleStmt->get_result()->fetch_assoc();
$saleStmt->close();

closeDBConnection($connection);

if (!$sale) {
    redirectWithMessage('sales.php', 'Sale not found', 'error');
}

// Check if this is a print request
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Receipt - Hospital Management System</title>
    <?php if (!$isPrint): ?>
        <?php include '../../includes/header.php'; ?>
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            @media print {
                .no-print { display: none !important; }
                .print-only { display: block !important; }
                body { font-size: 12px; }
                .receipt-container { max-width: 400px; margin: 0 auto; }
            }
            .receipt-container { 
                max-width: 400px; 
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
                                <h1><i class="fas fa-receipt"></i> Sale Receipt</h1>
                                <div class="page-actions">
                                    <a href="sales.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Sales
                                    </a>
                                    <a href="?id=<?php echo $saleId; ?>&print=1" target="_blank" class="btn btn-primary">
                                        <i class="fas fa-print"></i> Print Receipt
                                    </a>
                                    <a href="edit-sale.php?id=<?php echo $saleId; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit Sale
                                    </a>
                                </div>
                            </div>

                            <?php echo displaySessionMessage(); ?>
    <?php endif; ?>

                            <div class="receipt-container">
                                <div class="receipt-header">
                                    <h4><strong>HOSPITAL PHARMACY</strong></h4>
                                    <p class="mb-1">Hospital Management System</p>
                                    <p class="mb-1">Medicine Sale Receipt</p>
                                    <div class="receipt-line"></div>
                                </div>

                                <div class="receipt-body">
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Receipt No:</strong></div>
                                        <div class="col-6 text-end">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Date:</strong></div>
                                        <div class="col-6 text-end"><?php echo formatDate($sale['sale_date'], 'd-m-Y'); ?></div>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Time:</strong></div>
                                        <div class="col-6 text-end"><?php echo date('H:i:s', strtotime($sale['created_at'])); ?></div>
                                    </div>

                                    <?php if ($sale['patient_name']): ?>
                                        <div class="receipt-line"></div>
                                        <div class="row mb-2">
                                            <div class="col-12"><strong>PATIENT DETAILS</strong></div>
                                        </div>
                                        <div class="row mb-1">
                                            <div class="col-4">Name:</div>
                                            <div class="col-8"><?php echo htmlspecialchars($sale['patient_name']); ?></div>
                                        </div>
                                        <div class="row mb-1">
                                            <div class="col-4">ID:</div>
                                            <div class="col-8"><?php echo htmlspecialchars($sale['patient_id']); ?></div>
                                        </div>
                                        <?php if ($sale['phone']): ?>
                                            <div class="row mb-2">
                                                <div class="col-4">Phone:</div>
                                                <div class="col-8"><?php echo htmlspecialchars($sale['phone']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="row mb-2">
                                            <div class="col-12"><strong>Customer:</strong> Walk-in</div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="receipt-line"></div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-12"><strong>MEDICINE DETAILS</strong></div>
                                    </div>
                                    
                                    <div class="row mb-1">
                                        <div class="col-8">Medicine:</div>
                                        <div class="col-4 text-end"><?php echo htmlspecialchars($sale['medicine_name']); ?></div>
                                    </div>
                                    
                                    <?php if ($sale['category']): ?>
                                        <div class="row mb-1">
                                            <div class="col-8">Category:</div>
                                            <div class="col-4 text-end"><?php echo htmlspecialchars($sale['category']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sale['batch_number']): ?>
                                        <div class="row mb-1">
                                            <div class="col-8">Batch:</div>
                                            <div class="col-4 text-end"><?php echo htmlspecialchars($sale['batch_number']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mb-1">
                                        <div class="col-8">Quantity:</div>
                                        <div class="col-4 text-end"><?php echo $sale['quantity']; ?> units</div>
                                    </div>
                                    
                                    <div class="row mb-1">
                                        <div class="col-8">Unit Price:</div>
                                        <div class="col-4 text-end"><?php echo formatCurrency($sale['unit_price']); ?></div>
                                    </div>

                                    <?php if ($sale['prescription_number']): ?>
                                        <div class="row mb-2">
                                            <div class="col-8">Prescription:</div>
                                            <div class="col-4 text-end"><?php echo htmlspecialchars($sale['prescription_number']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="receipt-line"></div>
                                    
                                    <div class="row receipt-total">
                                        <div class="col-8">TOTAL AMOUNT:</div>
                                        <div class="col-4 text-end"><?php echo formatCurrency($sale['total_amount']); ?></div>
                                    </div>
                                    
                                    <div class="receipt-line"></div>
                                    
                                    <div class="text-center mt-3">
                                        <p class="mb-1"><small>Thank you for your purchase!</small></p>
                                        <p class="mb-1"><small>Please keep this receipt for your records</small></p>
                                        <?php if ($isPrint): ?>
                                            <p class="mb-0"><small>Printed on: <?php echo date('d-m-Y H:i:s'); ?></small></p>
                                        <?php endif; ?>
                                    </div>
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