-- Run this once to add the new student columns
USE mmi_collection;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS father_name VARCHAR(100) AFTER roll_no,
    ADD COLUMN IF NOT EXISTS department  VARCHAR(100) AFTER father_name,
    ADD COLUMN IF NOT EXISTS semester    VARCHAR(50)  AFTER department,
    ADD COLUMN IF NOT EXISTS shift       VARCHAR(50)  AFTER semester;
