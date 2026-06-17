-- Exam schedules (separate from score submissions)
CREATE TABLE IF NOT EXISTS exam_schedules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    exam_type       ENUM('midterm','final') NOT NULL DEFAULT 'midterm',
    subject_name    VARCHAR(200) NOT NULL,
    department      VARCHAR(100),
    semester        VARCHAR(50),
    shift           VARCHAR(50),
    exam_date       DATE NOT NULL,
    start_time      TIME,
    end_time        TIME,
    room            VARCHAR(100),
    invigilator_id  INT DEFAULT NULL,
    invigilator2_id INT DEFAULT NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invigilator_id)  REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (invigilator2_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Unique teacher ID (like student roll_no)
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS teacher_no VARCHAR(20) UNIQUE DEFAULT NULL;
