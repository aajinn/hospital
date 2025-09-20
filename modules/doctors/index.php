<?php
/**
 * Doctors Module - Main Index
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$doctors = [];
$totalRecords = 0;
$currentPage = 1;
$totalPages = 1;
$searchTerm = '';
$searchSpecialization = '';

// Get search parameters
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}
if (isset($_GET['specialization'])) {
    $searchSpecialization = trim($_GET['specialization']);
}

// Get current page
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int)$_GET['page']);
}

try {
    $connection = getDBConnection();
    
    // Build search conditions
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $types .= 'sss';
    }
    
    if (!empty($searchSpecialization)) {
        $whereConditions[] = "specialization LIKE ?";
        $params[] = "%$searchSpecialization%";
        $types .= 's';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM doctors $whereClause";
    $countStmt = $connection->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / RECORDS_PER_PAGE);
    $offset = ($currentPage - 1) * RECORDS_PER_PAGE;
    
    // Get doctors with pagination
    $query = "SELECT * FROM doctors $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
    $stmt = $connection->prepare($query);
    
    // Add limit and offset parameters
    $params[] = RECORDS_PER_PAGE;
    $params[] = $offset;
    $types .= 'ii';
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    
    $stmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Doctors listing error: " . $e->getMessage());
    $doctors = [];
}

// Get unique specializations for filter dropdown
$specializations = [];
try {
    $connection = getDBConnection();
    $specQuery = "SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
    $specResult = $connection->query($specQuery);
    
    if ($specResult) {
        while ($row = $specResult->fetch_assoc()) {
            $specializations[] = $row['specialization'];
        }
    }
    
    closeDBConnection($connection);
} catch (Exception $e) {
    error_log("Specializations query error: " . $e->getMessage());
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
                        <h1 class="h3 mb-0">Doctors Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Doctors</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="workload.php" class="btn btn-info mr-2">
                            <i class="fas fa-chart-bar"></i> Workload Dashboard
                        </a>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Doctor
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>

                <!-- Search and Filter Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end">
                            <div class="col-md-4">
                                <label for="search">Search Doctors</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                       placeholder="Search by name, phone, or email">
                            </div>
                            <div class="col-md-3">
                                <label for="specialization">Specialization</label>
                                <select class="form-control" id="specialization" name="specialization">
                                    <option value="">All Specializations</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec); ?>" 
                                                <?php echo $searchSpecialization == $spec ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($spec); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="index.php" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">
                                    Showing <?php echo count($doctors); ?> of <?php echo $totalRecords; ?> doctors
                                </small>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Doctors List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-md"></i> Doctors List
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($doctors)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No doctors found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($searchTerm) || !empty($searchSpecialization)): ?>
                                        No doctors match your search criteria. <a href="index.php">View all doctors</a>
                                    <?php else: ?>
                                        Start by adding your first doctor to the system.
                                    <?php endif; ?>
                                </p>
                                <a href="add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First Doctor
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Specialization</th>
                                            <th>Contact</th>
                                            <th>Consultation Fee</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2">
                                                            <i class="fas fa-user-md"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                                            <?php if (!empty($doctor['email'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($doctor['email']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($doctor['specialization'])): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($doctor['phone'])): ?>
                                                        <i class="fas fa-phone text-muted mr-1"></i>
                                                        <?php echo htmlspecialchars($doctor['phone']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($doctor['consultation_fee'] > 0): ?>
                                                        <strong class="text-success"><?php echo formatCurrency($doctor['consultation_fee']); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($doctor['created_at'], 'd M Y'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $doctor['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $doctor['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['name'], ENT_QUOTES); ?>')" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="mt-4">
                                    <?php
                                    $baseUrl = 'index.php';
                                    $params = [];
                                    if (!empty($searchTerm)) $params[] = 'search=' . urlencode($searchTerm);
                                    if (!empty($searchSpecialization)) $params[] = 'specialization=' . urlencode($searchSpecialization);
                                    if (!empty($params)) $baseUrl .= '?' . implode('&', $params);
                                    
                                    echo getPagination($currentPage, $totalPages, $baseUrl);
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete Dr. <strong id="doctorName"></strong>?</p>
                <p class="text-danger"><small><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete Doctor</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(doctorId, doctorName) {
    $('#doctorName').text(doctorName);
    $('#deleteConfirmBtn').attr('href', 'delete.php?id=' + doctorId);
    $('#deleteModal').modal('show');
}

$(document).ready(function() {
    // Auto-submit search form on specialization change
    $('#specialization').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Clear search functionality
    $('.btn-secondary').on('click', function(e) {
        if ($(this).find('.fa-times').length > 0) {
            e.preventDefault();
            window.location.href = 'index.php';
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>