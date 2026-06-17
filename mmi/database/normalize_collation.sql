-- Align collation of the newer workflow tables to match the older tables
-- (students/teachers use utf8mb4_general_ci). This prevents
-- "Illegal mix of collations" errors when joining on department/semester/shift.
-- Run once in phpMyAdmin.

USE mmi_collection;

ALTER TABLE exam_papers       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE teacher_schedules CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE exam_schedules    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- These may already be general_ci; converting is harmless and idempotent.
ALTER TABLE schedules         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE schedule_slots    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE teacher_courses   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE subjects          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
