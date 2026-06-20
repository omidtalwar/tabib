-- MMI Collection — Institute Management System
-- Run this file once to set up the database.

CREATE DATABASE IF NOT EXISTS mmi_collection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mmi_collection;

-- Users table (all roles)
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    phone       VARCHAR(20),
    avatar      VARCHAR(255),
    status      TINYINT(1)   NOT NULL DEFAULT 1,  -- 1=active, 0=inactive
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Teachers (extra profile data)
CREATE TABLE IF NOT EXISTS teachers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    subject      VARCHAR(100),
    qualification VARCHAR(150),
    joining_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Students (extra profile data)
CREATE TABLE IF NOT EXISTS students (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    roll_no      VARCHAR(50) UNIQUE,
    father_name  VARCHAR(100),
    department   VARCHAR(100),
    semester     VARCHAR(50),
    shift        VARCHAR(50),
    class_id     INT,
    dob          DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Classes / batches
CREATE TABLE IF NOT EXISTS classes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    teacher_id  INT,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Uploaded study materials by teachers
CREATE TABLE IF NOT EXISTS materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    class_id    INT,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    file_path   VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL
);

-- Subject catalog (admin-managed)
CREATE TABLE IF NOT EXISTS subjects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    semester   VARCHAR(50)  NOT NULL,
    credits    INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teacher course assignments
CREATE TABLE IF NOT EXISTS teacher_courses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT NOT NULL,
    no           INT NOT NULL DEFAULT 1,
    subject_name VARCHAR(150) NOT NULL,
    department   VARCHAR(100),
    semester     VARCHAR(50),
    shift        VARCHAR(50),
    credits      INT NOT NULL DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Seed default admin account  (password: admin123)
INSERT IGNORE INTO users (name, email, password, role)
VALUES ('Admin', 'admin@mmi.com', '$2y$10$9qmcuO.UOW.hR4Oig28UIe2Ymvqhmd.yMMUtIVci3XLM5Z1rBXhV2', 'admin');
