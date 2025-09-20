<?php
/**
 * Common Functions
 * Hospital Management System
 */

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (10 digits)
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

/**
 * Validate date format
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate required fields
 */
function validateRequired($fields) {
    $errors = [];
    foreach ($fields as $field => $value) {
        if (empty(trim($value))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

/**
 * Validate numeric value
 */
function validateNumeric($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    if ($min !== null && $value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}

/**
 * Generate unique patient ID
 */
function generatePatientId() {
    try {
        $connection = getDBConnection();
        
        // Get the last patient ID
        $query = "SELECT patient_id FROM patients ORDER BY id DESC LIMIT 1";
        $result = $connection->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastId = $row['patient_id'];
            $number = (int)substr($lastId, strlen(PATIENT_ID_PREFIX));
            $newNumber = $number + 1;
        } else {
            $newNumber = 1;
        }
        
        closeDBConnection($connection);
        return PATIENT_ID_PREFIX . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Generate Patient ID Error: " . $e->getMessage());
        // Fallback to timestamp-based ID
        return PATIENT_ID_PREFIX . date('ymd') . rand(10, 99);
    }
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd-m-Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get pagination HTML
 */
function getPagination($currentPage, $totalPages, $baseUrl) {
    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center">';
    
    // Determine URL separator
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '">Previous</a>';
        $html .= '</li>';
    }
    
    // Calculate page range to show
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    // First page link if not in range
    if ($startPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a>';
        $html .= '</li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }
    
    // Last page link if not in range
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a>';
        $html .= '</li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '">Next</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Display success message
 */
function showSuccessMessage($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> ' . $message . '
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>';
}

/**
 * Display error message
 */
function showErrorMessage($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> ' . $message . '
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>';
}

/**
 * Display warning message
 */
function showWarningMessage($message) {
    return '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> ' . $message . '
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>';
}

/**
 * Check if medicine stock is low
 */
function isLowStock($quantity, $minQuantity) {
    return $quantity <= $minQuantity;
}

/**
 * Check if medicine is near expiry
 */
function isNearExpiry($expiryDate, $days = EXPIRY_ALERT_DAYS) {
    $today = new DateTime();
    $expiry = new DateTime($expiryDate);
    $interval = $today->diff($expiry);
    
    return ($interval->days <= $days && $expiry >= $today);
}

/**
 * Get low stock medicines
 */
function getLowStockMedicines() {
    $connection = getDBConnection();
    $query = "SELECT * FROM medicines WHERE quantity <= min_quantity";
    $result = $connection->query($query);
    
    $medicines = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $medicines[] = $row;
        }
    }
    
    closeDBConnection($connection);
    return $medicines;
}

/**
 * Get medicines near expiry
 */
function getMedicinesNearExpiry() {
    $connection = getDBConnection();
    $alertDate = date('Y-m-d', strtotime('+' . EXPIRY_ALERT_DAYS . ' days'));
    $query = "SELECT * FROM medicines WHERE expiry_date <= '$alertDate' AND expiry_date >= CURDATE()";
    $result = $connection->query($query);
    
    $medicines = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $medicines[] = $row;
        }
    }
    
    closeDBConnection($connection);
    return $medicines;
}

/**
 * Generic function to insert data into any table
 */
function insertRecord($table, $data) {
    try {
        $connection = getDBConnection();
        
        // Sanitize data
        $data = sanitizeInput($data);
        
        // Prepare column names and placeholders
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $connection->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Prepare types string and values array
        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        // Bind parameters
        if (!empty($values)) {
            $stmt->bind_param($types, ...$values);
        }
        
        $result = $stmt->execute();
        $insertId = $connection->insert_id;
        
        $stmt->close();
        closeDBConnection($connection);
        
        if ($result) {
            return $insertId;
        } else {
            throw new Exception("Insert failed");
        }
    } catch (Exception $e) {
        error_log("Insert Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generic function to update data in any table
 */
function updateRecord($table, $data, $where) {
    try {
        $connection = getDBConnection();
        
        // Sanitize data
        $data = sanitizeInput($data);
        $where = sanitizeInput($where);
        
        // Prepare SET clause
        $setParts = [];
        $params = [];
        $types = '';
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $setClause = implode(', ', $setParts);
        
        // Prepare WHERE clause
        $whereParts = [];
        foreach ($where as $key => $value) {
            $whereParts[] = "$key = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $whereClause = implode(' AND ', $whereParts);
        
        $query = "UPDATE $table SET $setClause WHERE $whereClause";
        $stmt = $connection->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Bind parameters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        closeDBConnection($connection);
        
        return $result;
    } catch (Exception $e) {
        error_log("Update Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generic function to delete data from any table
 */
function deleteRecord($table, $where) {
    try {
        $connection = getDBConnection();
        
        // Sanitize where conditions
        $where = sanitizeInput($where);
        
        // Prepare WHERE clause
        $whereParts = [];
        $params = [];
        $types = '';
        
        foreach ($where as $key => $value) {
            $whereParts[] = "$key = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $whereClause = implode(' AND ', $whereParts);
        
        $query = "DELETE FROM $table WHERE $whereClause";
        $stmt = $connection->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Bind parameters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        closeDBConnection($connection);
        
        return $result;
    } catch (Exception $e) {
        error_log("Delete Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generic function to select data from any table
 */
function selectRecords($table, $where = [], $orderBy = '', $limit = '') {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT * FROM $table";
        $params = [];
        $types = '';
        
        // Add WHERE clause if conditions provided
        if (!empty($where)) {
            $where = sanitizeInput($where);
            $whereParts = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "$key = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            }
            $query .= " WHERE " . implode(' AND ', $whereParts);
        }
        
        // Add ORDER BY clause
        if (!empty($orderBy)) {
            $query .= " ORDER BY $orderBy";
        }
        
        // Add LIMIT clause
        if (!empty($limit)) {
            $query .= " LIMIT $limit";
        }
        
        $stmt = $connection->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Bind parameters if WHERE conditions exist
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
        
        $stmt->close();
        closeDBConnection($connection);
        return $records;
    } catch (Exception $e) {
        error_log("Select Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get single record by ID
 */
function getRecordById($table, $id) {
    $records = selectRecords($table, ['id' => $id]);
    return !empty($records) ? $records[0] : null;
}

/**
 * Count total records in a table with optional conditions
 */
function countRecords($table, $where = []) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT COUNT(*) as total FROM $table";
        $params = [];
        $types = '';
        
        // Add WHERE clause if conditions provided
        if (!empty($where)) {
            $where = sanitizeInput($where);
            $whereParts = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "$key = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            }
            $query .= " WHERE " . implode(' AND ', $whereParts);
        }
        
        $stmt = $connection->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Bind parameters if WHERE conditions exist
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        closeDBConnection($connection);
        return (int)$row['total'];
    } catch (Exception $e) {
        error_log("Count Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dateOfBirth) {
    $today = new DateTime();
    $dob = new DateTime($dateOfBirth);
    $age = $today->diff($dob);
    return $age->y;
}

/**
 * Generate random password
 */
function generatePassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Log activity
 */
function logActivity($action, $details = '') {
    try {
        $connection = getDBConnection();
        
        $data = [
            'action' => $action,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
        
        // Create activity_log table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            timestamp DATETIME NOT NULL,
            ip_address VARCHAR(45)
        )";
        $connection->query($createTable);
        
        insertRecord('activity_log', $data);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Display session message
 */
function displaySessionMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        switch ($type) {
            case 'success':
                return showSuccessMessage($message);
            case 'error':
                return showErrorMessage($message);
            case 'warning':
                return showWarningMessage($message);
            default:
                return '<div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i> ' . $message . '
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>';
        }
    }
    return '';
}


function getDoctorAssignmentRecommendations($specialization = '') {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
            d.id,
            d.name,
            d.specialization,
            d.consultation_fee,
            COUNT(CASE WHEN a.status = 'Admitted' THEN 1 END) as active_assignments,
            COUNT(a.id) as total_assignments
        FROM doctors d
        LEFT JOIN admissions a ON d.id = a.doctor_id
        WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (!empty($specialization)) {
            $query .= " AND d.specialization LIKE ?";
            $params[] = "%$specialization%";
            $types .= 's';
        }
        
        $query .= " GROUP BY d.id, d.name, d.specialization, d.consultation_fee
                   ORDER BY active_assignments ASC, total_assignments ASC";
        
        $stmt = $connection->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $row['workload_level'] = 'low';
            if ($row['active_assignments'] >= 10) {
                $row['workload_level'] = 'high';
            } elseif ($row['active_assignments'] >= 5) {
                $row['workload_level'] = 'medium';
            }
            $recommendations[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $recommendations;
    } catch (Exception $e) {
        error_log("Doctor recommendations error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get doctor assignment history for a specific patient
 */
function getPatientDoctorHistory($patientId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
            a.id as admission_id,
            a.admission_date,
            a.discharge_date,
            a.reason,
            a.status,
            d.id as doctor_id,
            d.name as doctor_name,
            d.specialization,
            CASE 
                WHEN a.discharge_date IS NOT NULL THEN DATEDIFF(a.discharge_date, a.admission_date)
                ELSE DATEDIFF(CURDATE(), a.admission_date)
            END as stay_days
        FROM admissions a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        ORDER BY a.admission_date DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $history;
    } catch (Exception $e) {
        error_log("Patient doctor history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get doctor workload statistics
 */
function getDoctorWorkloadStats($doctorId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
            COUNT(*) as total_assignments,
            COUNT(CASE WHEN status = 'Admitted' THEN 1 END) as active_assignments,
            COUNT(CASE WHEN status = 'Discharged' THEN 1 END) as completed_assignments,
            COUNT(CASE WHEN admission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as weekly_assignments,
            COUNT(CASE WHEN admission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as monthly_assignments,
            AVG(CASE WHEN discharge_date IS NOT NULL THEN DATEDIFF(discharge_date, admission_date) END) as avg_stay_days,
            MAX(admission_date) as last_assignment_date
        FROM admissions 
        WHERE doctor_id = ?";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $stats;
    } catch (Exception $e) {
        error_log("Doctor workload stats error: " . $e->getMessage());
        return [];
    }
}
/**

 * Billing System Functions
 */

/**
 * Generate bill for a patient
 */
function generateBill($patientId, $admissionId = null, $billData = []) {
    try {
        $connection = getDBConnection();
        
        // Validate patient exists
        $patientCheck = "SELECT id FROM patients WHERE id = ?";
        $stmt = $connection->prepare($patientCheck);
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Patient not found");
        }
        $stmt->close();
        
        // Prepare bill data with defaults
        $defaultBillData = [
            'patient_id' => $patientId,
            'admission_id' => $admissionId,
            'bill_date' => date('Y-m-d'),
            'doctor_fee' => 0.00,
            'room_charges' => 0.00,
            'medicine_charges' => 0.00,
            'other_charges' => 0.00,
            'status' => 'Pending'
        ];
        
        $billData = array_merge($defaultBillData, $billData);
        
        // Calculate total amount
        $billData['total_amount'] = $billData['doctor_fee'] + $billData['room_charges'] + 
                                   $billData['medicine_charges'] + $billData['other_charges'];
        
        // Insert bill
        $billId = insertRecord('bills', $billData);
        
        if ($billId) {
            logActivity('Bill Generated', "Bill ID: $billId for Patient ID: $patientId");
            closeDBConnection($connection);
            return $billId;
        } else {
            throw new Exception("Failed to generate bill");
        }
        
    } catch (Exception $e) {
        error_log("Generate bill error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update bill status based on payments
 */
function updateBillStatus($billId) {
    try {
        $connection = getDBConnection();
        
        // Get bill total and payments
        $query = "SELECT b.total_amount, COALESCE(SUM(p.amount), 0) as paid_amount
                  FROM bills b
                  LEFT JOIN payments p ON b.id = p.bill_id
                  WHERE b.id = ?
                  GROUP BY b.id, b.total_amount";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $billId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $totalAmount = $row['total_amount'];
            $paidAmount = $row['paid_amount'];
            
            // Determine status
            $status = 'Pending';
            if ($paidAmount >= $totalAmount) {
                $status = 'Paid';
            } elseif ($paidAmount > 0) {
                $status = 'Partial';
            }
            
            // Update bill status
            $updateQuery = "UPDATE bills SET status = ? WHERE id = ?";
            $updateStmt = $connection->prepare($updateQuery);
            $updateStmt->bind_param('si', $status, $billId);
            $updateResult = $updateStmt->execute();
            
            $updateStmt->close();
            $stmt->close();
            closeDBConnection($connection);
            
            return $updateResult;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        return false;
        
    } catch (Exception $e) {
        error_log("Update bill status error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get bill details with patient information
 */
function getBillDetails($billId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT b.*, p.name as patient_name, p.patient_id, p.phone, p.email,
                         a.admission_date, a.discharge_date, a.reason as admission_reason,
                         d.name as doctor_name, d.specialization,
                         COALESCE(SUM(pay.amount), 0) as paid_amount,
                         (b.total_amount - COALESCE(SUM(pay.amount), 0)) as pending_amount
                  FROM bills b
                  JOIN patients p ON b.patient_id = p.id
                  LEFT JOIN admissions a ON b.admission_id = a.id
                  LEFT JOIN doctors d ON a.doctor_id = d.id
                  LEFT JOIN payments pay ON b.id = pay.bill_id
                  WHERE b.id = ?
                  GROUP BY b.id";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $billId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bill = null;
        if ($result->num_rows > 0) {
            $bill = $result->fetch_assoc();
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $bill;
        
    } catch (Exception $e) {
        error_log("Get bill details error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get patient billing history
 */
function getPatientBillingHistory($patientId, $limit = null) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT b.*, 
                         COALESCE(SUM(p.amount), 0) as paid_amount,
                         (b.total_amount - COALESCE(SUM(p.amount), 0)) as pending_amount
                  FROM bills b
                  LEFT JOIN payments p ON b.id = p.bill_id
                  WHERE b.patient_id = ?
                  GROUP BY b.id
                  ORDER BY b.bill_date DESC, b.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ?";
        }
        
        $stmt = $connection->prepare($query);
        
        if ($limit) {
            $stmt->bind_param('ii', $patientId, $limit);
        } else {
            $stmt->bind_param('i', $patientId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bills = [];
        while ($row = $result->fetch_assoc()) {
            $bills[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $bills;
        
    } catch (Exception $e) {
        error_log("Get patient billing history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pending bills summary
 */
function getPendingBillsSummary() {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    COUNT(*) as total_pending_bills,
                    SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending_amount
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE b.status IN ('Pending', 'Partial')";
        
        $result = $connection->query($query);
        $summary = $result->fetch_assoc();
        
        closeDBConnection($connection);
        return $summary;
        
    } catch (Exception $e) {
        error_log("Get pending bills summary error: " . $e->getMessage());
        return ['total_pending_bills' => 0, 'total_pending_amount' => 0];
    }
}

/**
 * Get billing statistics for dashboard
 */
function getBillingStats($period = 'month') {
    try {
        $connection = getDBConnection();
        
        // Determine date range based on period
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(b.bill_date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = "1=1"; // All time
        }
        
        $query = "SELECT 
                    COUNT(*) as total_bills,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_collected,
                    SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending,
                    COUNT(CASE WHEN b.status = 'Paid' THEN 1 END) as paid_bills,
                    COUNT(CASE WHEN b.status = 'Partial' THEN 1 END) as partial_bills,
                    COUNT(CASE WHEN b.status = 'Pending' THEN 1 END) as pending_bills
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE $dateCondition";
        
        $result = $connection->query($query);
        $stats = $result->fetch_assoc();
        
        closeDBConnection($connection);
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get billing stats error: " . $e->getMessage());
        return [
            'total_bills' => 0,
            'total_billed' => 0,
            'total_collected' => 0,
            'total_pending' => 0,
            'paid_bills' => 0,
            'partial_bills' => 0,
            'pending_bills' => 0
        ];
    }
}

/**
 * Validate bill data
 */
function validateBillData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['patient_id'])) {
        $errors[] = "Patient is required";
    }
    
    if (empty($data['bill_date'])) {
        $errors[] = "Bill date is required";
    } elseif (!validateDate($data['bill_date'])) {
        $errors[] = "Invalid bill date format";
    }
    
    // Numeric validations
    $numericFields = ['doctor_fee', 'room_charges', 'medicine_charges', 'other_charges'];
    foreach ($numericFields as $field) {
        if (isset($data[$field]) && !validateNumeric($data[$field], 0)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be a valid positive number";
        }
    }
    
    return $errors;
}

/**
 * Calculate bill total from components
 */
function calculateBillTotal($doctorFee, $roomCharges, $medicineCharges, $otherCharges) {
    return (float)$doctorFee + (float)$roomCharges + (float)$medicineCharges + (float)$otherCharges;
}

/**
 * Get bills by status
 */
function getBillsByStatus($status, $limit = null) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT b.*, p.name as patient_name, p.patient_id,
                         COALESCE(SUM(pay.amount), 0) as paid_amount,
                         (b.total_amount - COALESCE(SUM(pay.amount), 0)) as pending_amount
                  FROM bills b
                  JOIN patients p ON b.patient_id = p.id
                  LEFT JOIN payments pay ON b.id = pay.bill_id
                  WHERE b.status = ?
                  GROUP BY b.id
                  ORDER BY b.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ?";
        }
        
        $stmt = $connection->prepare($query);
        
        if ($limit) {
            $stmt->bind_param('si', $status, $limit);
        } else {
            $stmt->bind_param('s', $status);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bills = [];
        while ($row = $result->fetch_assoc()) {
            $bills[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $bills;
        
    } catch (Exception $e) {
        error_log("Get bills by status error: " . $e->getMessage());
        return [];
    }
}

/**
 * Payment Processing Functions
 */

/**
 * Add payment to a bill
 */
function addPayment($billId, $paymentData) {
    try {
        $connection = getDBConnection();
        
        // Validate bill exists and get current status
        $billQuery = "SELECT id, total_amount, status FROM bills WHERE id = ?";
        $billStmt = $connection->prepare($billQuery);
        $billStmt->bind_param('i', $billId);
        $billStmt->execute();
        $billResult = $billStmt->get_result();
        
        if ($billResult->num_rows == 0) {
            throw new Exception("Bill not found");
        }
        
        $bill = $billResult->fetch_assoc();
        $billStmt->close();
        
        // Check if bill is already fully paid
        if ($bill['status'] == 'Paid') {
            throw new Exception("Bill is already fully paid");
        }
        
        // Get current paid amount
        $paidQuery = "SELECT COALESCE(SUM(amount), 0) as paid_amount FROM payments WHERE bill_id = ?";
        $paidStmt = $connection->prepare($paidQuery);
        $paidStmt->bind_param('i', $billId);
        $paidStmt->execute();
        $paidResult = $paidStmt->get_result();
        $currentPaid = $paidResult->fetch_assoc()['paid_amount'];
        $paidStmt->close();
        
        // Validate payment amount
        $pendingAmount = $bill['total_amount'] - $currentPaid;
        if ($paymentData['amount'] > $pendingAmount) {
            throw new Exception("Payment amount exceeds pending amount");
        }
        
        // Prepare payment data
        $paymentData['bill_id'] = $billId;
        
        // Insert payment
        $paymentId = insertRecord('payments', $paymentData);
        
        if ($paymentId) {
            // Update bill status
            updateBillStatus($billId);
            
            logActivity('Payment Added', "Payment ID: $paymentId for Bill ID: $billId, Amount: " . formatCurrency($paymentData['amount']));
            closeDBConnection($connection);
            return $paymentId;
        } else {
            throw new Exception("Failed to add payment");
        }
        
    } catch (Exception $e) {
        error_log("Add payment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment history for a bill
 */
function getBillPayments($billId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC, created_at DESC";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $billId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $payments;
        
    } catch (Exception $e) {
        error_log("Get bill payments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get payment details by ID
 */
function getPaymentDetails($paymentId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT p.*, b.total_amount as bill_total, pt.name as patient_name, pt.patient_id
                  FROM payments p
                  JOIN bills b ON p.bill_id = b.id
                  JOIN patients pt ON b.patient_id = pt.id
                  WHERE p.id = ?";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payment = null;
        if ($result->num_rows > 0) {
            $payment = $result->fetch_assoc();
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $payment;
        
    } catch (Exception $e) {
        error_log("Get payment details error: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate payment data
 */
function validatePaymentData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['amount'])) {
        $errors[] = "Payment amount is required";
    } elseif (!validateNumeric($data['amount'], 0.01)) {
        $errors[] = "Payment amount must be greater than 0";
    }
    
    if (empty($data['payment_date'])) {
        $errors[] = "Payment date is required";
    } elseif (!validateDate($data['payment_date'])) {
        $errors[] = "Invalid payment date format";
    }
    
    if (empty($data['payment_method'])) {
        $errors[] = "Payment method is required";
    } else {
        $validMethods = ['Cash', 'Card', 'UPI', 'Bank Transfer', 'Cheque'];
        if (!in_array($data['payment_method'], $validMethods)) {
            $errors[] = "Invalid payment method";
        }
    }
    
    return $errors;
}

/**
 * Get payment statistics
 */
function getPaymentStats($period = 'month') {
    try {
        $connection = getDBConnection();
        
        // Determine date range based on period
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(p.payment_date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = "p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = "1=1"; // All time
        }
        
        $query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(p.amount) as total_amount,
                    AVG(p.amount) as average_amount,
                    COUNT(CASE WHEN p.payment_method = 'Cash' THEN 1 END) as cash_payments,
                    COUNT(CASE WHEN p.payment_method = 'Card' THEN 1 END) as card_payments,
                    COUNT(CASE WHEN p.payment_method = 'UPI' THEN 1 END) as upi_payments,
                    COUNT(CASE WHEN p.payment_method = 'Bank Transfer' THEN 1 END) as bank_payments,
                    COUNT(CASE WHEN p.payment_method = 'Cheque' THEN 1 END) as cheque_payments
                  FROM payments p
                  WHERE $dateCondition";
        
        $result = $connection->query($query);
        $stats = $result->fetch_assoc();
        
        closeDBConnection($connection);
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get payment stats error: " . $e->getMessage());
        return [
            'total_payments' => 0,
            'total_amount' => 0,
            'average_amount' => 0,
            'cash_payments' => 0,
            'card_payments' => 0,
            'upi_payments' => 0,
            'bank_payments' => 0,
            'cheque_payments' => 0
        ];
    }
}

/**
 * Get recent payments
 */
function getRecentPayments($limit = 10) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT p.*, b.id as bill_id, pt.name as patient_name, pt.patient_id
                  FROM payments p
                  JOIN bills b ON p.bill_id = b.id
                  JOIN patients pt ON b.patient_id = pt.id
                  ORDER BY p.created_at DESC
                  LIMIT ?";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $payments;
        
    } catch (Exception $e) {
        error_log("Get recent payments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get payments by method
 */
function getPaymentsByMethod($method, $limit = null) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT p.*, b.id as bill_id, pt.name as patient_name, pt.patient_id
                  FROM payments p
                  JOIN bills b ON p.bill_id = b.id
                  JOIN patients pt ON b.patient_id = pt.id
                  WHERE p.payment_method = ?
                  ORDER BY p.payment_date DESC, p.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ?";
        }
        
        $stmt = $connection->prepare($query);
        
        if ($limit) {
            $stmt->bind_param('si', $method, $limit);
        } else {
            $stmt->bind_param('s', $method);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $payments;
        
    } catch (Exception $e) {
        error_log("Get payments by method error: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate partial payment percentage
 */
function calculatePaymentPercentage($paidAmount, $totalAmount) {
    if ($totalAmount <= 0) {
        return 0;
    }
    
    return min(100, ($paidAmount / $totalAmount) * 100);
}

/**
 * Get payment method statistics
 */
function getPaymentMethodStats($period = 'month') {
    try {
        $connection = getDBConnection();
        
        // Determine date range based on period
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(payment_date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = "payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = "1=1"; // All time
        }
        
        $query = "SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount
                  FROM payments
                  WHERE $dateCondition
                  GROUP BY payment_method
                  ORDER BY total_amount DESC";
        
        $result = $connection->query($query);
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        closeDBConnection($connection);
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get payment method stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * Billing Reports and History Functions
 */

/**
 * Generate billing summary report
 */
function getBillingSummaryReport($dateFrom, $dateTo) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    COUNT(*) as total_bills,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_collected,
                    SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending,
                    COUNT(CASE WHEN b.status = 'Paid' THEN 1 END) as paid_bills,
                    COUNT(CASE WHEN b.status = 'Partial' THEN 1 END) as partial_bills,
                    COUNT(CASE WHEN b.status = 'Pending' THEN 1 END) as pending_bills,
                    AVG(b.total_amount) as average_bill_amount,
                    SUM(b.doctor_fee) as total_doctor_fees,
                    SUM(b.room_charges) as total_room_charges,
                    SUM(b.medicine_charges) as total_medicine_charges,
                    SUM(b.other_charges) as total_other_charges
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE b.bill_date BETWEEN ? AND ?";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $summary;
        
    } catch (Exception $e) {
        error_log("Billing summary report error: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate daily billing report
 */
function getDailyBillingReport($dateFrom, $dateTo) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    DATE(b.bill_date) as bill_date,
                    COUNT(*) as bills_count,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_collected,
                    SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending,
                    AVG(b.total_amount) as average_bill
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE b.bill_date BETWEEN ? AND ?
                  GROUP BY DATE(b.bill_date)
                  ORDER BY bill_date DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $report;
        
    } catch (Exception $e) {
        error_log("Daily billing report error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pending dues report
 */
function getPendingDuesReport($dateFrom = null, $dateTo = null) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    b.id, b.bill_date, b.total_amount,
                    p.name as patient_name, p.patient_id, p.phone, p.email,
                    COALESCE(payments.paid_amount, 0) as paid_amount,
                    (b.total_amount - COALESCE(payments.paid_amount, 0)) as pending_amount,
                    DATEDIFF(CURDATE(), b.bill_date) as days_pending,
                    b.status
                  FROM bills b
                  JOIN patients p ON b.patient_id = p.id
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE b.status IN ('Pending', 'Partial')";
        
        $params = [];
        $types = '';
        
        if ($dateFrom && $dateTo) {
            $query .= " AND b.bill_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
            $types .= 'ss';
        }
        
        $query .= " ORDER BY days_pending DESC, pending_amount DESC";
        
        $stmt = $connection->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $report;
        
    } catch (Exception $e) {
        error_log("Pending dues report error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get collection efficiency report
 */
function getCollectionEfficiencyReport($period = 'month') {
    try {
        $connection = getDBConnection();
        
        // Determine date range based on period
        $dateCondition = '';
        switch ($period) {
            case 'week':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = "1=1"; // All time
        }
        
        $query = "SELECT 
                    COUNT(*) as total_bills,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_collected,
                    (SUM(COALESCE(payments.paid_amount, 0)) / SUM(b.total_amount)) * 100 as collection_rate,
                    COUNT(CASE WHEN b.status = 'Paid' THEN 1 END) as fully_paid_bills,
                    COUNT(CASE WHEN b.status = 'Partial' THEN 1 END) as partially_paid_bills,
                    COUNT(CASE WHEN b.status = 'Pending' THEN 1 END) as unpaid_bills,
                    AVG(DATEDIFF(COALESCE(first_payment.payment_date, CURDATE()), b.bill_date)) as avg_collection_days
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  LEFT JOIN (
                      SELECT bill_id, MIN(payment_date) as payment_date
                      FROM payments
                      GROUP BY bill_id
                  ) first_payment ON b.id = first_payment.bill_id
                  WHERE $dateCondition";
        
        $result = $connection->query($query);
        $report = $result->fetch_assoc();
        
        closeDBConnection($connection);
        return $report;
        
    } catch (Exception $e) {
        error_log("Collection efficiency report error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get top patients by billing amount
 */
function getTopPatientsByBilling($limit = 10, $period = 'all') {
    try {
        $connection = getDBConnection();
        
        // Determine date range based on period
        $dateCondition = '';
        switch ($period) {
            case 'month':
                $dateCondition = "AND b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $dateCondition = "AND b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $dateCondition = "AND b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = ""; // All time
        }
        
        $query = "SELECT 
                    p.id, p.patient_id, p.name, p.phone,
                    COUNT(b.id) as total_bills,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_paid,
                    SUM(b.total_amount - COALESCE(payments.paid_amount, 0)) as total_pending
                  FROM patients p
                  JOIN bills b ON p.id = b.patient_id
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE 1=1 $dateCondition
                  GROUP BY p.id, p.patient_id, p.name, p.phone
                  ORDER BY total_billed DESC
                  LIMIT ?";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $report;
        
    } catch (Exception $e) {
        error_log("Top patients by billing error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get billing trends data for charts
 */
function getBillingTrends($period = 'month', $groupBy = 'day') {
    try {
        $connection = getDBConnection();
        
        // Determine date range and grouping
        $dateCondition = '';
        $groupByClause = '';
        $selectClause = '';
        
        switch ($period) {
            case 'week':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $dateCondition = "b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
        }
        
        switch ($groupBy) {
            case 'day':
                $selectClause = "DATE(b.bill_date) as period";
                $groupByClause = "DATE(b.bill_date)";
                break;
            case 'week':
                $selectClause = "YEARWEEK(b.bill_date) as period";
                $groupByClause = "YEARWEEK(b.bill_date)";
                break;
            case 'month':
                $selectClause = "DATE_FORMAT(b.bill_date, '%Y-%m') as period";
                $groupByClause = "DATE_FORMAT(b.bill_date, '%Y-%m')";
                break;
        }
        
        $query = "SELECT 
                    $selectClause,
                    COUNT(*) as bills_count,
                    SUM(b.total_amount) as total_billed,
                    SUM(COALESCE(payments.paid_amount, 0)) as total_collected
                  FROM bills b
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE $dateCondition
                  GROUP BY $groupByClause
                  ORDER BY period";
        
        $result = $connection->query($query);
        
        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = $row;
        }
        
        closeDBConnection($connection);
        return $trends;
        
    } catch (Exception $e) {
        error_log("Billing trends error: " . $e->getMessage());
        return [];
    }
}

/**
 * Export billing data to CSV
 */
function exportBillingDataToCSV($dateFrom, $dateTo, $status = null) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    b.id as bill_id,
                    b.bill_date,
                    p.patient_id,
                    p.name as patient_name,
                    p.phone,
                    b.doctor_fee,
                    b.room_charges,
                    b.medicine_charges,
                    b.other_charges,
                    b.total_amount,
                    COALESCE(payments.paid_amount, 0) as paid_amount,
                    (b.total_amount - COALESCE(payments.paid_amount, 0)) as pending_amount,
                    b.status,
                    b.created_at
                  FROM bills b
                  JOIN patients p ON b.patient_id = p.id
                  LEFT JOIN (
                      SELECT bill_id, SUM(amount) as paid_amount
                      FROM payments
                      GROUP BY bill_id
                  ) payments ON b.id = payments.bill_id
                  WHERE b.bill_date BETWEEN ? AND ?";
        
        $params = [$dateFrom, $dateTo];
        $types = 'ss';
        
        if ($status) {
            $query .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $query .= " ORDER BY b.bill_date DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Generate CSV content
        $csvContent = "Bill ID,Bill Date,Patient ID,Patient Name,Phone,Doctor Fee,Room Charges,Medicine Charges,Other Charges,Total Amount,Paid Amount,Pending Amount,Status,Created At\n";
        
        while ($row = $result->fetch_assoc()) {
            $csvContent .= implode(',', [
                $row['bill_id'],
                $row['bill_date'],
                '"' . $row['patient_id'] . '"',
                '"' . $row['patient_name'] . '"',
                '"' . $row['phone'] . '"',
                $row['doctor_fee'],
                $row['room_charges'],
                $row['medicine_charges'],
                $row['other_charges'],
                $row['total_amount'],
                $row['paid_amount'],
                $row['pending_amount'],
                '"' . $row['status'] . '"',
                $row['created_at']
            ]) . "\n";
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $csvContent;
        
    } catch (Exception $e) {
        error_log("Export billing data error: " . $e->getMessage());
        return false;
    }
}
/**

 * Pharmacy Alert Functions
 */

/**
 * Get pharmacy alert counts for dashboard
 */
function getPharmacyAlertCounts() {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT 
                    SUM(CASE WHEN quantity <= min_quantity THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                    SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as near_expiry_count,
                    SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_count
                  FROM medicines";
        
        $result = $connection->query($query);
        $counts = $result->fetch_assoc();
        
        closeDBConnection($connection);
        return $counts;
        
    } catch (Exception $e) {
        error_log("Get pharmacy alert counts error: " . $e->getMessage());
        return [
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'near_expiry_count' => 0,
            'expired_count' => 0
        ];
    }
}

/**
 * Get critical pharmacy alerts for dashboard widget
 */
function getCriticalPharmacyAlerts($limit = 5) {
    try {
        $connection = getDBConnection();
        
        $alerts = [];
        
        // Get out of stock medicines
        $outOfStockQuery = "SELECT name, 'out_of_stock' as alert_type, quantity, min_quantity 
                           FROM medicines 
                           WHERE quantity = 0 
                           ORDER BY name 
                           LIMIT ?";
        $stmt = $connection->prepare($outOfStockQuery);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'out_of_stock',
                'message' => "Out of stock: " . $row['name'],
                'severity' => 'danger',
                'icon' => 'fas fa-times-circle'
            ];
        }
        $stmt->close();
        
        // Get expired medicines
        $expiredQuery = "SELECT name, 'expired' as alert_type, expiry_date, quantity
                        FROM medicines 
                        WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND quantity > 0
                        ORDER BY expiry_date DESC 
                        LIMIT ?";
        $stmt = $connection->prepare($expiredQuery);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'expired',
                'message' => "Expired: " . $row['name'] . " (" . formatDate($row['expiry_date']) . ")",
                'severity' => 'danger',
                'icon' => 'fas fa-ban'
            ];
        }
        $stmt->close();
        
        // Get low stock medicines (excluding out of stock)
        $lowStockQuery = "SELECT name, 'low_stock' as alert_type, quantity, min_quantity 
                         FROM medicines 
                         WHERE quantity > 0 AND quantity <= min_quantity 
                         ORDER BY quantity ASC 
                         LIMIT ?";
        $stmt = $connection->prepare($lowStockQuery);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'low_stock',
                'message' => "Low stock: " . $row['name'] . " (" . $row['quantity'] . " left)",
                'severity' => 'warning',
                'icon' => 'fas fa-exclamation-triangle'
            ];
        }
        $stmt->close();
        
        // Get medicines expiring soon (within 7 days)
        $nearExpiryQuery = "SELECT name, 'near_expiry' as alert_type, expiry_date, 
                           DATEDIFF(expiry_date, CURDATE()) as days_left
                           FROM medicines 
                           WHERE expiry_date IS NOT NULL 
                           AND expiry_date >= CURDATE() 
                           AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                           AND quantity > 0
                           ORDER BY expiry_date ASC 
                           LIMIT ?";
        $stmt = $connection->prepare($nearExpiryQuery);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'near_expiry',
                'message' => "Expiring soon: " . $row['name'] . " (" . $row['days_left'] . " days)",
                'severity' => 'info',
                'icon' => 'fas fa-clock'
            ];
        }
        $stmt->close();
        
        closeDBConnection($connection);
        
        // Sort by severity (danger first, then warning, then info)
        usort($alerts, function($a, $b) {
            $severityOrder = ['danger' => 1, 'warning' => 2, 'info' => 3];
            return $severityOrder[$a['severity']] - $severityOrder[$b['severity']];
        });
        
        return array_slice($alerts, 0, $limit);
        
    } catch (Exception $e) {
        error_log("Get critical pharmacy alerts error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if medicine needs reorder based on stock level
 */
function needsReorder($medicineId) {
    try {
        $connection = getDBConnection();
        
        $query = "SELECT quantity, min_quantity FROM medicines WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $medicineId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $medicine = $result->fetch_assoc();
            $needsReorder = $medicine['quantity'] <= $medicine['min_quantity'];
        } else {
            $needsReorder = false;
        }
        
        $stmt->close();
        closeDBConnection($connection);
        
        return $needsReorder;
        
    } catch (Exception $e) {
        error_log("Check reorder error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get medicine expiry status
 */
function getMedicineExpiryStatus($expiryDate) {
    if (empty($expiryDate)) {
        return ['status' => 'no_expiry', 'class' => 'text-muted', 'message' => 'No expiry date'];
    }
    
    $today = new DateTime();
    $expiry = new DateTime($expiryDate);
    $interval = $today->diff($expiry);
    
    if ($expiry < $today) {
        return [
            'status' => 'expired',
            'class' => 'text-danger',
            'message' => 'Expired ' . $interval->days . ' days ago'
        ];
    } elseif ($interval->days <= 7) {
        return [
            'status' => 'critical',
            'class' => 'text-danger',
            'message' => 'Expires in ' . $interval->days . ' days'
        ];
    } elseif ($interval->days <= 30) {
        return [
            'status' => 'warning',
            'class' => 'text-warning',
            'message' => 'Expires in ' . $interval->days . ' days'
        ];
    } else {
        return [
            'status' => 'normal',
            'class' => 'text-success',
            'message' => 'Expires in ' . $interval->days . ' days'
        ];
    }
}

/**
 * Generate pharmacy alerts notification HTML
 */
function generatePharmacyAlertsNotification() {
    $alerts = getCriticalPharmacyAlerts(3);
    
    if (empty($alerts)) {
        return '';
    }
    
    $html = '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
    $html .= '<h6><i class="fas fa-exclamation-triangle"></i> Pharmacy Alerts</h6>';
    $html .= '<ul class="mb-2">';
    
    foreach ($alerts as $alert) {
        $html .= '<li><i class="' . $alert['icon'] . '"></i> ' . htmlspecialchars($alert['message']) . '</li>';
    }
    
    $html .= '</ul>';
    $html .= '<a href="modules/pharmacy/alerts.php" class="btn btn-sm btn-warning">View All Alerts</a>';
    $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    $html .= '</div>';
    
    return $html;
}