<?php
/**
 * Patient Discharge Form
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check if admission ID is provided
if (!isset($_GET['admission_id']) || !is_numeric($_GET['admission_id'])) {
    redirectWithMessage('index.php', 'Invalid admission ID.', 'error');
}

$admissionId = (int)$_GET['admission_id'];
$admission = null;
$patient = null;
$doctor = null;
$errors = [];

// Initialize form data
$formData = [
    'discharge_date' => date('Y-m-d'),
    'discharge_notes' => ''
];

try {
    $connection = getDBConnection();
    
    // Get admission details with patient and doctor info
    $query = "SELECT a.*, p.name as patient_name, p.patient_id, p.age, p.gender, p.phone,
                     d.name as doctor_name, d.specialization
              FROM admissions a
              JOIN patients p ON a.patient_id = p.id
              LEFT JOIN doctors d ON a.doctor_id = d.id
              WHERE a.id = ? AND a.status = 'Admitted'";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param('i', $admissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        redirectWithMessage('index.php', 'Admission not found or patient already discharged.', 'error');
    }
    
    $admission = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Patient discharge page error: " . $e->getMessage());
    redirectWithMessage('index.php', 'An error occurred while loading the discharge form.', 'error');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $formData = [
        'discharge_date' => trim($_POST['discharge_date'] ?? ''),
        'discharge_notes' => trim($_POST['discharge_notes'] ?? '')
    ];
    
    // Validate required fields
    $requiredFields = [
        'discharge_date' => $formData['discharge_date']
    ];
    
    $errors = validateRequired($requiredFields);
    
    // Validate discharge date
    if (!empty($formData['discharge_date']) && !validateDate($formData['discharge_date'])) {
        $errors[] = 'Please enter a valid discharge date';
    }
    
    // Check if discharge date is not before admission date
    if (!empty($formData['discharge_date']) && strtotime($formData['discharge_date']) < strtotime($admission['admission_date'])) {
        $errors[] = 'Discharge date cannot be before admission date';
    }
    
    // Check if discharge date is not in the future
    if (!empty($formData['discharge_date']) && strtotime($formData['discharge_date']) > time()) {
        $errors[] = 'Discharge date cannot be in the future';
    }
    
    // If no errors, process discharge
    if (empty($errors)) {
        try {
            // Calculate total days and room charges
            $admissionDate = new DateTime($admission['admission_date']);
            $dischargeDate = new DateTime($formData['discharge_date']);
            $totalDays = $dischargeDate->diff($admissionDate)->days + 1; // Include admission day
            $totalRoomCharges = $totalDays * $admission['room_charges'];
            
            // Update admission record
            $updateData = [
                'discharge_date' => $formData['discharge_date'],
                'status' => 'Discharged',
                'room_charges' => $totalRoomCharges
            ];
            
            $updateResult = updateRecord('admissions', $updateData, ['id' => $admissionId]);
            
            if ($updateResult) {
                // Log activity
                logActivity('Patient Discharged', "Patient {$admission['patient_name']} (ID: {$admission['patient_id']}) discharged after $totalDays days");
                
                // Redirect with success message
                redirectWithMessage("view.php?id={$admission['patient_id']}", "Patient discharged successfully! Total stay: $totalDays days", 'success');
            } else {
                $errors[] = 'Failed to discharge patient. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Patient discharge error: " . $e->getMessage());
            $errors[] = 'An error occurred while discharging the patient. Please try again.';
        }
    }
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
                        <h1 class="h3 mb-0">Discharge Patient</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Patients</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $admission['patient_id']; ?>"><?php echo htmlspecialchars($admission['patient_name']); ?></a></li>
                                <li class="breadcrumb-item active">Discharge</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $admission['patient_id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Patient
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Please correct the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Admission Information -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-hospital"></i> Current Admission Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Patient ID:</strong></td>
                                        <td><span class="patient-id"><?php echo htmlspecialchars($admission['patient_id']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Patient Name:</strong></td>
                                        <td><?php echo htmlspecialchars($admission['patient_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Age/Gender:</strong></td>
                                        <td><?php echo htmlspecialchars($admission['age']); ?> years, <?php echo htmlspecialchars($admission['gender']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($admission['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Attending Doctor:</strong></td>
                                        <td>
                                            Dr. <?php echo htmlspecialchars($admission['doctor_name']); ?>
                                            <?php if (!empty($admission['specialization'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($admission['specialization']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Admission Date:</strong></td>
                                        <td><?php echo formatDate($admission['admission_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reason:</strong></td>
                                        <td><?php echo nl2br(htmlspecialchars($admission['reason'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Room Charges:</strong></td>
                                        <td><?php echo formatCurrency($admission['room_charges']); ?> per day</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Discharge Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-sign-out-alt"></i> Discharge Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="dischargeForm">
                                    <div class="form-group">
                                        <label for="discharge_date" class="required">Discharge Date</label>
                                        <input type="date" class="form-control" id="discharge_date" name="discharge_date" 
                                               value="<?php echo htmlspecialchars($formData['discharge_date']); ?>" 
                                               required 
                                               min="<?php echo $admission['admission_date']; ?>"
                                               max="<?php echo date('Y-m-d'); ?>">
                                        <small class="form-text text-muted">
                                            Date must be between <?php echo formatDate($admission['admission_date']); ?> and today
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="discharge_notes">Discharge Notes</label>
                                        <textarea class="form-control" id="discharge_notes" name="discharge_notes" 
                                                  rows="6" maxlength="1000"><?php echo htmlspecialchars($formData['discharge_notes']); ?></textarea>
                                        <small class="form-text text-muted">Optional discharge summary, instructions, or notes</small>
                                    </div>

                                    <!-- Calculated Information -->
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-calculator"></i> Calculated Charges</h6>
                                        <div id="calculatedCharges">
                                            <p class="mb-1"><strong>Total Days:</strong> <span id="totalDays">-</span></p>
                                            <p class="mb-0"><strong>Total Room Charges:</strong> <span id="totalCharges">-</span></p>
                                        </div>
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-sign-out-alt"></i> Discharge Patient
                                        </button>
                                        <button type="reset" class="btn btn-secondary ml-2">
                                            <i class="fas fa-undo"></i> Reset Form
                                        </button>
                                        <a href="view.php?id=<?php echo $admission['patient_id']; ?>" class="btn btn-outline-secondary ml-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </form>
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
    var admissionDate = new Date('<?php echo $admission['admission_date']; ?>');
    var roomChargesPerDay = <?php echo $admission['room_charges']; ?>;
    
    // Calculate charges when discharge date changes
    function calculateCharges() {
        var dischargeDateStr = $('#discharge_date').val();
        if (dischargeDateStr) {
            var dischargeDate = new Date(dischargeDateStr);
            var timeDiff = dischargeDate.getTime() - admissionDate.getTime();
            var totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // Include admission day
            var totalCharges = totalDays * roomChargesPerDay;
            
            $('#totalDays').text(totalDays + ' days');
            $('#totalCharges').text('₹' + totalCharges.toFixed(2));
        } else {
            $('#totalDays').text('-');
            $('#totalCharges').text('-');
        }
    }
    
    // Calculate on page load
    calculateCharges();
    
    // Calculate when discharge date changes
    $('#discharge_date').on('change', calculateCharges);
    
    // Form validation
    $('#dischargeForm').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Validate discharge date
        var dischargeDate = $('#discharge_date').val();
        if (!dischargeDate) {
            errors.push('Discharge date is required');
            $('#discharge_date').addClass('is-invalid');
            isValid = false;
        } else {
            var dischargeDateObj = new Date(dischargeDate);
            var today = new Date();
            today.setHours(23, 59, 59, 999); // End of today
            
            if (dischargeDateObj < admissionDate) {
                errors.push('Discharge date cannot be before admission date');
                $('#discharge_date').addClass('is-invalid');
                isValid = false;
            } else if (dischargeDateObj > today) {
                errors.push('Discharge date cannot be in the future');
                $('#discharge_date').addClass('is-invalid');
                isValid = false;
            } else {
                $('#discharge_date').removeClass('is-invalid');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // Show errors
            var errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                           '<i class="fas fa-exclamation-circle"></i> ' +
                           '<strong>Please correct the following errors:</strong>' +
                           '<ul class="mb-0 mt-2">';
            
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            
            errorHtml += '</ul>' +
                        '<button type="button" class="close" data-dismiss="alert">' +
                        '<span>&times;</span>' +
                        '</button>' +
                        '</div>';
            
            $('.content-body').prepend(errorHtml);
            $('html, body').animate({scrollTop: 0}, 500);
        }
    });
    
    // Remove validation classes on input
    $('input, textarea').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>