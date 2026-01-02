-- 006_add_users_is_admin_and_seed.sql
-- Add `is_admin` column to `users` table and seed a default admin user if missing.

-- This migration is safe to run multiple times. It uses INFORMATION_SCHEMA checks
-- to avoid errors on older MySQL versions.

DELIMITER $$
CREATE PROCEDURE add_is_admin_and_seed()
BEGIN
  -- Add is_admin column if missing
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_admin') = 0 THEN
    ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
  END IF;

  -- If no 'admin' user exists, create one with password 'admin' (hashed with SHA2).
  -- If an 'admin' user exists, ensure is_admin=1 for that user.
  IF (SELECT COUNT(*) FROM users WHERE username='admin') = 0 THEN
    INSERT INTO users (user_id, username, email, password_hash, is_admin)
    VALUES (UUID(), 'admin', 'admin@localhost', SHA2('admin',256), 1);
  ELSE
    UPDATE users SET is_admin=1 WHERE username='admin' LIMIT 1;
  END IF;
END$$

CALL add_is_admin_and_seed()$$
DROP PROCEDURE add_is_admin_and_seed$$
DELIMITER ;

-- IMPORTANT:
-- This migration creates an 'admin' user with password 'admin' if none exists.
-- Change the password immediately after running the migration:
--   1) Login as admin and change password via application UI if available, OR
--   2) Update the users.password_hash with a SHA2(...) value for a secure password.
