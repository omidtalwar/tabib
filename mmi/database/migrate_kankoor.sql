-- MMI Collection — Migration: Kankoor (Entrance Exam) Waitlist
-- Run once via phpMyAdmin or MySQL CLI.

CREATE TABLE IF NOT EXISTS kankoor_waitlist (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    father_name     VARCHAR(100) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    father_phone    VARCHAR(20)  DEFAULT NULL,
    department      VARCHAR(100) DEFAULT NULL,
    year_register   SMALLINT     NOT NULL,
    year_graduation SMALLINT     NOT NULL,
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
