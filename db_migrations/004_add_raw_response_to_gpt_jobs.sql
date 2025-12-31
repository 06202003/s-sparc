-- Migration: add raw_response column to gpt_jobs
-- Run this SQL against your MySQL database to add the column used for storing
-- the full model output (for debugging / forensic analysis).

ALTER TABLE gpt_jobs
  ADD COLUMN raw_response LONGTEXT NULL AFTER code;

-- Optional: index/update permissions if needed
-- COMMIT;
