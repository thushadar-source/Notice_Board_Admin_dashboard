-- ============================================================
--  Digital Notice Board Management System
--  Database schema + seed data (Version 1.0)
--  Import this file in phpMyAdmin before using the admin panel.
-- ============================================================

DROP DATABASE IF EXISTS notice_board;
CREATE DATABASE notice_board CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE notice_board;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120)  NOT NULL,
  username      VARCHAR(60)   NOT NULL UNIQUE,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- categories
-- ---------------------------------------------------------
CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(40) NOT NULL UNIQUE,
  name        VARCHAR(60) NOT NULL,
  is_hidden   TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- notices
-- ---------------------------------------------------------
CREATE TABLE notices (
  notice_id    INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  description  TEXT NOT NULL,
  category_id  INT NOT NULL,
  status       ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
  publish_date DATE NOT NULL,
  expiry_date  DATE NOT NULL,
  is_pinned    TINYINT(1) NOT NULL DEFAULT 0,
  created_by   INT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_notice_category FOREIGN KEY (category_id) REFERENCES categories(category_id),
  CONSTRAINT fk_notice_user     FOREIGN KEY (created_by)  REFERENCES users(user_id),
  INDEX idx_status (status),
  INDEX idx_category (category_id),
  INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- attachments  (one file per notice in Version 1.0)
-- ---------------------------------------------------------
CREATE TABLE attachments (
  attachment_id INT AUTO_INCREMENT PRIMARY KEY,
  notice_id     INT NOT NULL,
  file_name     VARCHAR(200) NOT NULL,   -- original name shown to the user
  stored_name   VARCHAR(200) NOT NULL,   -- name on disk inside /uploads
  file_type     VARCHAR(80)  NOT NULL,
  file_size     INT          NOT NULL,   -- bytes
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attachment_notice FOREIGN KEY (notice_id) REFERENCES notices(notice_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- calendar notes
-- ---------------------------------------------------------
CREATE TABLE calendar_notes (
  calendar_note_id INT AUTO_INCREMENT PRIMARY KEY,
  note_date        DATE NOT NULL,
  title            VARCHAR(160) NOT NULL,
  note_text        TEXT NULL,
  is_visible       TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_calendar_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_calendar_note_date (note_date),
  INDEX idx_calendar_visible (is_visible)
) ENGINE=InnoDB;

-- ============================================================
--  Seed data
-- ============================================================

INSERT INTO categories (slug, name) VALUES
('academic',    'Academic'),
('examination', 'Examination'),
('events',      'Events'),
('scholarship', 'Scholarship'),
('general',     'General');

-- Default passwords:  admin / admin123        student / user123
INSERT INTO users (full_name, username, email, password_hash, role, status, created_at) VALUES
('admin',          'admin',   'nusky@meridian.edu',   '$2y$10$eyCooLXStkQ6eDZWZVTyDOMgxSrCUwOA1VRYw8pxa2f4Qt5CBZBGa', 'admin', 'active',   '2026-07-14 09:00:00'),
('Ruwan Bandara',  'ruwanb',  'ruwan@meridian.edu',   '$2y$10$eyCooLXStkQ6eDZWZVTyDOMgxSrCUwOA1VRYw8pxa2f4Qt5CBZBGa', 'admin', 'active',   '2026-07-14 09:10:00'),
('Fathima Rizna',  'student', 'rizna@meridian.edu',   '$2y$10$dnF5h1PoSrKY/A9mEMwwIeZptL2QJoDkjxT/k.jw75Uo8jj.i974G', 'user',  'active',   '2026-08-02 10:30:00'),
('Kavindu Perera', 'kavindu', 'kavindu@meridian.edu', '$2y$10$dnF5h1PoSrKY/A9mEMwwIeZptL2QJoDkjxT/k.jw75Uo8jj.i974G', 'user',  'active',   '2026-08-11 11:15:00'),
('Sara Jayakody',  'sara.j',  'sara@meridian.edu',    '$2y$10$dnF5h1PoSrKY/A9mEMwwIeZptL2QJoDkjxT/k.jw75Uo8jj.i974G', 'user',  'inactive', '2026-08-19 14:00:00'),
('Mohamed Azhar',  'azhar',   'azhar@meridian.edu',   '$2y$10$dnF5h1PoSrKY/A9mEMwwIeZptL2QJoDkjxT/k.jw75Uo8jj.i974G', 'user',  'active',   '2026-08-27 08:45:00'),
('Dinithi Silva',  'dinithi', 'dinithi@meridian.edu', '$2y$10$dnF5h1PoSrKY/A9mEMwwIeZptL2QJoDkjxT/k.jw75Uo8jj.i974G', 'user',  'active',   '2026-09-03 16:20:00');

INSERT INTO notices (title, description, category_id, status, publish_date, expiry_date, is_pinned, created_by) VALUES
('Semester 2 Examination Timetable Released',
 'The examination timetable for Semester 2 has been finalised and is now available for all registered students.\n\nStudents are advised to check their batch, subject codes, and seat numbers carefully. Any discrepancies must be reported to the examinations office within three working days.\n\nAdmission to the examination hall will not be permitted without a valid student ID card.',
 2, 'published', '2026-09-02', '2026-09-30', 1, 1),

('Guest Lecture: Careers in Software Engineering',
 'The Department of Computing is pleased to host a guest lecture on career paths in software engineering.\n\nThe session will cover industry expectations, interview preparation, and an open Q&A with the speaker.\n\nAll final-year students are encouraged to attend. Seating is limited to the main auditorium capacity.',
 3, 'published', '2026-09-05', '2026-09-20', 0, 1),

('Merit Scholarship Applications Now Open',
 'Applications for the Merit Scholarship Programme 2026/27 are now open to all eligible students.\n\nApplicants must have a minimum GPA as specified in the attached guideline document and submit supporting documents through the administration office.\n\nLate applications will not be considered under any circumstances.',
 4, 'published', '2026-09-01', '2026-10-15', 1, 1),

('Library Closed for Maintenance',
 'The main library will be closed for scheduled electrical and network maintenance.\n\nDigital resources, e-books, and the online catalogue will remain accessible throughout this period.\n\nWe apologise for any inconvenience and appreciate your understanding.',
 5, 'unpublished', '2026-09-06', '2026-09-09', 0, 2),

('Assignment Submission Deadline Extended',
 'Following requests from several students, the submission deadline for the Database Systems assignment has been extended by one week.\n\nThe new deadline and updated marking rubric are attached below.\n\nNo further extensions will be granted after this date.',
 1, 'draft', '2026-09-04', '2026-09-18', 0, 1),

('Internship Opportunities — September Intake',
 'A limited number of internship placements were available for the September intake in partnership with local technology companies.\n\nThis notice is past its expiry date, so it is no longer visible to general users but remains listed for administrators.',
 5, 'published', '2026-08-20', '2026-09-05', 0, 2);

INSERT INTO calendar_notes (note_date, title, note_text, is_visible, created_by) VALUES
('2026-09-30', 'Semester 2 exam timetable', 'Check your subject codes and seat numbers before the exam week.', 1, 1),
('2026-10-15', 'Scholarship applications close', 'Submit all supporting documents before the deadline.', 1, 1);
