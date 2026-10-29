# Requirements Baseline

## Functional Requirements

| ID | Requirement |
|---|---|
| FR-01 | Admin users must be able to log in. |
| FR-02 | Public users must be able to register and log in. |
| FR-03 | The system must support role-based access. |
| FR-04 | The system must validate login credentials. |
| FR-05 | The system must show an error for invalid login attempts. |
| FR-06 | Users must be able to log out. |
| FR-07 | Admins must be able to add notices. |
| FR-08 | Admins must be able to edit notices. |
| FR-09 | Admins must be able to delete notices. |
| FR-10 | Admins must be able to view notice lists. |
| FR-11 | Users must be able to view published notices. |
| FR-12 | Admins must be able to publish and unpublish notices. |
| FR-13 | Notices must support publication and expiry dates. |
| FR-14 | Admins must be able to pin important notices. |
| FR-15 | Notices must be assigned to categories. |
| FR-16 | Users/admins must be able to filter notices by category. |
| FR-17 | Admins must be able to upload notice attachments. |
| FR-18 | Users must be able to download valid notice attachments. |
| FR-19 | Users/admins must be able to search notices. |
| FR-20 | Admins must be able to view registered users. |
| FR-21 | Admins must be able to activate/deactivate users. |
| FR-22 | Admins must be able to view basic reports. |

## Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-01 | Passwords must be stored securely using hashing. |
| NFR-02 | Admin pages must require authentication. |
| NFR-03 | Admin-only actions must require admin authorization. |
| NFR-04 | File uploads must validate file type and size. |
| NFR-05 | The interface must be responsive on desktop and mobile devices. |
| NFR-06 | The repository must be clean and organized for assessment submission. |

## Business Rules

| ID | Rule |
|---|---|
| BR-01 | Only admins can access the admin dashboard. |
| BR-02 | Admin actions must be protected by CSRF tokens. |
| BR-03 | Notice title, category, description, publication date, and expiry date are required. |
| BR-04 | Expired or unpublished notices must not appear as active public notices. |
| BR-05 | Only allowed attachment types may be uploaded. |
| BR-06 | Uploaded files must remain within the configured size limit. |
| BR-07 | A notice cannot be saved without required fields. |
| BR-08 | Plain text passwords must not be stored. |
| BR-09 | Expiry date cannot be earlier than publication date. |
