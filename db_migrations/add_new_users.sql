-- SQL Script to Add New Users
-- Generated on: 2026-01-27
-- Password Hash: SHA256 of "password" (default password)
-- Default Password Hash: 5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8

INSERT INTO users (user_id, username, email, password_hash, is_admin, created_at) VALUES
(UUID(), '2472026', '2472026@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2472033', '2472033@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372065', '2372065@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372020', '2372020@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2373003', '2373003@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2373008', '2373008@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2472013', '2472013@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372068', '2372068@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2472052', '2472052@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372054', '2372054@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372045', '2372045@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372055', '2372055@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372023', '2372023@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2372012', '2372012@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2373030', '2373030@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2473018', '2473018@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2373005', '2373005@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW()),
(UUID(), '2272019', '2272019@maranatha.ac.id', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 0, NOW());

-- Total: 18 new users added
-- All users have default password: "password" (SHA256)
-- Change password after first login or run an admin script to set individual passwords

-- Add users to course
INSERT INTO user_courses (id, user_id, course_id, role, created_at)
SELECT UUID(), user_id, '5a1cc6a3-fd1d-11f0-b898-345a60cd0a32', 'student', NOW()
FROM users
WHERE username IN (
    '2472026', '2472033', '2372065', '2372020', '2373003',
    '2373008', '2472013', '2372068', '2472052', '2372054',
    '2372045', '2372055', '2372023', '2372012', '2373030',
    '2473018', '2373005', '2272019'
);
