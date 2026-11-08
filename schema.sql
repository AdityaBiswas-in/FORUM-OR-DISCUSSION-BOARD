-- ============================================================
-- Quaddit – Complete MySQL Database Setup
-- Compatible with phpMyAdmin (XAMPP MySQL / MariaDB)
-- Database Name: quaddit
-- ============================================================

CREATE DATABASE IF NOT EXISTS `quaddit` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `quaddit`;

-- Drop existing tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `likes`;
DROP TABLE IF EXISTS `post_images`;
DROP TABLE IF EXISTS `replies`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `faculty`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `authorized_admins`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. Table: students
-- Dedicated storage for Student accounts
-- ============================================================
CREATE TABLE `students` (
  `id` VARCHAR(10) NOT NULL COMMENT 'Unique 10-digit Student ID',
  `name` VARCHAR(100) NOT NULL COMMENT 'Full student name',
  `email` VARCHAR(100) NOT NULL COMMENT 'Unique student email',
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `avatar` LONGTEXT NULL COMMENT 'Base64 data URI or image URL',
  `banner_url` LONGTEXT NULL COMMENT 'Base64 data URI or banner image URL',
  `bio` VARCHAR(255) DEFAULT 'Quaddit campus student',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_students_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Table: faculty
-- Dedicated storage for Faculty / Teacher accounts
-- ============================================================
CREATE TABLE `faculty` (
  `id` VARCHAR(10) NOT NULL COMMENT 'Unique 10-digit Faculty ID',
  `name` VARCHAR(100) NOT NULL COMMENT 'Full faculty name',
  `email` VARCHAR(100) NOT NULL COMMENT 'Unique faculty email',
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `avatar` LONGTEXT NULL COMMENT 'Base64 data URI or image URL',
  `banner_url` LONGTEXT NULL COMMENT 'Base64 data URI or banner image URL',
  `bio` VARCHAR(255) DEFAULT 'Faculty member at Quaddit',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_faculty_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Table: admins
-- Dedicated storage for System Administrator accounts
-- ============================================================
CREATE TABLE `admins` (
  `id` VARCHAR(10) NOT NULL COMMENT 'Unique 10-digit Admin ID',
  `name` VARCHAR(100) NOT NULL COMMENT 'Full administrator name',
  `email` VARCHAR(100) NOT NULL COMMENT 'Unique admin email',
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `avatar` LONGTEXT NULL COMMENT 'Base64 data URI or image URL',
  `banner_url` LONGTEXT NULL COMMENT 'Base64 data URI or banner image URL',
  `bio` VARCHAR(255) DEFAULT 'Campus Forum Administrator',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Table: authorized_admins
-- Pre-authorized faculty/staff list allowed to register as Admin
-- ============================================================
CREATE TABLE `authorized_admins` (
  `admin_id` VARCHAR(10) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL DEFAULT 'Academic Administration',
  `auth_key` VARCHAR(100) NOT NULL DEFAULT 'QUAD-ADMIN-2026',
  `is_registered` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Table: users
-- Unified bridge table maintaining relational integrity across
-- posts, replies, group memberships, and likes without polymorphics
-- ============================================================
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uid` VARCHAR(10) NOT NULL UNIQUE COMMENT 'Unique 10-digit ID matching students, faculty, or admins',
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'faculty', 'admin') NOT NULL DEFAULT 'student',
  `account_type` ENUM('student', 'faculty', 'admin') NOT NULL DEFAULT 'student',
  `avatar` LONGTEXT NULL,
  `banner_url` LONGTEXT NULL,
  `bio` VARCHAR(255) DEFAULT 'Quaddit campus member',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. Table: groups
-- Academic discussion groups managed by Administrators
-- ============================================================
CREATE TABLE `groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `image_url` LONGTEXT NULL,
  `status` ENUM('active', 'restricted') NOT NULL DEFAULT 'active',
  `created_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_groups_status` (`status`),
  CONSTRAINT `fk_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. Table: group_members
-- Records membership of users in discussion groups
-- ============================================================
CREATE TABLE `group_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_group_user_unique` (`group_id`, `user_id`),
  CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. Table: posts
-- Discussion posts published inside groups
-- ============================================================
CREATE TABLE `posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `views` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_posts_user` (`user_id`),
  INDEX `idx_posts_group` (`group_id`),
  INDEX `idx_posts_created` (`created_at`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. Table: post_images
-- Multiple image attachments for discussion posts
-- ============================================================
CREATE TABLE `post_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `image_url` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pi_post` (`post_id`),
  CONSTRAINT `fk_pi_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. Table: replies
-- Threaded/nested replies to posts
-- ============================================================
CREATE TABLE `replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `parent_id` INT NULL,
  `content` TEXT NOT NULL,
  `image_url` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_replies_post` (`post_id`),
  INDEX `idx_replies_user` (`user_id`),
  INDEX `idx_replies_parent` (`parent_id`),
  CONSTRAINT `fk_replies_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_replies_parent` FOREIGN KEY (`parent_id`) REFERENCES `replies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. Table: likes
-- Like interactions on posts and replies
-- ============================================================
CREATE TABLE `likes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `post_id` INT NULL,
  `reply_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_likes_user` (`user_id`),
  INDEX `idx_likes_post` (`post_id`),
  INDEX `idx_likes_reply` (`reply_id`),
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_reply` FOREIGN KEY (`reply_id`) REFERENCES `replies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- INITIAL SEED DATA
-- Pre-configured accounts (Password for all seed users: password123)
-- Bcrypt Hash: $2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G
-- ============================================================

-- Authorized Admins Roster
INSERT INTO `authorized_admins` (`admin_id`, `name`, `department`, `auth_key`, `is_registered`, `created_at`) VALUES
('9000000001', 'Dr. Arthur Vance', 'Dean of Academic Affairs', 'QUAD-ADMIN-2026', 1, NOW()),
('9000000002', 'Prof. Marcus Holloway', 'Faculty Dean of Engineering', 'QUAD-ADMIN-2026', 0, NOW()),
('9000000003', 'Campus Registrar', 'Central Academic Administration', 'QUAD-ADMIN-2026', 0, NOW());

-- 1. Seed Administrator
INSERT INTO `admins` (`id`, `name`, `email`, `password`, `avatar`, `bio`, `created_at`) VALUES
('9000000001', 'Dr. Arthur Vance', 'admin@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'assets/images/avatar_default.svg', 'Dean of Academic Affairs & System Administrator', NOW());

INSERT INTO `users` (`id`, `uid`, `name`, `username`, `email`, `password`, `role`, `account_type`, `avatar`, `bio`, `created_at`) VALUES
(1, '9000000001', 'Dr. Arthur Vance', 'Dr. Arthur Vance', 'admin@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'admin', 'admin', 'assets/images/avatar_default.svg', 'Dean of Academic Affairs & System Administrator', NOW());

-- 2. Seed Faculty
INSERT INTO `faculty` (`id`, `name`, `email`, `password`, `avatar`, `bio`, `created_at`) VALUES
('5000000001', 'Prof. Elena Rostova', 'faculty@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'assets/images/avatar_default.svg', 'Associate Professor in Computer Science', NOW());

INSERT INTO `users` (`id`, `uid`, `name`, `username`, `email`, `password`, `role`, `account_type`, `avatar`, `bio`, `created_at`) VALUES
(2, '5000000001', 'Prof. Elena Rostova', 'Prof. Elena Rostova', 'faculty@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'faculty', 'faculty', 'assets/images/avatar_default.svg', 'Associate Professor in Computer Science', NOW());

-- 3. Seed Student
INSERT INTO `students` (`id`, `name`, `email`, `password`, `avatar`, `bio`, `created_at`) VALUES
('1000000001', 'Alex Rivera', 'student@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'assets/images/avatar_default.svg', 'Undergraduate Student in Software Engineering', NOW());

INSERT INTO `users` (`id`, `uid`, `name`, `username`, `email`, `password`, `role`, `account_type`, `avatar`, `bio`, `created_at`) VALUES
(3, '1000000001', 'Alex Rivera', 'Alex Rivera', 'student@quaddit.edu', '$2y$12$0QjphQlDw7rGP4i6T60CtOhFQn2lPu/5LU8mlM0iQ/cZf18mXW/0G', 'student', 'student', 'assets/images/avatar_default.svg', 'Undergraduate Student in Software Engineering', NOW());

-- Seed Academic Discussion Groups
INSERT INTO `groups` (`id`, `name`, `description`, `image_url`, `status`, `created_by`, `created_at`) VALUES
(1, 'Computer Science & Engineering', 'Discussions on algorithms, software architecture, data structures, and engineering projects.', NULL, 'active', 1, NOW()),
(2, 'Artificial Intelligence & Machine Learning', 'Exploration of neural networks, deep learning models, LLMs, and real-world AI applications.', NULL, 'active', 1, NOW()),
(3, 'Campus Announcements & Events', 'Official university notices, guest seminars, academic calendars, and department symposiums.', NULL, 'active', 1, NOW()),
(4, 'Mathematics & Applied Statistics', 'Calculus, discrete mathematics, linear algebra, probability, and quantitative problem solving.', NULL, 'active', 1, NOW());

-- Seed Group Memberships
INSERT INTO `group_members` (`group_id`, `user_id`, `joined_at`) VALUES
(1, 1, NOW()),
(1, 2, NOW()),
(1, 3, NOW()),
(2, 1, NOW()),
(2, 2, NOW()),
(3, 1, NOW()),
(3, 2, NOW()),
(3, 3, NOW()),
(4, 1, NOW());

-- Seed Initial Discussion Posts
INSERT INTO `posts` (`id`, `user_id`, `group_id`, `title`, `content`, `views`, `created_at`) VALUES
(1, 1, 3, 'Welcome to Quaddit – Campus Academic Discussion Platform', 'Welcome students and faculty members to Quaddit! This platform is designed for academic collaboration, course discussions, and campus announcements. Join your relevant groups to participate in discussions and share resources.', 42, NOW()),
(2, 2, 1, 'Guidelines for the Upcoming Software Engineering Project Submissions', 'Dear students, please ensure your repository contains proper unit tests and architectural documentation before final submission next Friday. Office hours are open for consultations.', 28, NOW()),
(3, 3, 1, 'Question regarding B-Tree rebalancing algorithms in Database Internals', 'While studying index structures, I had a question regarding split operations during bulk insertions in B+ Trees. Is there an optimal batch threshold before triggering rebalancing?', 15, NOW());

-- Seed Initial Replies
INSERT INTO `replies` (`id`, `post_id`, `user_id`, `parent_id`, `content`, `created_at`) VALUES
(1, 3, 2, NULL, 'Great question Alex! In production systems like InnoDB, pages are split at 50% capacity unless sequential insertions are detected, in which case page splits occur at the right boundary to minimize page fragmentation.', NOW()),
(2, 3, 3, 1, 'Thank you Professor Rostova! That clarifies why sequential key insertion performs faster in benchmarks.', NOW());

-- Seed Likes
INSERT INTO `likes` (`user_id`, `post_id`, `reply_id`, `created_at`) VALUES
(1, 1, NULL, NOW()),
(2, 1, NULL, NOW()),
(3, 1, NULL, NOW()),
(3, 2, NULL, NOW()),
(1, NULL, 1, NOW()),
(3, NULL, 1, NOW());

