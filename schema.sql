-- Resume Analyzer Database Schema
-- Run this in phpMyAdmin or MySQL CLI: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS `resume_analyzer`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `resume_analyzer`;

-- -------------------------------------------------------
-- Table: job_roles
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_roles` (
    `id`        INT          NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: skills_master
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `skills_master` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `skill_name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: role_skills (many-to-many mapping)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_skills` (
    `id`       INT NOT NULL AUTO_INCREMENT,
    `role_id`  INT NOT NULL,
    `skill_id` INT NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`role_id`)  REFERENCES `job_roles`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills_master`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: resumes (stores each analysis result)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `resumes` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `file_path`      TEXT         NOT NULL,
    `extracted_text` LONGTEXT,
    `job_role_id`    INT          NULL,
    `score`          INT          NOT NULL DEFAULT 0,
    `analysis_json`  LONGTEXT,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`job_role_id`) REFERENCES `job_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: If upgrading an existing install, run these ALTER statements once:
-- ALTER TABLE `resumes` MODIFY COLUMN `job_role_id` INT NULL;
-- ALTER TABLE `resumes` DROP FOREIGN KEY <existing_fk_name>;
-- ALTER TABLE `resumes` ADD CONSTRAINT FOREIGN KEY (`job_role_id`) REFERENCES `job_roles`(`id`) ON DELETE SET NULL;

-- -------------------------------------------------------
-- Seed: Job Roles
-- -------------------------------------------------------
INSERT INTO `job_roles` (`id`, `role_name`) VALUES
    (1, 'Backend Developer'),
    (2, 'Frontend Developer'),
    (3, 'Data Analyst');

-- -------------------------------------------------------
-- Seed: Skills
-- -------------------------------------------------------
INSERT INTO `skills_master` (`id`, `skill_name`) VALUES
    (1, 'PHP'),
    (2, 'MySQL'),
    (3, 'JavaScript'),
    (4, 'React'),
    (5, 'Node.js'),
    (6, 'Python'),
    (7, 'SQL');

-- -------------------------------------------------------
-- Seed: Role → Skill Mappings
-- -------------------------------------------------------
-- Backend Developer: PHP, MySQL, Node.js, SQL
INSERT INTO `role_skills` (`role_id`, `skill_id`) VALUES
    (1, 1),  -- Backend → PHP
    (1, 2),  -- Backend → MySQL
    (1, 5),  -- Backend → Node.js
    (1, 7);  -- Backend → SQL

-- Frontend Developer: JavaScript, React, Node.js
INSERT INTO `role_skills` (`role_id`, `skill_id`) VALUES
    (2, 3),  -- Frontend → JavaScript
    (2, 4),  -- Frontend → React
    (2, 5);  -- Frontend → Node.js

-- Data Analyst: Python, SQL, MySQL
INSERT INTO `role_skills` (`role_id`, `skill_id`) VALUES
    (3, 6),  -- Data Analyst → Python
    (3, 7),  -- Data Analyst → SQL
    (3, 2);  -- Data Analyst → MySQL
