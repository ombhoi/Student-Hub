-- =====================================================================
-- StudentHub - Contact Inquiries Table Definition & Seed Data
-- File: sql/contact_inquiries.sql
-- Description: Creates and seeds the `contact_inquiries` table
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

USE `studenthub_db`;

-- Drop table if existing
DROP TABLE IF EXISTS `contact_inquiries`;

-- -------------------------------------------------------------
-- TABLE: contact_inquiries
-- Primary Key: id
-- Unique Key: uq_inquiry_code
-- Indexes: idx_inquiry_status
-- -------------------------------------------------------------
CREATE TABLE `contact_inquiries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inquiry_id` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('New', 'In Review', 'Resolved') NOT NULL DEFAULT 'New',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inquiry_code` (`inquiry_id`),
    INDEX `idx_inquiry_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SEED DATA: Contact Inquiries (Single Base Seed Record for Testing)
-- -------------------------------------------------------------
INSERT INTO `contact_inquiries` (`id`, `inquiry_id`, `name`, `email`, `subject`, `message`, `submitted_at`, `status`) VALUES
(1, 'INQ-1001', 'Alex Johnson', 'alex.ce23@charusat.edu.in', 'AI Workshop Venue Inquiries', 'Could you clarify whether laptop chargers and power extensions will be provided in Hall B?', '2026-09-28 14:32:00', 'Resolved');

