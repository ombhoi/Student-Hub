-- =====================================================================
-- StudentHub - Institutional Security & Audit Logs Table (Practical 14)
-- File: sql/audit_logs.sql
-- Description: Creates and seeds the audit_logs table to track administrative
--              traceability, RBAC enforcement, user logins, and system mutations.
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

USE `studenthub_db`;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `user_name` VARCHAR(100) NOT NULL DEFAULT 'System',
    `role` VARCHAR(30) NOT NULL DEFAULT 'admin',
    `action` VARCHAR(50) NOT NULL COMMENT 'AUTH_LOGIN, CREATE_EVENT, UPDATE_EVENT, DELETE_EVENT, CREATE_STUDENT, DELETE_STUDENT, STATUS_TOGGLE',
    `entity_type` VARCHAR(50) NOT NULL DEFAULT 'system' COMMENT 'event, student, auth, system',
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`log_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SEED DATA: Realistic Audit Trail Records for Practical 14 Demo
-- -------------------------------------------------------------
INSERT INTO `audit_logs` (`log_id`, `user_id`, `user_name`, `role`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 2, 'Admin User', 'admin', 'AUTH_LOGIN', 'auth', 2, 'Administrative session established via secure bcrypt verification.', '127.0.0.1', '2026-10-14 09:15:22'),
(2, 2, 'Admin User', 'admin', 'CREATE_EVENT', 'event', 1, 'Accredited new campus event #1: AI Workshop with poster media.', '127.0.0.1', '2026-10-14 09:25:40'),
(3, 2, 'Admin User', 'admin', 'CREATE_EVENT', 'event', 3, 'Created Web Development Workshop (Computing Lab 4).', '127.0.0.1', '2026-10-14 10:10:15'),
(4, 2, 'Admin User', 'admin', 'UPDATE_EVENT', 'event', 1, 'Updated seating capacity and schedule for AI Workshop.', '127.0.0.1', '2026-10-14 10:45:00'),
(5, 2, 'Admin User', 'admin', 'STATUS_TOGGLE', 'event', 2, 'Marked Autumn Arts Festival as upcoming.', '127.0.0.1', '2026-10-14 11:30:10')
ON DUPLICATE KEY UPDATE `log_id` = `log_id`;
