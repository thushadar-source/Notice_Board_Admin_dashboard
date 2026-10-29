# Project Plan

## Project Name

Digital Notice Board Management System

## Development Approach

The project was developed as a small web application using PHP, MySQL, HTML, CSS, and JavaScript. Development was divided into database setup, authentication, admin modules, public notice board, styling, validation, and documentation.

## Major Tasks

| Phase | Tasks |
|---|---|
| Planning | Identify problem, objectives, scope, users, and requirements. |
| Database Design | Create tables for users, categories, notices, attachments, and calendar notes. |
| Authentication | Implement login, registration, logout, sessions, and role checks. |
| Admin Dashboard | Build dashboard summaries and navigation. |
| Notice Management | Implement create, update, delete, publish/unpublish, pin, search, and filters. |
| Category Management | Implement add, rename, hide/show, and delete categories. |
| User Management | Implement user listing, role filtering, search, and account status toggle. |
| Public Board | Implement notice listing, category filter, search, details, and downloads. |
| UI/Responsive Design | Style desktop and mobile layouts. |
| Testing | Check form validation, database actions, access control, and responsive behavior. |
| Documentation | Prepare README, setup instructions, requirements, plan, and submission checklist. |

## Testing Summary

- PHP syntax checks were run on changed PHP files during development.
- Admin notice form validation was checked.
- Admin notice list filters and category dropdown behavior were adjusted.
- User activation/deactivation action was converted to a toggle-style control.
- Public category sidebar was updated so the category list scrolls while the note remains fixed.

## Risks and Mitigation

| Risk | Mitigation |
|---|---|
| Database connection failure | Include setup instructions and configurable database credentials. |
| Unauthorized admin access | Use session checks and admin role validation. |
| Invalid file uploads | Validate extension, MIME type, and size. |
| Missing project documents | Include all assessment documents in the `docs/` folder. |
| Uploading runtime files | Use `.gitignore` to exclude sessions and uploaded runtime files. |

## Final Deliverable

A complete GitHub repository containing source code, database script, README, and project documentation.
