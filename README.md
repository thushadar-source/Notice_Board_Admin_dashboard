# Digital Notice Board Management System

A PHP and MySQL web application for publishing, managing, searching, and viewing institute notices. The system includes an admin dashboard for notice management and a public/student notice board for viewing published notices.

## Project Description

The Digital Notice Board Management System replaces manual or paper-based notice sharing with a centralized web platform. Administrators can create notices, organize them by category, attach files, pin important notices, publish or unpublish notices, and manage registered users. Students/users can log in, browse current notices, search notices, filter by category, view notice details, and download attachments.

## Problem Statement

Traditional notice boards are difficult to update, easy to miss, and inconvenient for students who are not physically present near the board. Institutes need a simple digital solution where notices can be published quickly, kept organized, and accessed by users from one place. The system should also support admin control, user access, notice expiry, attachment handling, and basic reporting.

## Project Objectives

- Provide a centralized digital notice board for institute announcements.
- Allow administrators to create, edit, publish, unpublish, pin, and delete notices.
- Organize notices using categories and allow category-based filtering.
- Allow users to search notices and view full notice details.
- Support secure login, logout, role-based access, and CSRF-protected admin actions.
- Support notice attachments with file type and size validation.
- Provide basic reporting and dashboard summaries for administrators.

## Main Features

- Admin login and logout
- Admin dashboard with notice/user summaries
- Add, edit, delete, publish, unpublish, and pin notices
- Category management
- Notice search and filtering
- Attachment upload and download
- Public/student notice board
- Notice details page
- User registration and login
- User activation/deactivation by admin
- Calendar notes
- Basic reports
- Responsive user interface

## Technologies and Tools Used

- PHP
- MySQL
- PDO database access
- HTML5
- CSS3
- JavaScript
- XAMPP
- phpMyAdmin
- Git and GitHub

## Project Scope

The project focuses on notice board management for an educational institute. It includes administrator functions, public/user notice viewing, user authentication, category management, file attachments, calendar notes, and reports. The system is designed for local deployment using XAMPP and MySQL.

Out of scope for this version:

- Email/SMS notifications
- Mobile application
- Advanced analytics
- Multi-institute deployment
- Real-time push notifications

## Student Details

- Student Name: TODO - add your full name
- Student ID/Registration Number: TODO - add your student ID
- Course/Module: TODO - add course or module name
- Institute: Meridian Institute
- Project Title: Digital Notice Board Management System

## Setup Instructions

1. Copy the project folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL using XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/notice_board.sql`.
5. If needed, update database credentials in `config.php`.
6. Open `http://localhost/Notice_Board_Admin_dashboard/admin-login.php`.

## Default Accounts

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `admin123` |
| Administrator | `ruwanb` | `admin123` |
| Student/User | `student` | `user123` |

Change default passwords before using the system in a real environment.

## Repository Structure

| Path | Purpose |
|---|---|
| `admin-*.php` | Admin dashboard pages and management modules |
| `index.php` | Main notice board page |
| `login.php`, `register.php`, `logout.php` | User authentication |
| `notice-details.php` | Full notice view |
| `config.php` | Database connection, sessions, helpers, security functions |
| `includes/` | Shared admin layout files |
| `database/` | Database schema and sample data |
| `images/` | Project images and logo |
| `uploads/` | Runtime upload directory |
| `docs/` | Project documentation |
| `style.css` | Shared styling |
| `script.js`, `admin-ui.js` | Public and admin JavaScript |

## Documentation

Project documentation is available in the `docs/` folder:

- `docs/project-proposal.md`
- `docs/requirements-baseline.md`
- `docs/requirements-documentation.md`
- `docs/project-plan.md`
- `docs/submission-checklist.md`

## Security Notes

- Admin pages are protected with role-based access control.
- Database queries use prepared statements.
- Output is escaped before display.
- Admin actions use CSRF validation.
- Uploaded files are validated by extension, MIME type, and size.
- Passwords are stored using PHP password hashing.

## GitHub Submission URL

Submit this repository URL in Google Classroom after the final project has been committed and pushed:

`https://github.com/nusky437/notice_board_system`
