This folder contains SQL migrations for schema changes.

Migration 005_add_assessment_end_date_and_final_points.sql

- Adds `end_date` (DATETIME NULL) to `assessments`.
- Adds `final_points` (DECIMAL(7,2) NULL) to `user_points_assessment`.

How to apply (MySQL):

1. Backup your database before running migrations.
2. From a MySQL client or using `mysql` CLI, run:

   mysql -u <user> -p <database> < 005_add_assessment_end_date_and_final_points.sql

Notes:

- Some MySQL versions do not support `ADD COLUMN IF NOT EXISTS`. If the migration fails,
  edit the SQL to remove the `IF NOT EXISTS` clauses or add conditional checks.

Backfill guidance:

- After adding `end_date`, ensure every `assessments` row has a valid `end_date` set.
- To backfill `final_points` for historical assessments, run the application endpoint:

  POST /compute-assessment-points with JSON body {"assessment_id": "<ASSESSMENT_ID>"}

  or run a script that calls the `compute_assessment_final_points(assessment_id)` function.

Automation:

- Recommended: schedule a daily job to run computation for all assessments where `end_date` < NOW()
  and where points haven't been computed or need re-computation.

If you want, I can add a small admin script to run backfill across all assessments.
