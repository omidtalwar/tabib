-- Run once to add exam submission tracking
CREATE TABLE IF NOT EXISTS exam_submissions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    teacher_course_id INT NOT NULL,
    exam_type         ENUM('midterm','final') NOT NULL,
    status            ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    submitted_at      TIMESTAMP NULL DEFAULT NULL,
    approved_at       TIMESTAMP NULL DEFAULT NULL,
    approved_by       INT NULL DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_submission (teacher_course_id, exam_type),
    FOREIGN KEY (teacher_course_id) REFERENCES teacher_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
