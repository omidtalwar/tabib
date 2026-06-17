-- Exam question papers written by teachers (approval + print workflow)
CREATE TABLE IF NOT EXISTS exam_papers (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id        INT NOT NULL,
    teacher_course_id INT DEFAULT NULL,          -- links to the subject/class
    subject_name      VARCHAR(200) NOT NULL,
    department        VARCHAR(100),
    semester          VARCHAR(50),
    shift             VARCHAR(50),
    exam_type         ENUM('midterm','final') NOT NULL DEFAULT 'final',
    language          ENUM('pashto','english') NOT NULL DEFAULT 'pashto',
    questions         LONGTEXT,                  -- JSON: {mcqs:[{q,a,b,c,d}], descriptive:[{q}]}
    status            ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    admin_note        TEXT DEFAULT NULL,
    -- Header data assigned by admin at print time
    exam_date         VARCHAR(50)  DEFAULT NULL,  -- e.g. 1405/03/15 (shamsi)
    exam_time         VARCHAR(50)  DEFAULT NULL,  -- e.g. 09:00
    year_label        VARCHAR(50)  DEFAULT NULL,  -- e.g. ۱۴۰۵ هـ ش
    term_label        VARCHAR(100) DEFAULT NULL,  -- e.g. بهاري سمستر
    submitted_at      TIMESTAMP NULL DEFAULT NULL,
    reviewed_at       TIMESTAMP NULL DEFAULT NULL,
    reviewed_by       INT DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id)        REFERENCES teachers(id)       ON DELETE CASCADE,
    FOREIGN KEY (teacher_course_id) REFERENCES teacher_courses(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by)       REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
