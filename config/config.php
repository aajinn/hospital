<?php
/**
 * Application Configuration
 * Hospital Management System
 */

// Application settings
define('APP_NAME', 'Hospital Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/hospital');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Debug mode (set to false in production)
define('DEBUG_MODE', true);

// Error reporting (set to 0 in production)
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'database.php';

// Application constants
define('RECORDS_PER_PAGE', 10);
define('MIN_STOCK_ALERT', 10);
define('EXPIRY_ALERT_DAYS', 30);

// Patient ID prefix
define('PATIENT_ID_PREFIX', 'PAT');

// Bill status constants
define('BILL_STATUS_PENDING', 'Pending');
define('BILL_STATUS_PAID', 'Paid');
define('BILL_STATUS_PARTIAL', 'Partial');

// Admission status constants
define('ADMISSION_STATUS_ADMITTED', 'Admitted');
define('ADMISSION_STATUS_DISCHARGED', 'Discharged');
?>