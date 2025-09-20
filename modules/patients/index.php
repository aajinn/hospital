<?php
/**
 * Patients Module - Main Index
 * Hospital Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Initialize variables
$patients = [];
$totalPatients = 0;
$currentPage = 1;
$totalPages = 1;
$searchTerm = '';
$searchBy = 'name';

// Get search parameters
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}
if (isset($_GET['search_by'])) {
    $searchBy = $_GET['search_by'];
}

// Get current page
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = (int)$_GET['page'];
    if ($currentPage < 1) $currentPage = 1;
}

// Calculate offset for pagination
$offset = ($currentPage - 1) * RECORDS_PER_PAGE;

try {
    $connection = getDBConnection();
    
    // Build search query
    $whereClause = '';
    $params = [];
    $types = '';
    
    if (!empty($searchTerm)) {
        switch ($searchBy) {
            case 'name':
                $whereClause = "WHERE name LIKE ?";
                $params[] = "%$searchTerm%";
                $types .= 's';
                break;
            case 'phone':
                $whereClause = "WHERE phone LIKE ?";
                $params[] = "%$searchTerm%";
                $types .= 's';
                break;
            case 'patient_id':
                $whereClause = "WHERE patient_id LIKE ?";
                $params[] = "%$searchTerm%";
                $types .= 's';
                break;
            case 'all':
                $whereClause = "WHERE name LIKE ? OR phone LIKE ? OR patient_id LIKE ?";
                $params[] = "%$searchTerm%";
                $params[] = "%$searchTerm%";
                $params[] = "%$searchTerm%";
                $types .= 'sss';
                break;
        }
    }
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM patients $whereClause";
    $countStmt = $connection->prepare($countQuery);
    
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalPatients = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Calculate total pages
    $totalPages = ceil($totalPatients / RECORDS_PER_PAGE);
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * RECORDS_PER_PAGE;
    }
    
    // Get patients with pagination
    $query = "SELECT * FROM patients $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $connection->prepare($query);
    
    // Add limit and offset parameters
    $params[] = RECORDS_PER_PAGE;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    $stmt->close();
    closeDBConnection($connection);
    
} catch (Exception $e) {
    error_log("Patient listing error: " . $e->getMessage());
    $error = "An error occurred while loading patients.";
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
                        <h1 class="h3 mb-0">Patient Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Patients</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Patient
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <!-- Display session messages -->
                <?php echo displaySessionMessage(); ?>
                
                <!-- Display errors -->
                <?php if (isset($error)): ?>
                    <?php echo showErrorMessage($error); ?>
                <?php endif; ?>

                <!-- Search Box -->
                <div class="search-box">
                    <form method="GET" action="" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="search">Search Patients</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                   placeholder="Enter search term...">
                        </div>
                        <div class="col-md-3">
                            <label for="search_by">Search By</label>
                            <select class="form-control" id="search_by" name="search_by">
                                <option value="name" <?php echo $searchBy == 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="phone" <?php echo $searchBy == 'phone' ? 'selected' : ''; ?>>Phone</option>
                                <option value="patient_id" <?php echo $searchBy == 'patient_id' ? 'selected' : ''; ?>>Patient ID</option>
                                <option value="all" <?php echo $searchBy == 'all' ? 'selected' : ''; ?>>All Fields</option>
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
                                Total: <?php echo $totalPatients; ?> patients
                            </small>
                        </div>
                    </form>
                </div>

                <!-- Patients List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users"></i> Patients List
                            <?php if (!empty($searchTerm)): ?>
                                <small class="text-muted">- Search results for "<?php echo htmlspecialchars($searchTerm); ?>"</small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($patients)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Patient ID</th>
                                            <th>Name</th>
                                            <th>Age/Gender</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td>
                                                    <span class="patient-id"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($patient['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($patient['age']); ?> years, 
                                                    <?php echo htmlspecialchars($patient['gender']); ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($patient['email'])): ?>
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($patient['created_at'], 'd M Y'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="view.php?id=<?php echo $patient['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $patient['id']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="admit.php?id=<?php echo $patient['id']; ?>" 
                                                           class="btn btn-sm btn-success" title="Admit Patient">
                                                            <i class="fas fa-hospital"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Patients Found</h5>
                                <?php if (!empty($searchTerm)): ?>
                                    <p class="text-muted">No patients match your search criteria.</p>
                                    <a href="index.php" class="btn btn-secondary">View All Patients</a>
                                <?php else: ?>
                                    <p class="text-muted">No patients have been registered yet.</p>
                                    <a href="add.php" class="btn btn-primary">Add First Patient</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <small class="text-muted">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + RECORDS_PER_PAGE, $totalPatients); ?> 
                                of <?php echo $totalPatients; ?> patients
                            </small>
                        </div>
                        <div>
                            <?php
                            $baseUrl = 'index.php';
                            $queryParams = [];
                            if (!empty($searchTerm)) {
                                $queryParams[] = 'search=' . urlencode($searchTerm);
                                $queryParams[] = 'search_by=' . urlencode($searchBy);
                            }
                            if (!empty($queryParams)) {
                                $baseUrl .= '?' . implode('&', $queryParams);
                            }
                            echo getPagination($currentPage, $totalPages, $baseUrl);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-submit search form on enter
    $('#search').on('keypress', function(e) {
        if (e.which == 13) {
            $(this).closest('form').submit();
        }
    });
    
    // Clear search when clicking clear button
    $('.btn-secondary').on('click', function(e) {
        if ($(this).find('.fa-times').length > 0) {
            $('#search').val('');
            $('#search_by').val('name');
        }
    });
    
    // Highlight search terms in results
    <?php if (!empty($searchTerm)): ?>
    var searchTerm = '<?php echo addslashes($searchTerm); ?>';
    if (searchTerm) {
        $('tbody td').each(function() {
            var text = $(this).html();
            var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            $(this).html(text.replace(regex, '<mark>$1</mark>'));
        });
    }
    <?php endif; ?>
});
</script>

<?php include_once '../../includes/footer.php'; ?>