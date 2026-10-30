-- =====================================================================
-- StudentHub - Event Registrations Table Definition & Seed Data
-- File: sql/registration.sql
-- Description: Creates associative junction entity linking students and events
--              for event-level bookings with status flags ('confirm', 'not-confirm')
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

USE `studenthub_db`;

-- Drop view, procedure, and table if existing
DROP VIEW IF EXISTS `vw_student_registrations`;
DROP PROCEDURE IF EXISTS `sp_register_student`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `registrations`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- TABLE: registrations (Event Booking Table)
-- Primary Key: registration_id
-- Foreign Keys: student_id -> students(student_id) [CASCADE]
--               event_id   -> events(event_id)     [CASCADE]
-- Attributes: event_date, ticket_code, status ('confirm' | 'not-confirm')
-- -------------------------------------------------------------
CREATE TABLE `registrations` (
    `registration_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    `event_date` DATETIME NOT NULL,
    `ticket_code` VARCHAR(64) NOT NULL,
    `status` ENUM('confirm', 'not-confirm') NOT NULL DEFAULT 'confirm',
    `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`registration_id`),
    UNIQUE KEY `uq_student_event` (`student_id`, `event_id`),
    UNIQUE KEY `uq_ticket_code` (`ticket_code`),
    INDEX `idx_reg_student` (`student_id`),
    INDEX `idx_reg_event` (`event_id`),
    INDEX `idx_reg_status` (`status`),
    
    CONSTRAINT `fk_registrations_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
        
    CONSTRAINT `fk_registrations_event`
        FOREIGN KEY (`event_id`)
        REFERENCES `events` (`event_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SEED DATA: Event Registrations (Single Base Dummy Record)
-- -------------------------------------------------------------
INSERT INTO `registrations` (`registration_id`, `student_id`, `event_id`, `event_date`, `ticket_code`, `status`, `registered_at`) VALUES
(1, 1, 1, '2026-10-15 14:00:00', 'SH-2026-AI-1011', 'confirm', '2026-09-28 10:15:00');

-- -------------------------------------------------------------
-- VIEW: vw_student_registrations (Joined view for reporting)
-- -------------------------------------------------------------
CREATE OR REPLACE VIEW `vw_student_registrations` AS
SELECT 
    r.registration_id,
    r.ticket_code,
    r.status AS registration_status,
    r.registered_at,
    s.student_id,
    s.student_code,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    s.email AS student_email,
    s.roll_number,
    s.department AS student_department,
    e.event_id,
    e.title AS event_title,
    e.category AS event_category,
    e.badge AS event_badge,
    e.location AS event_location,
    r.event_date,
    e.image AS event_image
FROM `registrations` r
JOIN `students` s ON r.student_id = s.student_id
JOIN `events` e ON r.event_id = e.event_id;

-- -------------------------------------------------------------
-- STORED PROCEDURE: sp_register_student (Practical 8 Advanced Extension)
-- -------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_register_student`(
    IN  p_student_id INT UNSIGNED,
    IN  p_event_id   INT UNSIGNED,
    OUT p_status     VARCHAR(50),
    OUT p_ticket     VARCHAR(64)
)
proc_label: BEGIN
    DECLARE v_capacity INT DEFAULT 0;
    DECLARE v_enrolled INT DEFAULT 0;
    DECLARE v_exists   INT DEFAULT 0;
    DECLARE v_event_date DATETIME;

    -- Check if student already registered for this event
    SELECT COUNT(*) INTO v_exists 
    FROM `registrations` 
    WHERE `student_id` = p_student_id AND `event_id` = p_event_id;

    IF v_exists > 0 THEN
        SET p_status = 'ALREADY_REGISTERED';
        SET p_ticket = NULL;
        LEAVE proc_label;
    END IF;

    -- Check event capacity limits & retrieve event date
    SELECT `max_capacity`, `event_date` INTO v_capacity, v_event_date FROM `events` WHERE `event_id` = p_event_id;
    SELECT COUNT(*) INTO v_enrolled FROM `registrations` WHERE `event_id` = p_event_id AND `status` = 'confirm';

    IF v_enrolled >= v_capacity THEN
        SET p_status = 'EVENT_FULL';
        SET p_ticket = NULL;
        LEAVE proc_label;
    END IF;

    -- Generate ticket code and insert registration with 'confirm' flag
    SET p_ticket = CONCAT('SH-', YEAR(NOW()), '-', LPAD(FLOOR(RAND() * 90000 + 10000), 5, '0'));
    
    INSERT INTO `registrations` (`student_id`, `event_id`, `event_date`, `ticket_code`, `status`)
    VALUES (p_student_id, p_event_id, v_event_date, p_ticket, 'confirm');

    SET p_status = 'SUCCESS';
END$$

DELIMITER ;
