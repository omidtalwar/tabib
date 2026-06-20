-- Run once to add project submission support

-- Add due_date to materials (closing date set by teacher)
ALTER TABLE materials ADD COLUMN IF NOT EXISTS due_date DATE NULL AFTER description;

-- Student project submissions
CREATE TABLE IF NOT EXISTS student_projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    subject     VARCHAR(150),
    file_path   VARCHAR(255),
    status      ENUM('submitted','reviewed','returned') NOT NULL DEFAULT 'submitted',
    admin_note  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
