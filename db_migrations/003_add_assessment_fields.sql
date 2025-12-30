-- 003_add_assessment_fields.sql
-- Add assessment/course linkage to session_tokens and per-assessment user points

ALTER TABLE session_tokens
  ADD COLUMN assessment_id VARCHAR(36) NULL AFTER session_id,
  ADD COLUMN course_id VARCHAR(36) NULL AFTER assessment_id;

-- Create per-assessment user points table
CREATE TABLE IF NOT EXISTS user_points_assessment (
  id VARCHAR(36) NOT NULL PRIMARY KEY,
  user_id VARCHAR(36) NOT NULL,
  assessment_id VARCHAR(36),
  course_id VARCHAR(36),
  total_points INT DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
