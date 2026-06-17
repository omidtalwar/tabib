-- MMI Collection — Migration: Departments Management + Materials Enhancements
-- Run once via phpMyAdmin or MySQL CLI before using these features.

-- 1. Departments management table
CREATE TABLE IF NOT EXISTS departments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name_en       VARCHAR(100) NOT NULL UNIQUE,
    name_ps       VARCHAR(100) DEFAULT NULL,
    max_semesters TINYINT      DEFAULT 4,
    sort_order    INT          DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO departments (name_en, name_ps, max_semesters, sort_order) VALUES
    ('Nursing',    'نرسنګ',     6, 1),
    ('Pharmacy',   'درملپوهنه',  4, 2),
    ('Protiz',     'پروتیز',     4, 3),
    ('Technology', 'ټیکنالوجي', 4, 4);

-- 2. Add department list to teachers (comma-separated, e.g. "Nursing,Pharmacy")
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS department TEXT DEFAULT NULL;

-- 3. Add father phone to students
ALTER TABLE students ADD COLUMN IF NOT EXISTS father_phone VARCHAR(20) DEFAULT NULL;

-- 4. Link materials to a specific teacher course (NULL = general / not course-specific)
ALTER TABLE materials ADD COLUMN IF NOT EXISTS course_id INT DEFAULT NULL;
