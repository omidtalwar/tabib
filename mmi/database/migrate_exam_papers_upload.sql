-- Allow teachers to upload a ready-made .docx exam file instead of typing questions
ALTER TABLE exam_papers
    ADD COLUMN IF NOT EXISTS source ENUM('form','upload') NOT NULL DEFAULT 'form' AFTER language,
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL AFTER source;
