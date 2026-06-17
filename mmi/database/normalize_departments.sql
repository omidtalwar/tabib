-- Normalize department names across all tables.
-- English name (name_en) is the canonical value stored in data columns.
-- Pashto name (name_ps) is display-only, kept only in the departments table.
-- Run once in phpMyAdmin after seeding data from the schedule JSON.

USE mmi_collection;

-- ── students.department ──────────────────────────────────────────
UPDATE students SET department = 'Nursing'    WHERE department IN ('نرسنګ');
UPDATE students SET department = 'Pharmacy'   WHERE department IN ('فارمسي','درملپوهنه');
UPDATE students SET department = 'Protiz'     WHERE department IN ('پروتیز');
UPDATE students SET department = 'Technology' WHERE department IN ('تکنالوجي','ټیکنالوجي');

-- ── teachers.department (comma-separated list) ───────────────────
UPDATE teachers SET department = REPLACE(department, 'نرسنګ',     'Nursing')    WHERE department LIKE '%نرسنګ%';
UPDATE teachers SET department = REPLACE(department, 'فارمسي',    'Pharmacy')   WHERE department LIKE '%فارمسي%';
UPDATE teachers SET department = REPLACE(department, 'درملپوهنه', 'Pharmacy')   WHERE department LIKE '%درملپوهنه%';
UPDATE teachers SET department = REPLACE(department, 'پروتیز',    'Protiz')     WHERE department LIKE '%پروتیز%';
UPDATE teachers SET department = REPLACE(department, 'تکنالوجي',  'Technology') WHERE department LIKE '%تکنالوجي%';
UPDATE teachers SET department = REPLACE(department, 'ټیکنالوجي', 'Technology') WHERE department LIKE '%ټیکنالوجي%';

-- ── teacher_courses.department ───────────────────────────────────
UPDATE teacher_courses SET department = 'Nursing'    WHERE department IN ('نرسنګ');
UPDATE teacher_courses SET department = 'Pharmacy'   WHERE department IN ('فارمسي','درملپوهنه');
UPDATE teacher_courses SET department = 'Protiz'     WHERE department IN ('پروتیز');
UPDATE teacher_courses SET department = 'Technology' WHERE department IN ('تکنالوجي','ټیکنالوجي');

-- ── subjects.department ──────────────────────────────────────────
UPDATE subjects SET department = 'Nursing'    WHERE department IN ('نرسنګ');
UPDATE subjects SET department = 'Pharmacy'   WHERE department IN ('فارمسي','درملپوهنه');
UPDATE subjects SET department = 'Protiz'     WHERE department IN ('پروتیز');
UPDATE subjects SET department = 'Technology' WHERE department IN ('تکنالوجي','ټیکنالوجي');

-- ── schedules.department ─────────────────────────────────────────
UPDATE schedules SET department = 'Nursing'    WHERE department IN ('نرسنګ');
UPDATE schedules SET department = 'Pharmacy'   WHERE department IN ('فارمسي','درملپوهنه');
UPDATE schedules SET department = 'Protiz'     WHERE department IN ('پروتیز');
UPDATE schedules SET department = 'Technology' WHERE department IN ('تکنالوجي','ټیکنالوجي');

-- ── exam_schedules.department ────────────────────────────────────
UPDATE exam_schedules SET department = 'Nursing'    WHERE department IN ('نرسنګ');
UPDATE exam_schedules SET department = 'Pharmacy'   WHERE department IN ('فارمسي','درملپوهنه');
UPDATE exam_schedules SET department = 'Protiz'     WHERE department IN ('پروتیز');
UPDATE exam_schedules SET department = 'Technology' WHERE department IN ('تکنالوجي','ټیکنالوجي');

-- ── materials — via teacher_courses (already fixed above) ────────

-- Verify: these queries should all return 0 rows after running
-- SELECT DISTINCT department FROM students WHERE department REGEXP '[ا-ي]';
-- SELECT DISTINCT department FROM teacher_courses WHERE department REGEXP '[ا-ي]';
-- SELECT DISTINCT department FROM subjects WHERE department REGEXP '[ا-ي]';
