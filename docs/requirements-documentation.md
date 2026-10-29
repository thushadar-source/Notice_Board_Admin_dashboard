# Requirements Documentation

## System Overview

The Digital Notice Board Management System manages institute notices through a secure admin area and a user-facing notice board. The system stores users, notices, categories, attachments, and calendar notes in a MySQL database.

## User Roles

| Role | Description |
|---|---|
| Admin | Manages notices, categories, users, reports, and calendar notes. |
| User | Views published notices, filters/searches notices, views details, and downloads attachments. |

## Core Modules

### Authentication

- Admin login
- User login and registration
- Logout
- Role-based access checks

### Notice Management

- Add notices
- Edit notices
- Delete notices
- Publish/unpublish notices
- Pin notices
- Set publication and expiry dates
- Upload optional attachment

### Category Management

- Add categories
- Rename categories
- Hide/show categories
- Delete unused categories
- Filter notices by category

### User Management

- View registered users
- Filter users by role
- Search users
- Activate/deactivate accounts

### Public Notice Board

- View active notices
- Search notices
- Filter by category
- View notice details
- Download attachments

### Reports

- Notice counts by status
- Notice counts by category
- User account summary

## Data Requirements

Main database tables:

- `users`
- `categories`
- `notices`
- `attachments`
- `calendar_notes`

## Validation Requirements

- Required notice fields must be completed.
- Expiry date must not be before publication date.
- Uploaded files must match allowed extensions and MIME types.
- Login credentials must be verified before access.
- Admin actions must pass CSRF validation.

## Acceptance Criteria

- Admin can log in and access dashboard pages.
- Admin can add, edit, publish, unpublish, pin, and delete notices.
- User can view only active/published notices.
- Search and category filtering work on the notice board.
- Attachments can be uploaded and downloaded.
- Admin can activate/deactivate user accounts.
- Project repository includes README and required documentation.
