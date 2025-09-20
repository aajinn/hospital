<?php
/**
 * Generate Bill - Billing Module
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$patients = [];
$admissions = [];
$selectedPatient = null;
$selectedAdmission = null;
$errors = [];

// Get patients for dropdown
try {
    $connection = getDBConnection();
    
    // Get all patients
    $patientQuery = "SELECT id, patient_id, name, phone FROM patients ORDER BY name";
    $patientResult = $connection->query($patientQuery);
    while ($row = $patientResult->fetch_assoc()) {
        $patients[] = $row;
    }
    
    closeDBConnection($connection);
} catch (Exception $e) {
    error_log("Error loading patients: " . $e->getMessage());
    $errors[] = "Error loading patient data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $requiredFields = [
            'patient_id' => $_POST['patient_id'] ?? '',
            'bill_date' => $_POST['bill_date'] ?? '',
            'doctor_fee' => $_POST['doctor_fee'] ?? '0',
            'room_charges' => $_POST['room_charges'] ?? '0',
            'medicine_charges' => $_POST['medicine_charges'] ?? '0',
            'other_charges' => $_POST['other_charges'] ?? '0'
        ];
        
        $validationErrors = validateRequired([
            'patient_id' => $requiredFields['patient_id'],
            'bill_date' => $requiredFields['bill_date']
        ]);
        
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }
        
        // Validate date
        if (!empty($requiredFields['bill_date']) && !validateDate($requiredFields['bill_date'])) {
            $errors[] = "Please enter a valid bill date.";
        }
        
        // Validate numeric fields
        $numericFields = ['doctor_fee', 'room_charges', 'medicine_charges', 'other_charges'];
        foreach ($numericFields as $field) {
            if (!validateNumeric($requiredFields[$field], 0)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be a valid positive number.";
            }
        }
        
        if (empty($errors)) {
            $connection = getDBConnection();
            
            // Check if patient exists
            $patientCheck = "SELECT id FROM patients WHERE id = ?";
            $stmt = $connection->prepare($patientCheck);
            $stmt->bind_param('i', $requiredFields['patient_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $errors[] = "Selected patient not found.";
            } else {
                // Get admission ID if provided
                $admissionId = !empty($_POST['admission_id']) ? (int)$_POST['admission_id'] : null;
                
                // Prepare bill data
                $billData = [
                    'patient_id' => (int)$requiredFields['patient_id'],
                    'admission_id' => $admissionId,
                    'bill_date' => $requiredFields['bill_date'],
                    'doctor_fee' => (float)$requiredFields['doctor_fee'],
                    'room_charges' => (float)$requiredFields['room_charges'],
                    'medicine_charges' => (float)$requiredFields['medicine_charges'],
                    'other_charges' => (float)$requiredFields['other_charges']
                ];
                
                // Calculate total (trigger will handle this, but we set it for safety)
                $billData['total_amount'] = $billData['doctor_fee'] + $billData['room_charges'] + 
                                          $billData['medicine_charges'] + $billData['other_charges'];
                
                // Insert bill
                $billId = insertRecord('bills', $billData);
                
                if ($billId) {
                    logActivity('Bill Generated', "Bill ID: $billId for Patient ID: " . $requiredFields['patient_id']);
                    redirectWithMessage('view.php?id=' . $billId, 'Bill generated successfully!', 'success');
                } else {
                    $errors[] = "Failed to generate bill. Please try again.";
                }
            }
            
            $stmt->close();
            closeDBConnection($connection);
        }
    } catch (Exception $e) {
        error_log("Bill generation error: " . $e->getMessage());
        $errors[] = "An error occurred while generating the bill.";
    }
}

// Get patient details via AJAX
if (isset($_GET['get_patient']) && isset($_GET['patient_id'])) {
    header('Content-Type: application/json');
    
    try {
        $connection = getDBConnection();
        $patientId = (int)$_GET['patient_id'];
        
        // Get patient details
        $query = "SELECT p.*, 
                         COUNT(CASE WHEN a.status = 'Admitted' THEN 1 END) as active_admissions
                  FROM patients p 
                  LEFT JOIN admissions a ON p.id = a.patient_id 
                  WHERE p.id = ? 
                  GROUP BY p.id";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            
            // Get recent admissions
            $admissionQuery = "SELECT a.*, d.name as doctor_name, d.consultation_fee 
                              FROM admissions a 
                              JOIN doctors d ON a.doctor_id = d.id 
                              WHERE a.patient_id = ? 
                              ORDER BY a.admission_date DESC 
                              LIMIT 5";
            $admissionStmt = $connection->prepare($admissionQuery);
            $admissionStmt->bind_param('i', $patientId);
            $admissionStmt->execute();
            $admissionResult = $admissionStmt->get_result();
            
            $admissions = [];
            while ($admission = $admissionResult->fetch_assoc()) {
                $admissions[] = $admission;
            }
            
            $patient['admissions'] = $admissions;
            
            echo json_encode(['success' => true, 'patient' => $patient]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Patient not found']);
        }
        
        $stmt->close();
        closeDBConnection($connection);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading patient data']);
    }
    exit;
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
                        <h1 class="h3 mb-0">Generate Bill</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Billing</a></li>
                                <li class="breadcrumb-item active">Generate Bill</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Bill Generation Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-invoice-dollar"></i> Bill Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="billForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="patient_id">Select Patient <span class="text-danger">*</span></label>
                                                <select class="form-control" id="patient_id" name="patient_id" required>
                                                    <option value="">Choose Patient...</option>
                                                    <?php foreach ($patients as $patient): ?>
                                                        <option value="<?php echo $patient['id']; ?>" 
                                                                <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="bill_date">Bill Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="bill_date" name="bill_date" 
                                                       value="<?php echo $_POST['bill_date'] ?? date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="admission_id">Related Admission (Optional)</label>
                                                <select class="form-control" id="admission_id" name="admission_id">
                                                    <option value="">No specific admission</option>
                                                </select>
                                                <small class="form-text text-muted">Select a patient first to see their admissions</small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <h6>Billing Components</h6>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="doctor_fee">Doctor Consultation Fee</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="number" class="form-control" id="doctor_fee" name="doctor_fee" 
                                                           value="<?php echo $_POST['doctor_fee'] ?? '0'; ?>" 
                                                           min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="room_charges">Room Charges</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="number" class="form-control" id="room_charges" name="room_charges" 
                                                           value="<?php echo $_POST['room_charges'] ?? '0'; ?>" 
                                                           min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="medicine_charges">Medicine Charges</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="number" class="form-control" id="medicine_charges" name="medicine_charges" 
                                                           value="<?php echo $_POST['medicine_charges'] ?? '0'; ?>" 
                                                           min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="other_charges">Other Charges</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="number" class="form-control" id="other_charges" name="other_charges" 
                                                           value="<?php echo $_POST['other_charges'] ?? '0'; ?>" 
                                                           min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <div class="form-group">
                                                <label for="total_amount">Total Amount</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₹</span>
                                                    </div>
                                                    <input type="text" class="form-control font-weight-bold" id="total_amount" 
                                                           value="0.00" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Generate Bill
                                        </button>
                                        <a href="index.php" class="btn btn-secondary ml-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Patient Information -->
                        <div class="card" id="patientInfo" style="display: none;">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-user"></i> Patient Information
                                </h6>
                            </div>
                            <div class="card-body" id="patientDetails">
                                <!-- Patient details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Calculate total amount
    function calculateTotal() {
        var doctorFee = parseFloat($('#doctor_fee').val()) || 0;
        var roomCharges = parseFloat($('#room_charges').val()) || 0;
        var medicineCharges = parseFloat($('#medicine_charges').val()) || 0;
        var otherCharges = parseFloat($('#other_charges').val()) || 0;
        
        var total = doctorFee + roomCharges + medicineCharges + otherCharges;
        $('#total_amount').val(total.toFixed(2));
    }
    
    // Recalculate total when any amount field changes
    $('#doctor_fee, #room_charges, #medicine_charges, #other_charges').on('input', calculateTotal);
    
    // Load patient details when patient is selected
    $('#patient_id').on('change', function() {
        var patientId = $(this).val();
        
        if (patientId) {
            $.ajax({
                url: 'generate.php',
                method: 'GET',
                data: {
                    get_patient: 1,
                    patient_id: patientId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var patient = response.patient;
                        
                        // Show patient info card
                        $('#patientInfo').show();
                        
                        // Update patient details
                        var html = '<div class="patient-details">';
                        html += '<p><strong>Name:</strong> ' + patient.name + '</p>';
                        html += '<p><strong>Patient ID:</strong> ' + patient.patient_id + '</p>';
                        html += '<p><strong>Age/Gender:</strong> ' + patient.age + ' years, ' + patient.gender + '</p>';
                        html += '<p><strong>Phone:</strong> ' + patient.phone + '</p>';
                        
                        if (patient.active_admissions > 0) {
                            html += '<p><strong>Status:</strong> <span class="badge badge-warning">Currently Admitted</span></p>';
                        }
                        
                        html += '</div>';
                        
                        $('#patientDetails').html(html);
                        
                        // Update admission dropdown
                        var admissionOptions = '<option value="">No specific admission</option>';
                        if (patient.admissions && patient.admissions.length > 0) {
                            patient.admissions.forEach(function(admission) {
                                var statusBadge = admission.status === 'Admitted' ? 
                                    '<span class="badge badge-success">Active</span>' : 
                                    '<span class="badge badge-secondary">Discharged</span>';
                                
                                admissionOptions += '<option value="' + admission.id + '">' +
                                    admission.admission_date + ' - ' + admission.doctor_name + 
                                    ' (' + admission.status + ')</option>';
                                
                                // Auto-fill doctor fee if admission is selected
                                if (admission.status === 'Admitted' && $('#doctor_fee').val() == '0') {
                                    $('#doctor_fee').val(admission.consultation_fee || 0);
                                }
                            });
                        }
                        $('#admission_id').html(admissionOptions);
                        
                        // Recalculate total
                        calculateTotal();
                    } else {
                        alert('Error loading patient details: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error loading patient details. Please try again.');
                }
            });
        } else {
            $('#patientInfo').hide();
            $('#admission_id').html('<option value="">No specific admission</option>');
        }
    });
    
    // Update doctor fee when admission is selected
    $('#admission_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        // This would require additional AJAX call to get admission details
        // For now, we'll keep the current doctor fee
    });
    
    // Initial calculation
    calculateTotal();
});
</script>

<?php include_once '../../includes/footer.php'; ?>