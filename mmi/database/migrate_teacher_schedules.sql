-- Teacher-submitted weekly schedule (approval workflow)
CREATE TABLE IF NOT EXISTS teacher_schedules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT         NOT NULL,
    department   VARCHAR(100) NOT NULL,
    semester     VARCHAR(50)  NOT NULL,
    shift        VARCHAR(50)  NOT NULL,
    day_of_week  TINYINT     NOT NULL,  -- 1=Saturday ... 6=Thursday
    subject_name VARCHAR(200) NOT NULL,
    time_start   TIME         DEFAULT NULL,
    time_end     TIME         DEFAULT NULL,
    room         VARCHAR(100) DEFAULT NULL,
    status       ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    admin_note   TEXT         DEFAULT NULL,
    submitted_at TIMESTAMP    NULL DEFAULT NULL,
    reviewed_at  TIMESTAMP    NULL DEFAULT NULL,
    reviewed_by  INT          DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id)  REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
