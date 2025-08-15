# Learning Management System (LMS)

A comprehensive Learning Management System built with PHP, MySQL, HTML, CSS, and JavaScript. This system provides a complete platform for online education with three distinct user roles: Students, Lecturers, and Administrators.

## Features

### For Students:
- **Course Discovery**: Browse and search available courses
- **Course Enrollment**: Enroll in courses of interest
- **Learning Progress**: Track progress through course materials
- **Interactive Learning**: Access lessons, complete assignments, and take exams
- **Grade Tracking**: View grades and performance analytics
- **Profile Management**: Update personal information and preferences

### For Lecturers:
- **Course Creation**: Create and manage courses
- **Content Management**: Add lessons, assignments, and exams
- **Student Management**: Monitor student enrollment and progress
- **Assessment Tools**: Create questions and manage grading
- **Performance Analytics**: View student performance and provide feedback

### For Administrators:
- **System Management**: Oversee entire LMS platform
- **User Management**: Manage students, lecturers, and their accounts
- **Course Oversight**: Monitor all courses across the platform
- **Analytics Dashboard**: View system-wide statistics and reports

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Development Environment**: XAMPP
- **Icons**: Font Awesome 6
- **Responsive Design**: Mobile-first approach

## Installation

### Prerequisites
- XAMPP (Apache, MySQL, PHP)
- Web browser
- Text editor (optional)

### Setup Instructions

1. **Download and Install XAMPP**
   - Download from https://www.apachefriends.org/
   - Install and start Apache and MySQL services

2. **Clone/Download the Project**
   - Place the LMS folder in your XAMPP htdocs directory
   - Path should be: C:\xampp\htdocs\lms

3. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema from: database/schema.sql
   - This will create the database and insert sample data

4. **Configuration**
   - Check config/config.php for database settings
   - Default settings should work with standard XAMPP installation

5. **Access the System**
   - Open your browser and navigate to: http://localhost/lms
   - Use the demo accounts or register new users

## Demo Accounts

### Administrator
- **Username**: admin
- **Password**: admin123

### Lecturer
- **Username**: lecturer1
- **Password**: lecturer123

### Student
- **Username**: student1
- **Password**: student123

## System Structure

```
lms/
├── admin/                  # Admin dashboard and functions
├── lecturer/              # Lecturer dashboard and functions  
├── student/               # Student dashboard and functions
├── config/                # Configuration files
├── database/              # Database schema and setup
├── uploads/               # File upload directory (create if needed)
├── index.php             # Landing page
├── login.php             # Login system
├── register.php          # User registration
└── logout.php            # Logout functionality
```

## Key Features

### Course Management
- Create, edit, and delete courses
- Add multimedia content and materials
- Organize content with lessons, assignments, and exams
- Track student enrollment and progress

### Assessment System
- Multiple-choice questions (A, B, C, D format)
- Automatic grading and feedback
- Assignment retakes allowed
- Exam single-attempt restriction
- Detailed result analysis

### User Management
- Role-based access control
- Profile management
- User statistics and analytics
- Secure authentication system

### Responsive Design
- Mobile-friendly interface
- Bootstrap-based responsive layout
- Cross-browser compatibility
- Modern UI/UX design

## Usage Guide

### For Students:
1. Register or login to your account
2. Browse available courses from the dashboard
3. Click "View Details" to learn about a course
4. Enroll in courses that interest you
5. Access course materials from "My Courses"
6. Complete lessons, assignments, and exams
7. Track your progress and grades

### For Lecturers:
1. Login with lecturer credentials
2. Create new courses from your dashboard
3. Add course materials (lessons, assignments, exams)
4. Monitor student enrollment and progress
5. Grade assignments and provide feedback
6. View detailed analytics on student performance

### For Administrators:
1. Login with admin credentials
2. Monitor all courses and users
3. Manage user accounts and permissions
4. View system-wide statistics
5. Moderate content and user activities

## Security Features

- Password hashing with MD5
- SQL injection prevention with prepared statements
- Session management
- Role-based access control
- Input validation and sanitization

## Troubleshooting

### Common Issues:

1. **Database Connection Error**
   - Check MySQL service is running in XAMPP
   - Verify database credentials in config.php
   - Ensure database exists and is populated

2. **Page Not Found (404)**
   - Check file paths are correct
   - Ensure XAMPP Apache is running
   - Verify URL structure

3. **Login Issues**
   - Check if session is started
   - Verify user credentials in database
   - Clear browser cache and cookies

## License

This project is open-source and available for educational purposes.

---

**Note**: This system is designed for educational purposes. For production deployment, additional security measures should be implemented.
