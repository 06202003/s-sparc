-- Add optional course and assessment references to environmental_impact_logs
-- NOTE: Jalankan file ini sekali saja di database Anda.

ALTER TABLE environmental_impact_logs
    ADD COLUMN course_id CHAR(36) NULL AFTER job_id,
    ADD COLUMN assessment_id VARCHAR(191) NULL AFTER course_id,
    ADD KEY idx_eil_course (course_id),
    ADD KEY idx_eil_assessment (assessment_id);
