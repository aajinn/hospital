<?php
/**
 * Print Bill - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Get bill ID
$billId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($billId <= 0) {
    die('Invalid bill ID.');
}

$bill = null;
$payments = [];

try {
    // Get bill details
    $bill = getBillDetails($billId);
    
    if (!$bill) {
        die('Bill not found.');
    }
    
    // Get payment history
    $payments = getBillPayments($billId);
    
} catch (Exception $e) {
    error_log("Print bill error: " . $e->getMessage());
    die('Error loading bill details.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?> - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .bill-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .bill-info div {
            flex: 1;
        }
        
        .bill-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .bill-info p {
            margin: 5px 0;
        }
        
        .bill-details table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bill-details th,
        .bill-details td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .bill-details th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .bill-details .text-right {
            text-align: right;
        }
        
        .total-row {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        
        .payment-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .payment-history {
            margin-top: 20px;
        }
        
        .payment-history h3 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-partial {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-pending {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            
            .bill-container {
                border: none;
                box-shadow: none;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>Hospital Management System</p>
            <p>Email: info@hospital.com | Phone: +91-XXXXXXXXXX</p>
        </div>
        
        <!-- Bill Information -->
        <div class="bill-info">
            <div>
                <h3>Bill To:</h3>
                <p><strong><?php echo htmlspecialchars($bill['patient_name']); ?></strong></p>
                <p>Patient ID: <?php echo htmlspecialchars($bill['patient_id']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($bill['phone']); ?></p>
                <?php if (!empty($bill['email'])): ?>
                    <p>Email: <?php echo htmlspecialchars($bill['email']); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="text-align: right;">
                <h3>Bill Details:</h3>
                <p><strong>Bill #<?php echo str_pad($bill['id'], 6, '0', STR_PAD_LEFT); ?></strong></p>
                <p>Bill Date: <?php echo formatDate($bill['bill_date']); ?></p>
                <p>Generated: <?php echo formatDate($bill['created_at'], 'd M Y H:i'); ?></p>
                <p>Status: 
                    <span class="status-badge status-<?php echo strtolower($bill['status']); ?>">
                        <?php echo $bill['status']; ?>
                    </span>
                </p>
                
                <?php if (!empty($bill['admission_date'])): ?>
                    <p>Admission: <?php echo formatDate($bill['admission_date']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($bill['doctor_name'])): ?>
                    <p>Doctor: <?php echo htmlspecialchars($bill['doctor_name']); ?></p>
                    <?php if (!empty($bill['specialization'])): ?>
                        <p><small><?php echo htmlspecialchars($bill['specialization']); ?></small></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bill Details -->
        <div class="bill-details">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bill['doctor_fee'] > 0): ?>
                        <tr>
                            <td>Doctor Consultation Fee</td>
                            <td class="text-right"><?php echo formatCurrency($bill['doctor_fee']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($bill['room_charges'] > 0): ?>
                        <tr>
                            <td>Room Charges</td>
                            <td class="text-right"><?php echo formatCurrency($bill['room_charges']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($bill['medicine_charges'] > 0): ?>
                        <tr>
                            <td>Medicine Charges</td>
                            <td class="text-right"><?php echo formatCurrency($bill['medicine_charges']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($bill['other_charges'] > 0): ?>
                        <tr>
                            <td>Other Charges</td>
                            <td class="text-right"><?php echo formatCurrency($bill['other_charges']); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>Total Amount</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment Summary -->
        <div class="payment-summary">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none;"><strong>Total Amount:</strong></td>
                    <td style="border: none; text-align: right;"><strong><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                </tr>
                <tr>
                    <td style="border: none;">Amount Paid:</td>
                    <td style="border: none; text-align: right; color: #28a745;"><?php echo formatCurrency($bill['paid_amount']); ?></td>
                </tr>
                <tr style="border-top: 1px solid #dee2e6;">
                    <td style="border: none;"><strong>Pending Amount:</strong></td>
                    <td style="border: none; text-align: right; color: <?php echo $bill['pending_amount'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                        <strong><?php echo formatCurrency($bill['pending_amount']); ?></strong>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
            <div class="payment-history">
                <h3>Payment History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo !empty($payment['transaction_id']) ? htmlspecialchars($payment['transaction_id']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>Thank you for choosing <?php echo APP_NAME; ?>!</p>
            <p>This is a computer-generated bill and does not require a signature.</p>
            <p>Generated on: <?php echo date('d M Y H:i:s'); ?></p>
        </div>
        
        <!-- Print Button (hidden when printing) -->
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Print Bill
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                Close
            </button>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>