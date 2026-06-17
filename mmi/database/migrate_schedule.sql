-- Schedule system tables

CREATE TABLE IF NOT EXISTS schedules (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    department    VARCHAR(100) NOT NULL,
    semester      VARCHAR(50)  NOT NULL,
    shift         VARCHAR(50)  NOT NULL,
    academic_year VARCHAR(20)  DEFAULT NULL,
    created_by    INT          NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class (department, semester, shift),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedule_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT          NOT NULL,
    day_of_week TINYINT      NOT NULL COMMENT '1=Sat 2=Sun 3=Mon 4=Tue 5=Wed 6=Thu',
    time_start  TIME         NOT NULL,
    time_end    TIME         NOT NULL,
    subject     VARCHAR(150) DEFAULT NULL,
    teacher     VARCHAR(100) DEFAULT NULL,
    room        VARCHAR(60)  DEFAULT NULL,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
