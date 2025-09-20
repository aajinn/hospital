<?php
/**
 * Edit Doctor Form
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$errors = [];
$doctor = null;
$doctorId = 0;

// Get doctor ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $doctorId = (int)$_GET['id'];
    $doctor = getRecordById('doctors', $doctorId);
    
    if (!$doctor) {
        redirectWithMessage('index.php', 'Doctor not found.', 'error');
    }
} else {
    redirectWithMessage('index.php', 'Invalid doctor ID.', 'error');
}

// Initialize form data with existing doctor data
$formData = [
    'name' => $doctor['name'],
    'specialization' => $doctor['specialization'],
    'phone' => $doctor['phone'],
    'email' => $doctor['email'] ?? '',
    'schedule' => $doctor['schedule'] ?? '',
    'consultation_fee' => $doctor['consultation_fee']
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'specialization' => trim($_POST['specialization'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'schedule' => trim($_POST['schedule'] ?? ''),
        'consultation_fee' => trim($_POST['consultation_fee'] ?? '')
    ];
    
    // Validate required fields
    $requiredFields = [
        'name' => $formData['name'],
        'specialization' => $formData['specialization'],
        'phone' => $formData['phone']
    ];
    
    $errors = validateRequired($requiredFields);
    
    // Validate phone
    if (!empty($formData['phone']) && !validatePhone($formData['phone'])) {
        $errors[] = 'Phone number must be 10 digits';
    }
    
    // Validate email if provided
    if (!empty($formData['email']) && !validateEmail($formData['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Validate consultation fee
    if (!empty($formData['consultation_fee']) && !validateNumeric($formData['consultation_fee'], 0)) {
        $errors[] = 'Consultation fee must be a valid number';
    }
    
    // Check if phone number already exists (excluding current doctor)
    if (empty($errors)) {
        $connection = getDBConnection();
        $query = "SELECT id FROM doctors WHERE phone = ? AND id != ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('si', $formData['phone'], $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'A doctor with this phone number already exists';
        }
        
        $stmt->close();
        closeDBConnection($connection);
    }
    
    // Check if email already exists (excluding current doctor and if email is provided)
    if (empty($errors) && !empty($formData['email'])) {
        $connection = getDBConnection();
        $query = "SELECT id FROM doctors WHERE email = ? AND id != ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('si', $formData['email'], $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'A doctor with this email address already exists';
        }
        
        $stmt->close();
        closeDBConnection($connection);
    }
    
    // If no errors, update doctor
    if (empty($errors)) {
        try {
            // Prepare data for update
            $updateData = [
                'name' => $formData['name'],
                'specialization' => $formData['specialization'],
                'phone' => $formData['phone'],
                'email' => !empty($formData['email']) ? $formData['email'] : null,
                'schedule' => !empty($formData['schedule']) ? $formData['schedule'] : null,
                'consultation_fee' => !empty($formData['consultation_fee']) ? (float)$formData['consultation_fee'] : 0.00
            ];
            
            // Update doctor record
            $result = updateRecord('doctors', $updateData, ['id' => $doctorId]);
            
            if ($result) {
                // Log activity
                logActivity('Doctor Updated', "Doctor profile updated: {$formData['name']} - {$formData['specialization']}");
                
                // Redirect with success message
                redirectWithMessage('view.php?id=' . $doctorId, "Doctor profile updated successfully!", 'success');
            } else {
                $errors[] = 'Failed to update doctor profile. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Doctor update error: " . $e->getMessage());
            $errors[] = 'An error occurred while updating the doctor profile. Please try again.';
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
                        <h1 class="h3 mb-0">Edit Doctor</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Doctors</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $doctorId; ?>">Dr. <?php echo htmlspecialchars($doctor['name']); ?></a></li>
                                <li class="breadcrumb-item active">Edit</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="view.php?id=<?php echo $doctorId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
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

                <!-- Doctor Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-md"></i> Edit Doctor Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="doctorForm">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Basic Information</h6>
                                    
                                    <div class="form-group">
                                        <label for="name" class="required">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($formData['name']); ?>" 
                                               required maxlength="100">
                                        <small class="form-text text-muted">Enter doctor's full name with title (Dr.)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="specialization" class="required">Specialization</label>
                                        <input type="text" class="form-control" id="specialization" name="specialization" 
                                               value="<?php echo htmlspecialchars($formData['specialization']); ?>" 
                                               required maxlength="100" list="specializationList">
                                        <datalist id="specializationList">
                                            <option value="General Medicine">
                                            <option value="Cardiology">
                                            <option value="Neurology">
                                            <option value="Orthopedics">
                                            <option value="Pediatrics">
                                            <option value="Gynecology">
                                            <option value="Dermatology">
                                            <option value="Psychiatry">
                                            <option value="Ophthalmology">
                                            <option value="ENT">
                                            <option value="Radiology">
                                            <option value="Anesthesiology">
                                            <option value="Emergency Medicine">
                                            <option value="Surgery">
                                            <option value="Oncology">
                                        </datalist>
                                        <small class="form-text text-muted">Doctor's area of specialization</small>
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
                                        <small class="form-text text-muted">Professional email address</small>
                                    </div>
                                </div>

                                <!-- Professional Information -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Professional Information</h6>
                                    
                                    <div class="form-group">
                                        <label for="consultation_fee">Consultation Fee (₹)</label>
                                        <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                               value="<?php echo htmlspecialchars($formData['consultation_fee']); ?>" 
                                               min="0" step="0.01" max="99999.99">
                                        <small class="form-text text-muted">Fee charged per consultation</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="schedule">Schedule</label>
                                        <textarea class="form-control" id="schedule" name="schedule" 
                                                  rows="6" maxlength="1000"><?php echo htmlspecialchars($formData['schedule']); ?></textarea>
                                        <small class="form-text text-muted">Working hours and availability (e.g., Mon-Fri: 9:00 AM - 5:00 PM)</small>
                                    </div>

                                    <!-- Registration Info -->
                                    <div class="form-group">
                                        <label class="text-muted">Registration Date</label>
                                        <div class="form-control-plaintext">
                                            <i class="fas fa-calendar text-muted mr-1"></i>
                                            <?php echo formatDate($doctor['created_at'], 'd M Y, h:i A'); ?>
                                        </div>
                                    </div>

                                    <?php if ($doctor['updated_at'] != $doctor['created_at']): ?>
                                    <div class="form-group">
                                        <label class="text-muted">Last Updated</label>
                                        <div class="form-control-plaintext">
                                            <i class="fas fa-clock text-muted mr-1"></i>
                                            <?php echo formatDate($doctor['updated_at'], 'd M Y, h:i A'); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Doctor
                                        </button>
                                        <button type="reset" class="btn btn-secondary ml-2">
                                            <i class="fas fa-undo"></i> Reset Changes
                                        </button>
                                        <a href="view.php?id=<?php echo $doctorId; ?>" class="btn btn-outline-secondary ml-2">
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
    $('#doctorForm').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Validate required fields
        var requiredFields = ['name', 'specialization', 'phone'];
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
        
        // Validate consultation fee
        var fee = $('#consultation_fee').val().trim();
        if (fee && (isNaN(fee) || parseFloat(fee) < 0)) {
            errors.push('Consultation fee must be a valid positive number');
            $('#consultation_fee').addClass('is-invalid');
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
    $('#phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });
    
    // Consultation fee validation
    $('#consultation_fee').on('input', function() {
        var value = parseFloat(this.value);
        if (value < 0) this.value = 0;
        if (value > 99999.99) this.value = 99999.99;
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>