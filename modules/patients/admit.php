<?php
/**
 * Patient Admission Form
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage('index.php', 'Invalid patient ID.', 'error');
}

$patientId = (int)$_GET['id'];
$patient = null;
$doctors = [];
$errors = [];
$success = '';

// Initialize form data
$formData = [
    'doctor_id' => isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id']) ? $_GET['doctor_id'] : '',
    'admission_date' => date('Y-m-d'),
    'reason' => '',
    'room_charges' => '0.00'
];

try {
    // Get patient details
    $patient = getRecordById('patients', $patientId);
    
    if (!$patient) {
        redirectWithMessage('index.php', 'Patient not found.', 'error');
    }
    
    // Check if patient is already admitted
    $activeAdmissions = selectRecords('admissions', [
        'patient_id' => $patientId,
        'status' => 'Admitted'
    ]);
    
    if (!empty($activeAdmissions)) {
        redirectWithMessage("view.php?id=$patientId", 'Patient is already admitted.', 'warning');
    }
    
    // Get all doctors with workload information
    $doctors = getDoctorAssignmentRecommendations();
    
} catch (Exception $e) {
    error_log("Patient admission page error: " . $e->getMessage());
    redirectWithMessage('index.php', 'An error occurred while loading the admission form.', 'error');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $formData = [
        'doctor_id' => trim($_POST['doctor_id'] ?? ''),
        'admission_date' => trim($_POST['admission_date'] ?? ''),
        'reason' => trim($_POST['reason'] ?? ''),
        'room_charges' => trim($_POST['room_charges'] ?? '0.00')
    ];
    
    // Validate required fields
    $requiredFields = [
        'doctor_id' => $formData['doctor_id'],
        'admission_date' => $formData['admission_date'],
        'reason' => $formData['reason']
    ];
    
    $errors = validateRequired($requiredFields);
    
    // Validate doctor ID
    if (!empty($formData['doctor_id']) && !is_numeric($formData['doctor_id'])) {
        $errors[] = 'Please select a valid doctor';
    }
    
    // Validate admission date
    if (!empty($formData['admission_date']) && !validateDate($formData['admission_date'])) {
        $errors[] = 'Please enter a valid admission date';
    }
    
    // Validate room charges
    if (!empty($formData['room_charges']) && !is_numeric($formData['room_charges'])) {
        $errors[] = 'Room charges must be a valid number';
    }
    
    // Check if admission date is not in the future
    if (!empty($formData['admission_date']) && strtotime($formData['admission_date']) > time()) {
        $errors[] = 'Admission date cannot be in the future';
    }
    
    // Verify doctor exists
    if (empty($errors) && !empty($formData['doctor_id'])) {
        $doctor = getRecordById('doctors', $formData['doctor_id']);
        if (!$doctor) {
            $errors[] = 'Selected doctor not found';
        }
    }
    
    // If no errors, save admission
    if (empty($errors)) {
        try {
            // Prepare admission data
            $admissionData = [
                'patient_id' => $patientId,
                'doctor_id' => (int)$formData['doctor_id'],
                'admission_date' => $formData['admission_date'],
                'reason' => $formData['reason'],
                'status' => 'Admitted',
                'room_charges' => (float)$formData['room_charges']
            ];
            
            // Insert admission record
            $insertId = insertRecord('admissions', $admissionData);
            
            if ($insertId) {
                // Log activity
                logActivity('Patient Admitted', "Patient {$patient['name']} (ID: {$patient['patient_id']}) admitted by Dr. {$doctor['name']}");
                
                // Redirect with success message
                redirectWithMessage("view.php?id=$patientId", "Patient admitted successfully!", 'success');
            } else {
                $errors[] = 'Failed to admit patient. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Patient admission error: " . $e->getMessage());
            $errors[] = 'An error occurred while admitting the patient. Please try again.';
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
                        <h1 class="h3 mb-0">Admit Patient</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Patients</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></a></li>
                                <li class="breadcrumb-item active">Admit</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $patient['id']; ?>" class="btn btn-secondary">
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
                    <!-- Patient Information -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user"></i> Patient Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless patient-info">
                                    <tr>
                                        <td><strong>Patient ID:</strong></td>
                                        <td><span class="patient-id"><?php echo htmlspecialchars($patient['patient_id']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Age/Gender:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['age']); ?> years, <?php echo htmlspecialchars($patient['gender']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                    </tr>
                                    <?php if (!empty($patient['medical_history'])): ?>
                                    <tr>
                                        <td colspan="2">
                                            <strong>Medical History:</strong><br>
                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></small>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Admission Form -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-hospital"></i> Admission Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="admissionForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="doctor_id" class="required">Attending Doctor</label>
                                                <select class="form-control" id="doctor_id" name="doctor_id" required>
                                                    <option value="">Select Doctor</option>
                                                    <?php foreach ($doctors as $doctor): ?>
                                                        <?php
                                                        $workloadClass = '';
                                                        $workloadText = '';
                                                        if ($doctor['workload_level'] == 'high') {
                                                            $workloadClass = 'text-danger';
                                                            $workloadText = ' (High Load: ' . $doctor['active_assignments'] . ' patients)';
                                                        } elseif ($doctor['workload_level'] == 'medium') {
                                                            $workloadClass = 'text-warning';
                                                            $workloadText = ' (Medium Load: ' . $doctor['active_assignments'] . ' patients)';
                                                        } else {
                                                            $workloadClass = 'text-success';
                                                            $workloadText = ' (Available: ' . $doctor['active_assignments'] . ' patients)';
                                                        }
                                                        ?>
                                                        <option value="<?php echo $doctor['id']; ?>" 
                                                                <?php echo $formData['doctor_id'] == $doctor['id'] ? 'selected' : ''; ?>
                                                                class="<?php echo $workloadClass; ?>">
                                                            Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                                            <?php if (!empty($doctor['specialization'])): ?>
                                                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                                            <?php endif; ?>
                                                            <?php echo $workloadText; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">
                                                    Select the doctor who will be attending this patient
                                                    <br><span class="text-success">Green: Available</span> | 
                                                    <span class="text-warning">Orange: Medium Load</span> | 
                                                    <span class="text-danger">Red: High Load</span>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="admission_date" class="required">Admission Date</label>
                                                <input type="date" class="form-control" id="admission_date" name="admission_date" 
                                                       value="<?php echo htmlspecialchars($formData['admission_date']); ?>" 
                                                       required max="<?php echo date('Y-m-d'); ?>">
                                                <small class="form-text text-muted">Date of admission</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="reason" class="required">Reason for Admission</label>
                                        <textarea class="form-control" id="reason" name="reason" 
                                                  rows="4" required maxlength="1000"><?php echo htmlspecialchars($formData['reason']); ?></textarea>
                                        <small class="form-text text-muted">Describe the medical condition or reason for admission</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="room_charges">Room Charges (per day)</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₹</span>
                                            </div>
                                            <input type="number" class="form-control" id="room_charges" name="room_charges" 
                                                   value="<?php echo htmlspecialchars($formData['room_charges']); ?>" 
                                                   min="0" step="0.01">
                                        </div>
                                        <small class="form-text text-muted">Daily room charges (leave 0 if not applicable)</small>
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-hospital"></i> Admit Patient
                                        </button>
                                        <button type="reset" class="btn btn-secondary ml-2">
                                            <i class="fas fa-undo"></i> Reset Form
                                        </button>
                                        <a href="view.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline-secondary ml-2">
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
    // Form validation
    $('#admissionForm').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Validate required fields
        var requiredFields = ['doctor_id', 'admission_date', 'reason'];
        requiredFields.forEach(function(field) {
            var value = $('#' + field).val().trim();
            if (!value) {
                errors.push(field.charAt(0).toUpperCase() + field.slice(1).replace('_', ' ') + ' is required');
                $('#' + field).addClass('is-invalid');
                isValid = false;
            } else {
                $('#' + field).removeClass('is-invalid');
            }
        });
        
        // Validate admission date
        var admissionDate = $('#admission_date').val();
        if (admissionDate && new Date(admissionDate) > new Date()) {
            errors.push('Admission date cannot be in the future');
            $('#admission_date').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate room charges
        var roomCharges = $('#room_charges').val();
        if (roomCharges && (isNaN(roomCharges) || parseFloat(roomCharges) < 0)) {
            errors.push('Room charges must be a valid positive number');
            $('#room_charges').addClass('is-invalid');
            isValid = false;
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
    $('input, select, textarea').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
    
    // Set max date for admission date
    $('#admission_date').attr('max', new Date().toISOString().split('T')[0]);
});
</script>

<?php include_once '../../includes/footer.php'; ?>