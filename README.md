# Hospital Management System

A comprehensive web-based hospital management system built with PHP and MySQL to streamline hospital operations.

## Features

- **Patient Management**: Register patients, manage admissions and discharges
- **Doctor Management**: Manage doctor profiles, specializations, and schedules
- **Billing & Payment**: Generate bills, track payments, and manage financial records
- **Pharmacy Management**: Manage medicine inventory, sales, and purchase records
- **Dashboard**: Overview of hospital statistics and quick actions
- **Reporting**: Generate various reports for different modules

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, Bootstrap 4, JavaScript/jQuery
- **Web Server**: Apache with mod_rewrite

## Project Structure

```
hospital-management/
├── config/                 # Configuration files
│   ├── database.php        # Database connection
│   └── config.php          # Application configuration
├── includes/               # Common includes
│   ├── header.php          # Common header
│   ├── footer.php          # Common footer
│   ├── sidebar.php         # Navigation sidebar
│   └── functions.php       # Utility functions
├── modules/                # Application modules
│   ├── patients/           # Patient management
│   ├── doctors/            # Doctor management
│   ├── billing/            # Billing and payments
│   └── pharmacy/           # Pharmacy management
├── assets/                 # Static assets
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   └── images/             # Images
├── sql/                    # Database scripts
│   └── hospital_db.sql     # Database schema
├── index.php               # Main dashboard
└── README.md               # This file
```

## Installation

1. **Prerequisites**
   - PHP 7.4 or higher
   - MySQL 8.0 or higher
   - Apache web server
   - Web browser

2. **Database Setup**
   - Create a MySQL database named `hospital_management`
   - Import the database schema from `sql/hospital_db.sql`
   - Update database credentials in `config/database.php`

3. **Configuration**
   - Update `APP_URL` in `config/config.php` to match your local setup
   - Ensure proper file permissions for web server access

4. **Web Server**
   - Place the project files in your web server document root
   - Ensure mod_rewrite is enabled for Apache
   - Access the application through your web browser

## Database Schema

The system uses the following main tables:

- **patients**: Patient information and medical history
- **doctors**: Doctor profiles and specializations
- **admissions**: Patient admission/discharge records
- **bills**: Billing information and invoices
- **payments**: Payment transactions
- **medicines**: Pharmacy inventory
- **sales**: Medicine sales records
- **purchases**: Medicine purchase records

## Key Features

### Patient Management
- Patient registration with unique ID generation
- Admission and discharge tracking
- Medical history management
- Search and filtering capabilities

### Doctor Management
- Doctor profile management
- Specialization tracking
- Schedule management
- Patient assignment system

### Billing System
- Automated bill generation
- Multiple payment methods support
- Payment tracking and history
- Pending dues management

### Pharmacy Management
- Medicine inventory management
- Sales and purchase tracking
- Low stock alerts
- Expiry date monitoring

## Security Features

- Input sanitization and validation
- SQL injection prevention
- Basic session management
- CSRF protection for forms

## Browser Compatibility

- Chrome (recommended)
- Firefox
- Safari
- Edge
- Responsive design for mobile devices

## Development Status

This project is currently in development. The basic structure and database schema have been implemented. Individual modules will be developed in subsequent phases.

## License

This project is developed for educational purposes.

## Support

For support and questions, please refer to the project documentation or contact the development team.