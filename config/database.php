<?php
/**
 * Database Configuration and Connection
 * Hospital Management System
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hospital_management');

// Create connection
function getDBConnection() {
    try {
        $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        // Check connection
        if ($connection->connect_error) {
            error_log("Database connection failed: " . $connection->connect_error);
            throw new Exception("Database connection failed. Please try again later.");
        }
        
        // Set charset to utf8
        if (!$connection->set_charset("utf8")) {
            error_log("Error setting charset: " . $connection->error);
            throw new Exception("Database configuration error.");
        }
        
        return $connection;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Database Error: " . $e->getMessage());
        
        // Display user-friendly error message
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Database Error: " . $e->getMessage());
        } else {
            die("Database connection error. Please contact system administrator.");
        }
    }
}

// Function to close database connection
function closeDBConnection($connection) {
    if ($connection) {
        $connection->close();
    }
}

// Test database connection
function testDBConnection() {
    try {
        $connection = getDBConnection();
        if ($connection) {
            closeDBConnection($connection);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}

// Execute query with error handling
function executeQuery($connection, $query, $params = []) {
    try {
        if (!empty($params)) {
            $stmt = $connection->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $connection->error);
            }
            
            if (!empty($params)) {
                $types = str_repeat('s', count($params)); // Assume all strings for simplicity
                $stmt->bind_param($types, ...$params);
            }
            
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            return $stmt->get_result();
        } else {
            $result = $connection->query($query);
            if (!$result) {
                throw new Exception("Query failed: " . $connection->error);
            }
            return $result;
        }
    } catch (Exception $e) {
        error_log("Query Error: " . $e->getMessage() . " | Query: " . $query);
        throw $e;
    }
}
?>