# MMTVTC Management System

A comprehensive Learning Management System (LMS) for the **Mandaluyong Manpower and Technical Vocational Training Center (MMTVTC)**. This system manages student enrollment, course administration, grading, assessments, attendance tracking, and graduate employment tracking.

## 📋 Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [User Roles](#user-roles)
- [Security Features](#security-features)
- [Project Structure](#project-structure)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

### Core Functionality
- **Multi-role Authentication System** - Secure login with OTP (One-Time Password) verification
- **Student Management** - Complete student enrollment, profile management, and record tracking
- **Course Management** - Manage multiple technical vocational courses with modules
- **Grade Management** - Track and manage student grades across different modules
- **Assessment System** - Create and manage quizzes, exams, and assessments
- **Attendance Tracking** - Monitor student attendance records
- **Announcement System** - Broadcast important announcements to users
- **Job Matching** - Match graduates with employment opportunities
- **Graduate Tracking** - Track graduate employment status and career progression
- **Analytics Dashboard** - Visualize data with charts and statistics

### Available Courses
- Automotive Servicing
- Basic Computer Literacy
- Beauty Care (Nail Care)
- Bread and Pastry Production
- Computer Systems Servicing
- Dressmaking
- Electrical Installation and Maintenance
- Electronic Products and Assembly Servicing
- Events Management Services
- Food and Beverage Services
- Food Processing
- Hairdressing
- Housekeeping
- And more...

### Additional Features
- CSV Import/Export functionality
- Email notifications via PHPMailer
- Responsive design for mobile and desktop
- Real-time data visualization
- Batch assignment for assessments
- Password reset functionality
- Remember me functionality
- Cross-tab logout detection

## 🛠 Technology Stack

- **Backend**: PHP 7.2+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla JS)
- **Libraries**:
  - PHPMailer (for email functionality)
  - Chart.js (for data visualization)
- **Security**: 
  - Cloudflare Turnstile (CAPTCHA)
  - CSRF Protection
  - Content Security Policy (CSP)
  - Session management
  - Abuse protection system

## 📦 System Requirements

- PHP 7.2 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer (for dependency management)
- SMTP server access (for email functionality)
- Cloudflare Turnstile account (for CAPTCHA)

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd backup-for-testing
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up environment variables**
   - Copy `.env.example` to `.env` (if available) or create a `.env` file
   - Configure database and SMTP settings (see Configuration section)

4. **Set up the database**
   - Import the SQL file: `u485501277_mmtvtc.sql`
   - Or run the database migrations manually

5. **Configure web server**
   - Point your web server document root to the project directory
   - Ensure mod_rewrite is enabled (if using Apache)

6. **Set permissions**
   ```bash
   chmod -R 755 logs/
   chmod -R 755 uploads_quarantine/
   ```

## ⚙️ Configuration

Create a `.env` file in the project root with the following variables:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=your_db_username
DB_PASS=your_db_password
DB_NAME=your_database_name

# SMTP Configuration
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=your_smtp_username
SMTP_PASS=your_smtp_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=MMTVTC
SMTP_SECURE=tls

# Application Environment
APP_ENV=production
```

### Cloudflare Turnstile Configuration

Update the Turnstile keys in `login_users_mmtvtc.php`:
```php
define('TURNSTILE_SITE_KEY', 'your_site_key');
define('TURNSTILE_SECRET_KEY', 'your_secret_key');
```

## 🗄 Database Setup

1. **Create the database**
   ```sql
   CREATE DATABASE u485501277_mmtvtc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import the SQL file**
   ```bash
   mysql -u username -p u485501277_mmtvtc < u485501277_mmtvtc.sql
   ```

3. **Verify tables**
   The database includes tables for:
   - Users and authentication
   - Courses and modules
   - Students and enrollments
   - Instructors
   - Grades and assessments
   - Attendance records
   - Announcements
   - Graduates and employment tracking
   - Security and abuse tracking
   - And more...

## 👥 User Roles

The system supports three main user roles:

### 1. Admin (Role: 2)
- Full system access
- Manage trainees and instructors
- Course enrollment settings
- Graduate management
- Job matching administration
- Career analytics
- Announcement management
- System configuration

### 2. Instructor (Role: 1)
- View assigned courses
- Manage student grades
- Create and manage assessments (quizzes, exams)
- Track student attendance
- View student progress
- Publish assessments

### 3. Student (Role: 0)
- View enrolled courses
- Access course modules
- View grades and progress
- Take assessments
- View announcements
- Access job recommendations
- View career tracking records

## 🔒 Security Features

- **OTP Authentication**: Email-based one-time password verification
- **CSRF Protection**: Cross-site request forgery protection
- **Content Security Policy (CSP)**: Prevents XSS attacks
- **Session Management**: Secure session handling with timeout
- **Abuse Protection**: Rate limiting and account lockout
- **Password Security**: Secure password hashing and history tracking
- **SQL Injection Prevention**: Prepared statements with PDO
- **XSS Protection**: Input sanitization and output escaping
- **Security Logging**: Comprehensive security event logging
- **Cloudflare Turnstile**: Bot protection
- **Secure Headers**: X-Frame-Options, X-Content-Type-Options, etc.

## 📁 Project Structure

```
.
├── apis/                    # API endpoints
│   ├── attendance_handler.php
│   ├── course_admin.php
│   ├── grade_handler.php
│   ├── quiz_handler.php
│   └── ...
├── assets/                  # Static assets (logos, images)
├── CSS/                     # Stylesheets
│   ├── admin.css
│   ├── instructor.css
│   └── student.css
├── data/                    # CSV data files
├── images/                  # Image assets
├── js/                      # JavaScript files
│   ├── admin.js
│   ├── instructor.js
│   └── Student.js
├── logs/                    # Application logs
├── otpforusers/             # OTP functionality
├── PHPMailer/               # Email library
├── security/                # Security modules
│   ├── abuse_protection.php
│   ├── csp.php
│   ├── csrf.php
│   ├── db_connect.php
│   ├── session_config.php
│   └── ...
├── dashboard/               # Role-specific dashboards
│   ├── admin_dashboard.php
│   ├── instructors_dashboard.php
│   └── student_dashboard.php
├── auth/                    # Authentication + password flows
│   ├── login_users_mmtvtc.php
│   ├── logout.php
│   ├── forgot_password.php
│   ├── create_pass.php
│   ├── reset_pass.php
│   └── resend_reset.php
├── tools/
│   └── maintenance/         # Admin/maintenance utilities
│       ├── grade_management.php
│       ├── password_history_manager.php
│       ├── sync_grades.php
│       ├── fix_role_field.php
│       ├── assign_test_batches.php
│       ├── check_batch_distribution.php
│       └── distribute_5_students.php
├── index.php                # Landing page
├── u485501277_mmtvtc.sql    # Database schema
└── README.md                # This file
```

## 📖 Usage

### Accessing the System

1. Navigate to your web server URL (e.g., `http://localhost/`)
2. You'll be redirected to the login page if not authenticated
3. Enter your credentials (email and password)
4. Complete OTP verification if required
5. You'll be redirected to your role-specific dashboard

### For Administrators

- Access the admin dashboard to manage all aspects of the system
- Add new trainees and instructors
- Configure course enrollment settings
- Manage graduates and employment tracking
- View analytics and reports

### For Instructors

- Access the instructor dashboard
- View your assigned course(s)
- Manage student grades and assessments
- Track attendance
- Publish assessments for students

### For Students

- Access the student dashboard
- View enrolled courses and modules
- Check grades and progress
- Take assessments
- View announcements
- Access job recommendations

## 🔌 API Endpoints

The system includes various API endpoints in the `apis/` directory:

- `/apis/attendance_handler.php` - Attendance management
- `/apis/course_admin.php` - Course administration
- `/apis/grade_handler.php` - Grade management
- `/apis/quiz_handler.php` - Quiz/assessment handling
- `/apis/exam_handler.php` - Exam management
- `/apis/announcement_handler.php` - Announcement CRUD
- `/apis/student_profile.php` - Student profile data
- `/apis/csv_import_handler.php` - CSV import functionality
- And more...

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Notes

- Ensure all sensitive data (`.env` file) is not committed to version control
- Log files are automatically generated in the `logs/` directory
- The system uses prepared statements to prevent SQL injection
- All user inputs are sanitized before processing
- Regular security audits are recommended

## ⚠️ Important Security Notes

- **Never commit** the `.env` file containing sensitive credentials
- Keep PHP and database software updated
- Regularly review security logs in `logs/security.log`
- Use strong passwords for database and SMTP accounts
- Enable HTTPS in production environments
- Regularly backup the database

## 📄 License

This project is part of a thesis project. Please refer to the LICENSE file for more information.

## 👤 Author

Developed as part of a thesis project for MMTVTC.

## 🙏 Acknowledgments

- PHPMailer for email functionality
- Cloudflare for Turnstile CAPTCHA service
- All contributors and testers

---

**Note**: This is a thesis project. For production use, ensure all security measures are properly configured and tested.

