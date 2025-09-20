<?php
/**
 * Add Patient Form
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$errors = [];
$success = '';
$formData = [
    'name' => '',
    'age' => '',
    'gender' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'emergency_contact' => '',
    'medical_history' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'age' => trim($_POST['age'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'medical_history' => trim($_POST['medical_history'] ?? '')
    ];
    
    // Validate required fields
    $requiredFields = [
        'name' => $formData['name'],
        'age' => $formData['age'],
        'gender' => $formData['gender'],
        'phone' => $formData['phone']
    ];
    
    $errors = validateRequired($requiredFields);
    
    // Validate age
    if (!empty($formData['age']) && !validateNumeric($formData['age'], 1, 150)) {
        $errors[] = 'Age must be a valid number between 1 and 150';
    }
    
    // Validate phone
    if (!empty($formData['phone']) && !validatePhone($formData['phone'])) {
        $errors[] = 'Phone number must be 10 digits';
    }
    
    // Validate email if provided
    if (!empty($formData['email']) && !validateEmail($formData['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Validate emergency contact if provided
    if (!empty($formData['emergency_contact']) && !validatePhone($formData['emergency_contact'])) {
        $errors[] = 'Emergency contact must be 10 digits';
    }
    
    // Check if phone number already exists
    if (empty($errors)) {
        $existingPatient = selectRecords('patients', ['phone' => $formData['phone']]);
        if (!empty($existingPatient)) {
            $errors[] = 'A patient with this phone number already exists';
        }
    }
    
    // If no errors, save patient
    if (empty($errors)) {
        try {
            // Generate patient ID
            $patientId = generatePatientId();
            
            // Prepare data for insertion
            $patientData = [
                'patient_id' => $patientId,
                'name' => $formData['name'],
                'age' => (int)$formData['age'],
                'gender' => $formData['gender'],
                'phone' => $formData['phone'],
                'email' => !empty($formData['email']) ? $formData['email'] : null,
                'address' => !empty($formData['address']) ? $formData['address'] : null,
                'emergency_contact' => !empty($formData['emergency_contact']) ? $formData['emergency_contact'] : null,
                'medical_history' => !empty($formData['medical_history']) ? $formData['medical_history'] : null
            ];
            
            // Insert patient record
            $insertId = insertRecord('patients', $patientData);
            
            if ($insertId) {
                // Log activity
                logActivity('Patient Added', "New patient registered: {$formData['name']} (ID: $patientId)");
                
                // Redirect with success message
                redirectWithMessage('index.php', "Patient registered successfully! Patient ID: $patientId", 'success');
            } else {
                $errors[] = 'Failed to register patient. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Patient registration error: " . $e->getMessage());
            $errors[] = 'An error occurred while registering the patient. Please try again.';
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
                        <h1 class="h3 mb-0">Add New Patient</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Patients</a></li>
                                <li class="breadcrumb-item active">Add Patient</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Patients
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

                <!-- Patient Registration Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus"></i> Patient Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="patientForm">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Basic Information</h6>
                                    
                                    <div class="form-group">
                                        <label for="name" class="required">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($formData['name']); ?>" 
                                               required maxlength="100">
                                        <small class="form-text text-muted">Enter patient's full name</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="age" class="required">Age</label>
                                                <input type="number" class="form-control" id="age" name="age" 
                                                       value="<?php echo htmlspecialchars($formData['age']); ?>" 
                                                       required min="1" max="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="gender" class="required">Gender</label>
                                                <select class="form-control" id="gender" name="gender" required>
                                                    <option value="">Select Gender</option>
                                                    <option value="Male" <?php echo $formData['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo $formData['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo $formData['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone" class="required">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                                               required pattern="[0-9]{10}" maxlength="10">
                                        <small class="form-text text-muted">Enter 10-digit phone number</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                               maxlength="100">
                                        <small class="form-text text-muted">Optional</small>
                                    </div>
                                </div>

                                <!-- Contact & Medical Information -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Contact & Medical Information</h6>
                                    
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <textarea class="form-control" id="address" name="address" 
                                                  rows="3" maxlength="500"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                                        <small class="form-text text-muted">Complete address with city and pincode</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="emergency_contact">Emergency Contact</label>
                                        <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                               value="<?php echo htmlspecialchars($formData['emergency_contact']); ?>" 
                                               pattern="[0-9]{10}" maxlength="10">
                                        <small class="form-text text-muted">10-digit emergency contact number</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="medical_history">Medical History</label>
                                        <textarea class="form-control" id="medical_history" name="medical_history" 
                                                  rows="4" maxlength="1000"><?php echo htmlspecialchars($formData['medical_history']); ?></textarea>
                                        <small class="form-text text-muted">Previous medical conditions, allergies, medications, etc.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Register Patient
                                        </button>
                                        <button type="reset" class="btn btn-secondary ml-2">
                                            <i class="fas fa-undo"></i> Reset Form
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary ml-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
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

<script>
$(document).ready(function() {
    // Form validation
    $('#patientForm').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Validate required fields
        var requiredFields = ['name', 'age', 'gender', 'phone'];
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
        
        // Validate age
        var age = parseInt($('#age').val());
        if (age && (age < 1 || age > 150)) {
            errors.push('Age must be between 1 and 150');
            $('#age').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate phone number
        var phone = $('#phone').val().trim();
        if (phone && !/^[0-9]{10}$/.test(phone)) {
            errors.push('Phone number must be exactly 10 digits');
            $('#phone').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate email if provided
        var email = $('#email').val().trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push('Please enter a valid email address');
            $('#email').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate emergency contact if provided
        var emergencyContact = $('#emergency_contact').val().trim();
        if (emergencyContact && !/^[0-9]{10}$/.test(emergencyContact)) {
            errors.push('Emergency contact must be exactly 10 digits');
            $('#emergency_contact').addClass('is-invalid');
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
    
    // Phone number formatting
    $('#phone, #emergency_contact').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });
    
    // Age validation
    $('#age').on('input', function() {
        var age = parseInt(this.value);
        if (age < 1) this.value = 1;
        if (age > 150) this.value = 150;
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>