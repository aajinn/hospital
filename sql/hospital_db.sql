-- Hospital Management System Database Schema
-- MySQL Database Creation Script

-- Create database
CREATE DATABASE IF NOT EXISTS hospital_management;
USE hospital_management;

-- Set charset
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for patients
-- --------------------------------------------------------

CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(20) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `age` int(3) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_name` (`name`),
  KEY `idx_patient_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Table structure for doctors
-- --------------------------------------------------------

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doctor_name` (`name`),
  KEY `idx_doctor_specialization` (`specialization`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for admissions
-- --------------------------------------------------------

CREATE TABLE `admissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `admission_date` date NOT NULL,
  `discharge_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Admitted','Discharged') DEFAULT 'Admitted',
  `room_charges` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_admission_patient` (`patient_id`),
  KEY `fk_admission_doctor` (`doctor_id`),
  KEY `idx_admission_date` (`admission_date`),
  KEY `idx_admission_status` (`status`),
  CONSTRAINT `fk_admission_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admission_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for bills
-- --------------------------------------------------------

CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `admission_id` int(11) DEFAULT NULL,
  `bill_date` date NOT NULL,
  `doctor_fee` decimal(10,2) DEFAULT 0.00,
  `room_charges` decimal(10,2) DEFAULT 0.00,
  `medicine_charges` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Partial') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bill_patient` (`patient_id`),
  KEY `fk_bill_admission` (`admission_id`),
  KEY `idx_bill_date` (`bill_date`),
  KEY `idx_bill_status` (`status`),
  CONSTRAINT `fk_bill_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for payments
-- --------------------------------------------------------

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Card','UPI','Bank Transfer','Cheque') DEFAULT 'Cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_payment_bill` (`bill_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for medicines
-- --------------------------------------------------------

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `min_quantity` int(11) DEFAULT 10,
  `expiry_date` date DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_medicine_name` (`name`),
  KEY `idx_medicine_category` (`category`),
  KEY `idx_medicine_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for sales
-- --------------------------------------------------------

CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` date NOT NULL,
  `prescription_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sale_medicine` (`medicine_id`),
  KEY `fk_sale_patient` (`patient_id`),
  KEY `idx_sale_date` (`sale_date`),
  CONSTRAINT `fk_sale_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for purchases
-- --------------------------------------------------------

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` int(11) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_purchase_medicine` (`medicine_id`),
  KEY `idx_purchase_date` (`purchase_date`),
  CONSTRAINT `fk_purchase_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Create indexes for better performance
-- --------------------------------------------------------

-- Additional indexes for common queries
CREATE INDEX idx_patient_created ON patients(created_at);
CREATE INDEX idx_doctor_created ON doctors(created_at);
CREATE INDEX idx_admission_patient_doctor ON admissions(patient_id, doctor_id);
CREATE INDEX idx_bill_patient_status ON bills(patient_id, status);
CREATE INDEX idx_medicine_quantity ON medicines(quantity);
CREATE INDEX idx_medicine_low_stock ON medicines(quantity, min_quantity);

-- --------------------------------------------------------
-- Create triggers for automatic calculations
-- --------------------------------------------------------

DELIMITER $$

-- Trigger to update bill total when bill details change
CREATE TRIGGER tr_bill_calculate_total 
BEFORE UPDATE ON bills
FOR EACH ROW
BEGIN
    SET NEW.total_amount = NEW.doctor_fee + NEW.room_charges + NEW.medicine_charges + NEW.other_charges;
END$$

-- Trigger to update bill total on insert
CREATE TRIGGER tr_bill_calculate_total_insert 
BEFORE INSERT ON bills
FOR EACH ROW
BEGIN
    SET NEW.total_amount = NEW.doctor_fee + NEW.room_charges + NEW.medicine_charges + NEW.other_charges;
END$$

-- Trigger to update medicine quantity after sale
CREATE TRIGGER tr_update_medicine_stock_after_sale
AFTER INSERT ON sales
FOR EACH ROW
BEGIN
    UPDATE medicines 
    SET quantity = quantity - NEW.quantity 
    WHERE id = NEW.medicine_id;
END$$

-- Trigger to update medicine quantity after purchase
CREATE TRIGGER tr_update_medicine_stock_after_purchase
AFTER INSERT ON purchases
FOR EACH ROW
BEGIN
    UPDATE medicines 
    SET quantity = quantity + NEW.quantity 
    WHERE id = NEW.medicine_id;
END$$

-- Trigger to update bill status based on payments
CREATE TRIGGER tr_update_bill_status_after_payment
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE total_bill DECIMAL(10,2);
    DECLARE total_paid DECIMAL(10,2);
    
    SELECT total_amount INTO total_bill FROM bills WHERE id = NEW.bill_id;
    SELECT SUM(amount) INTO total_paid FROM payments WHERE bill_id = NEW.bill_id;
    
    IF total_paid >= total_bill THEN
        UPDATE bills SET status = 'Paid' WHERE id = NEW.bill_id;
    ELSEIF total_paid > 0 THEN
        UPDATE bills SET status = 'Partial' WHERE id = NEW.bill_id;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------
-- Create views for common queries
-- --------------------------------------------------------

-- View for patient admission details
CREATE VIEW v_patient_admissions AS
SELECT 
    a.id as admission_id,
    p.patient_id,
    p.name as patient_name,
    p.phone as patient_phone,
    d.name as doctor_name,
    d.specialization,
    a.admission_date,
    a.discharge_date,
    a.reason,
    a.status,
    a.room_charges
FROM admissions a
JOIN patients p ON a.patient_id = p.id
JOIN doctors d ON a.doctor_id = d.id;

-- View for bill details with patient info
CREATE VIEW v_bill_details AS
SELECT 
    b.id as bill_id,
    p.patient_id,
    p.name as patient_name,
    b.bill_date,
    b.doctor_fee,
    b.room_charges,
    b.medicine_charges,
    b.other_charges,
    b.total_amount,
    b.status,
    COALESCE(SUM(pay.amount), 0) as paid_amount,
    (b.total_amount - COALESCE(SUM(pay.amount), 0)) as pending_amount
FROM bills b
JOIN patients p ON b.patient_id = p.id
LEFT JOIN payments pay ON b.id = pay.bill_id
GROUP BY b.id, p.patient_id, p.name, b.bill_date, b.doctor_fee, b.room_charges, b.medicine_charges, b.other_charges, b.total_amount, b.status;

-- View for medicine inventory with alerts
CREATE VIEW v_medicine_inventory AS
SELECT 
    m.*,
    CASE 
        WHEN m.quantity <= m.min_quantity THEN 'Low Stock'
        WHEN m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND m.expiry_date >= CURDATE() THEN 'Near Expiry'
        WHEN m.expiry_date < CURDATE() THEN 'Expired'
        ELSE 'Normal'
    END as alert_status
FROM medicines m;

-- --------------------------------------------------------
-- Table structure for doctor schedules
-- --------------------------------------------------------

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(11) DEFAULT 30 COMMENT 'Duration in minutes',
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_schedule_doctor` (`doctor_id`),
  KEY `idx_schedule_day` (`day_of_week`),
  CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for appointments
-- --------------------------------------------------------

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_appointment_doctor` (`doctor_id`),
  KEY `fk_appointment_patient` (`patient_id`),
  KEY `idx_appointment_date` (`appointment_date`),
  KEY `idx_appointment_status` (`status`),
  CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Set foreign key checks back to 1
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Insert default data (optional)
-- --------------------------------------------------------

-- Insert sample specializations for reference
-- This will be handled in later tasks

COMMIT;