<?php
/**
 * Delete Doctor
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$doctorId = 0;
$doctor = null;

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

// Check if doctor has any admissions
$hasAdmissions = false;
try {
    $connection = getDBConnection();
    $checkQuery = "SELECT COUNT(*) as count FROM admissions WHERE doctor_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param('i', $doctorId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $hasAdmissions = $checkResult->fetch_assoc()['count'] > 0;
    $checkStmt->close();
    closeDBConnection($connection);
} catch (Exception $e) {
    error_log("Check admissions error: " . $e->getMessage());
    redirectWithMessage('index.php', 'Error checking doctor records.', 'error');
}

// If doctor has admissions, prevent deletion
if ($hasAdmissions) {
    redirectWithMessage('view.php?id=' . $doctorId, 'Cannot delete doctor. This doctor has patient admission records. Please transfer or discharge all patients first.', 'error');
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Delete doctor record
        $result = deleteRecord('doctors', ['id' => $doctorId]);
        
        if ($result) {
            // Log activity
            logActivity('Doctor Deleted', "Doctor deleted: {$doctor['name']} - {$doctor['specialization']}");
            
            // Redirect with success message
            redirectWithMessage('index.php', "Doctor '{$doctor['name']}' has been deleted successfully.", 'success');
        } else {
            redirectWithMessage('view.php?id=' . $doctorId, 'Failed to delete doctor. Please try again.', 'error');
        }
    } catch (Exception $e) {
        error_log("Doctor deletion error: " . $e->getMessage());
        redirectWithMessage('view.php?id=' . $doctorId, 'An error occurred while deleting the doctor. Please try again.', 'error');
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
                        <h1 class="h3 mb-0">Delete Doctor</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Doctors</a></li>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $doctorId; ?>">Dr. <?php echo htmlspecialchars($doctor['name']); ?></a></li>
                                <li class="breadcrumb-item active">Delete</li>
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
                <!-- Delete Confirmation -->
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> Confirm Doctor Deletion
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Warning!</strong> This action cannot be undone.
                                </div>

                                <!-- Doctor Information -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <div class="avatar-lg bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                                <i class="fas fa-user-md fa-2x"></i>
                                            </div>
                                            <h4><?php echo htmlspecialchars($doctor['name']); ?></h4>
                                            <p class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                            </tr>
                                            <?php if (!empty($doctor['email'])): ?>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td><strong>Consultation Fee:</strong></td>
                                                <td>
                                                    <?php if ($doctor['consultation_fee'] > 0): ?>
                                                        <?php echo formatCurrency($doctor['consultation_fee']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Registered:</strong></td>
                                                <td><?php echo formatDate($doctor['created_at'], 'd M Y'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="alert alert-info" role="alert">
                                    <h6><i class="fas fa-info-circle"></i> What will happen when you delete this doctor?</h6>
                                    <ul class="mb-0">
                                        <li>The doctor's profile will be permanently removed from the system</li>
                                        <li>All doctor information including contact details and schedule will be deleted</li>
                                        <li>This action is irreversible</li>
                                    </ul>
                                </div>

                                <!-- Confirmation Form -->
                                <form method="POST" action="" id="deleteForm">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                                            <label class="form-check-label" for="confirmCheck">
                                                I understand that this action cannot be undone and I want to permanently delete Dr. <?php echo htmlspecialchars($doctor['name']); ?>.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-actions text-center">
                                        <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                            <i class="fas fa-trash"></i> Yes, Delete Doctor
                                        </button>
                                        <a href="view.php?id=<?php echo $doctorId; ?>" class="btn btn-secondary ml-3">
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
    // Enable/disable delete button based on checkbox
    $('#confirmCheck').on('change', function() {
        $('#deleteBtn').prop('disabled', !this.checked);
    });
    
    // Form submission confirmation
    $('#deleteForm').on('submit', function(e) {
        if (!$('#confirmCheck').is(':checked')) {
            e.preventDefault();
            alert('Please confirm that you want to delete this doctor.');
            return false;
        }
        
        // Final confirmation
        if (!confirm('Are you absolutely sure you want to delete Dr. <?php echo htmlspecialchars($doctor['name'], ENT_QUOTES); ?>? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
}
</style>

<?php include_once '../../includes/footer.php'; ?>