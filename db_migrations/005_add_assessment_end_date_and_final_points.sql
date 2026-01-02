-- 005_add_assessment_end_date_and_final_points.sql
-- Add end_date to assessments and final_points to user_points_assessment

-- Add end_date column to assessments (nullable) and final_points to user_points_assessment
-- This file uses INFORMATION_SCHEMA checks to be compatible with older MySQL versions.

DELIMITER $$
CREATE PROCEDURE add_columns_if_missing()
BEGIN
  -- assessments.end_date
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND COLUMN_NAME='end_date') = 0 THEN
    ALTER TABLE assessments ADD COLUMN end_date DATETIME NULL AFTER created_at;
  END IF;

  -- user_points_assessment.final_points
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_points_assessment' AND COLUMN_NAME='final_points') = 0 THEN
    ALTER TABLE user_points_assessment ADD COLUMN final_points DECIMAL(7,2) NULL AFTER total_points;
  END IF;

  -- indexes
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND INDEX_NAME='idx_assessments_end_date') = 0 THEN
    ALTER TABLE assessments ADD INDEX idx_assessments_end_date (end_date);
  END IF;

  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_points_assessment' AND INDEX_NAME='idx_upa_assessment') = 0 THEN
    ALTER TABLE user_points_assessment ADD INDEX idx_upa_assessment (assessment_id);
  END IF;
END$$

CALL add_columns_if_missing()$$
DROP PROCEDURE add_columns_if_missing$$
DELIMITER ;

-- Notes:
-- Run this migration once against your MySQL database. If your MySQL user does not
-- have privileges to create or drop procedures, you can run the ALTER TABLE statements
-- manually after checking INFORMATION_SCHEMA.
