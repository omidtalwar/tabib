-- MMI Drive — File Storage System
-- Run once via phpMyAdmin or MySQL CLI.

CREATE TABLE IF NOT EXISTS drive_folders (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT          DEFAULT NULL,
    name        VARCHAR(200) NOT NULL,
    created_by  INT          DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    CONSTRAINT fk_df_parent  FOREIGN KEY (parent_id)  REFERENCES drive_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_df_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS drive_files (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    folder_id     INT          DEFAULT NULL,
    name          VARCHAR(200) NOT NULL,
    original_name VARCHAR(200) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_size     BIGINT       DEFAULT 0,
    extension     VARCHAR(20)  DEFAULT NULL,
    category      VARCHAR(50)  DEFAULT 'Other',
    uploaded_by   INT          DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_folder   (folder_id),
    INDEX idx_category (category),
    CONSTRAINT fk_dfile_folder   FOREIGN KEY (folder_id)   REFERENCES drive_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_dfile_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
