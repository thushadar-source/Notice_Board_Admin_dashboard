# Setup Guide - Digital Notice Board Management System

This guide explains how to run the project locally using XAMPP.

## Requirements

- XAMPP with Apache and MySQL
- PHP 8 or compatible PHP version
- Web browser
- phpMyAdmin

## Installation Steps

1. Copy the project folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import the SQL file located at `database/notice_board.sql`.
5. Confirm that a database named `notice_board` is created.
6. If your MySQL username/password is different, update the database settings in `config.php`.
7. Open the system in your browser:

   `http://localhost/Notice_Board_Admin_dashboard/`

## Admin Login

Open:

`http://localhost/Notice_Board_Admin_dashboard/admin-login.php`

Default admin accounts:

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `admin123` |
| Administrator | `ruwanb` | `admin123` |

## User Login

Open:

`http://localhost/Notice_Board_Admin_dashboard/login.php`

Default user account:

| Role | Username | Password |
|---|---|---|
| Student/User | `student` | `user123` |

## Main Files

| File/Folder | Purpose |
|---|---|
| `config.php` | Database connection, sessions, helpers, security functions |
| `index.php` | Main public notice board |
| `notice-details.php` | Full notice view |
| `login.php`, `register.php`, `logout.php` | User authentication |
| `admin-login.php`, `admin-logout.php` | Admin authentication |
| `admin-dashboard.php` | Admin dashboard overview |
| `admin-notices.php` | Notice list, filters, publish/unpublish, pin, delete |
| `admin-notice-form.php` | Add/edit notice form |
| `admin-categories.php` | Category management |
| `admin-users.php` | User status management |
| `admin-calendar.php` | Calendar notes |
| `admin-reports.php` | Reports |
| `database/notice_board.sql` | Database schema and sample data |
| `docs/` | Project documentation |
| `uploads/` | Runtime upload directory |

## Security Notes

- Passwords are stored using password hashing.
- Admin pages require admin login.
- Admin POST actions use CSRF validation.
- Database queries use prepared statements.
- Uploaded attachments are validated by file extension, MIME type, and size.

## GitHub Submission

Before submission, commit and push the final project to GitHub. Submit this repository URL:

`https://github.com/nusky437/notice_board_system`
