-- ============================================================
-- Quaddit  Complete MySQL Database Setup
-- Database Name: quaddit
-- ============================================================

CREATE DATABASE IF NOT EXISTS `quaddit` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `quaddit`;

-- Drop existing tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `post_images`;
DROP TABLE IF EXISTS `replies`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `admins`;
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



