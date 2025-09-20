# Hospital Management System Design Document

## Overview

The Hospital Management System is a web-based application built using PHP and MySQL, designed with a simple MVC-like structure. The system provides a centralized platform for managing hospital operations through four main modules: Patient Management, Doctor Management, Billing & Payment, and Pharmacy Management.

## Architecture

### Technology Stack
- **Backend**: PHP 7.4+ with procedural and basic OOP approach
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, Bootstrap 4, JavaScript/jQuery
- **Web Server**: Apache with mod_rewrite enabled

### Directory Structure
```
hospital-management/
├── config/
│   ├── database.php
│   └── config.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   └── functions.php
├── modules/
│   ├── patients/
│   ├── doctors/
│   ├── billing/
│   └── pharmacy/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── sql/
│   └── hospital_db.sql
└── index.php
```

## Components and Interfaces

### 1. Database Layer

#### Core Tables:
- **patients**: Patient information and medical history
- **doctors**: Doctor profiles and specializations
- **admissions**: Patient admission/discharge records
- **bills**: Billing information and invoices
- **payments**: Payment transactions
- **medicines**: Pharmacy inventory
- **sales**: Medicine sales records
- **purchases**: Medicine purchase records

#### Key Relationships:
- Patients → Admissions (One-to-Many)
- Doctors → Admissions (One-to-Many)
- Patients → Bills (One-to-Many)
- Bills → Payments (One-to-Many)
- Medicines → Sales (One-to-Many)

### 2. Application Layer

#### Core Functions (includes/functions.php):
- Database connection management
- CRUD operations for each module
- Input validation and sanitization
- Date formatting and calculations
- Alert generation for pharmacy

#### Module Structure:
Each module follows a consistent pattern:
- `index.php` - List/dashboard view
- `add.php` - Add new record form
- `edit.php` - Edit existing record form
- `view.php` - Detailed view of record
- `delete.php` - Delete confirmation and action
- `process.php` - Form processing logic

### 3. User Interface Layer

#### Dashboard (index.php):
- Quick statistics cards for each module
- Recent activities feed
- Navigation menu to all modules
- Alert notifications for pharmacy

#### Common UI Components:
- Responsive Bootstrap-based layout
- Data tables with search and pagination
- Modal dialogs for confirmations
- Form validation with client-side feedback
- Success/error message notifications

## Data Models

### Patient Model
```sql
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    age INT,
    gender ENUM('Male', 'Female', 'Other'),
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    emergency_contact VARCHAR(15),
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Doctor Model
```sql
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    phone VARCHAR(15),
    email VARCHAR(100),
    schedule TEXT,
    consultation_fee DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Admission Model
```sql
CREATE TABLE admissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    doctor_id INT,
    admission_date DATE,
    discharge_date DATE NULL,
    reason TEXT,
    status ENUM('Admitted', 'Discharged') DEFAULT 'Admitted',
    room_charges DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);
```

### Billing Model
```sql
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    admission_id INT NULL,
    bill_date DATE,
    doctor_fee DECIMAL(10,2) DEFAULT 0,
    room_charges DECIMAL(10,2) DEFAULT 0,
    medicine_charges DECIMAL(10,2) DEFAULT 0,
    other_charges DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2),
    status ENUM('Pending', 'Paid', 'Partial') DEFAULT 'Pending',
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (admission_id) REFERENCES admissions(id)
);
```

### Medicine Model
```sql
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10,2),
    quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 10,
    expiry_date DATE,
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Error Handling

### Database Errors:
- Connection failures: Display user-friendly message and log technical details
- Query errors: Validate input and show specific field errors
- Constraint violations: Provide clear feedback about data conflicts

### Input Validation:
- Server-side validation for all form inputs
- Sanitization of user data before database operations
- Client-side validation for immediate feedback

### User Experience:
- Graceful degradation when JavaScript is disabled
- Clear error messages without exposing system details
- Redirect to appropriate pages after operations

## Testing Strategy

### Manual Testing Approach:
1. **Unit Testing**: Test individual functions for each CRUD operation
2. **Integration Testing**: Verify module interactions (e.g., billing with patient data)
3. **User Acceptance Testing**: Test complete workflows for each user story
4. **Data Integrity Testing**: Verify foreign key constraints and data consistency

### Test Scenarios:
- Patient registration and admission workflow
- Doctor assignment and billing generation
- Medicine sales and inventory updates
- Payment processing and bill status updates
- Search and filtering functionality
- Alert generation for low stock and expiry

### Browser Compatibility:
- Test on modern browsers (Chrome, Firefox, Safari, Edge)
- Ensure responsive design works on tablets and mobile devices
- Verify form submissions and AJAX functionality

## Security Considerations

### Basic Security Measures:
- Input sanitization using `mysqli_real_escape_string()`
- Prepared statements for database queries where possible
- Basic session management for user tracking
- CSRF protection for form submissions
- File upload restrictions (if implemented)

### Data Protection:
- Regular database backups
- Basic access logging
- Input length limitations
- SQL injection prevention through parameterized queries

## Performance Considerations

### Database Optimization:
- Proper indexing on frequently queried columns
- Pagination for large data sets
- Efficient JOIN queries for related data
- Regular database maintenance

### Application Performance:
- Minimize database queries per page
- Use appropriate caching headers
- Optimize images and static assets
- Implement basic query optimization

This design provides a solid foundation for a college-level hospital management system that balances functionality with simplicity, making it easy to understand and maintain while meeting all the specified requirements.