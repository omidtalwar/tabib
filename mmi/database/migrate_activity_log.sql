-- MMI Collection — Migration: Activity Log
-- Run once via phpMyAdmin or MySQL CLI.

CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT       AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          DEFAULT NULL,
    user_name   VARCHAR(100) DEFAULT NULL,
    role        VARCHAR(20)  DEFAULT 'system',
    action      VARCHAR(100) DEFAULT NULL,
    description TEXT         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role      (role),
    INDEX idx_created   (created_at),
    INDEX idx_id_desc   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
