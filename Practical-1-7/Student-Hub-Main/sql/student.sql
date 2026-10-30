-- =====================================================================
-- StudentHub Database Schema
-- File: sql/student.sql
-- Description: Dynamic database schema for StudentHub university portal
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

-- 1. DATABASE INITIALIZATION
CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- Disable foreign key checks during setup to prevent drop order conflicts
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- 2. TABLE: students
-- Represents registered university scholars / users.
-- Enhancements from ER diagram:
--   - Added `password` (VARCHAR(255)) for secure hash storage (needed for login/register).
--   - Added `roll_number` (VARCHAR(30)) matching student profile info.
--   - Added `profile_image` & `bio` for profile page support.
--   - Added `updated_at` timestamp.
-- =====================================================================
CREATE TABLE `students` (
    `student_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt or Argon2 hashed password',
    `roll_number` VARCHAR(30) DEFAULT NULL,
    `department` VARCHAR(50) DEFAULT NULL,
    `semester` TINYINT UNSIGNED DEFAULT 1,
    `phone` VARCHAR(15) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`student_id`),
    UNIQUE KEY `uq_students_email` (`email`),
    UNIQUE KEY `uq_students_roll_number` (`roll_number`),
    INDEX `idx_students_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 3. TABLE: events
-- Represents campus events, hackathons, workshops, and seminars.
-- Enhancements from ER diagram:
--   - Added `image` (VARCHAR(255)) to feed event cards in UI without breaking layouts.
--   - Added `badge` (VARCHAR(50)) for category tags displayed on UI cards.
--   - Added `status` (ENUM) to track upcoming, completed, or cancelled status.
--   - Added `updated_at` timestamp and search indexes on category & event_date.
-- =====================================================================
CREATE TABLE `events` (
    `event_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(50) NOT NULL,
    `badge` VARCHAR(50) DEFAULT 'Event',
    `location` VARCHAR(100) NOT NULL,
    `event_date` DATETIME NOT NULL,
    `image` VARCHAR(255) DEFAULT 'image/Full-Stack.jpg',
    `max_capacity` INT UNSIGNED NOT NULL DEFAULT 100,
    `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`event_id`),
    INDEX `idx_events_category` (`category`),
    INDEX `idx_events_date` (`event_date`),
    INDEX `idx_events_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 4. TABLE: registrations (Bridge / Junction Table: 1:N with both)
-- Connects students with events they enroll in.
-- Constraints & Features:
--   - Composite UNIQUE(student_id, event_id) prevents duplicate bookings.
--   - Foreign keys with ON DELETE CASCADE to maintain referential integrity.
--   - Unique ticket_code for event passes / QR generation.
-- =====================================================================
CREATE TABLE `registrations` (
    `registration_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    `ticket_code` VARCHAR(64) NOT NULL,
    `status` ENUM('confirmed', 'pending', 'cancelled', 'attended') NOT NULL DEFAULT 'confirmed',
    `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`registration_id`),
    UNIQUE KEY `uq_student_event` (`student_id`, `event_id`),
    UNIQUE KEY `uq_ticket_code` (`ticket_code`),
    INDEX `idx_reg_student` (`student_id`),
    INDEX `idx_reg_event` (`event_id`),
    
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


-- =====================================================================
-- 5. USEFUL VIEW: vw_student_registrations
-- Convenient joined view for dashboard, tickets, and admin reports.
-- =====================================================================
CREATE OR REPLACE VIEW `vw_student_registrations` AS
SELECT 
    r.registration_id,
    r.ticket_code,
    r.status AS registration_status,
    r.registered_at,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    s.email AS student_email,
    s.roll_number,
    s.department AS student_department,
    e.event_id,
    e.title AS event_title,
    e.category AS event_category,
    e.badge AS event_badge,
    e.location AS event_location,
    e.event_date,
    e.image AS event_image
FROM `registrations` r
JOIN `students` s ON r.student_id = s.student_id
JOIN `events` e ON r.event_id = e.event_id;


-- =====================================================================
-- 6. SEED DATA (Ready for testing in XAMPP phpMyAdmin)
-- Matches sample data from data/events.json and profile pages.
-- Default password for demo student: 'password123' (hashed using standard bcrypt)
-- =====================================================================

-- Seed Demo Students
INSERT INTO `students` (`student_id`, `first_name`, `last_name`, `email`, `password`, `roll_number`, `department`, `semester`, `phone`, `bio`) VALUES
(1, 'Alex', 'Johnson', 'alex.ce23@charusat.edu.in', '$2y$10$eA89nPEmY8HvhfJzIqCksOqZJzJ0X9zP/h4Qc5Y51Hh.R1y5vH5ea', '22CE042', 'Computer Science & Engineering', 5, '+91 98765 43210', 'Focusing on full-stack web applications, machine learning architectures, and campus student community initiatives.'),
(2, 'Sarah', 'Miller', 'sarah.it22@charusat.edu.in', '$2y$10$eA89nPEmY8HvhfJzIqCksOqZJzJ0X9zP/h4Qc5Y51Hh.R1y5vH5ea', '22IT015', 'Information Technology', 5, '+91 98765 43211', 'Enthusiastic about cloud native systems, open-source development, and UI/UX design.'),
(3, 'Rohan', 'Patel', 'rohan.ds24@charusat.edu.in', '$2y$10$eA89nPEmY8HvhfJzIqCksOqZJzJ0X9zP/h4Qc5Y51Hh.R1y5vH5ea', '24DS089', 'Data Science & Analytics', 3, '+91 98765 43212', 'Exploring deep learning models, natural language processing, and high-performance computing.');

-- Seed Events (from data/events.json)
INSERT INTO `events` (`event_id`, `title`, `description`, `category`, `badge`, `location`, `event_date`, `image`, `max_capacity`, `status`) VALUES
(1, 'AI Workshop', 'Join leading researchers for a deep dive into the ethical implications of large language models in academia.', 'Tech & Innovation', 'Tech', 'Main Library, Hall B', '2026-10-15 14:00:00', 'image/AI-one.jpg', 120, 'upcoming'),
(2, 'Autumn Arts Festival', 'Celebrate the change of seasons with live music, local food vendors, and student art exhibitions.', 'Arts & Culture', 'Cultural', 'Central Quad', '2026-10-18 11:00:00', 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&auto=format&fit=crop&q=80', 250, 'upcoming'),
(3, 'Full-Stack Web Development Workshop', 'Learn how to build production-ready web applications that handle millions of users using modern architectures.', 'Tech & Innovation', 'Academic', 'Archives Dept, Room 402', '2026-10-22 15:30:00', 'image/Full-Stack.jpg', 80, 'upcoming'),
(4, 'Machine Learning & Deep Learning Bootcamp', 'Learn how LLMs work under the hood, how deep learning models train on massive datasets, and techniques for handling noisy data.', 'Tech & Innovation', 'ML', 'Computer Engineering Block, Lab 3', '2026-10-25 18:00:00', 'image/ML.webp', 60, 'upcoming'),
(5, 'Inter-College Hackathon 2026', 'A 24-hour sprint to solve real-world problems with cutting-edge software and hardware prototypes.', 'Tech & Innovation', 'Hackathon', 'Tech Hub Auditorium', '2026-10-28 09:00:00', 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=600&auto=format&fit=crop&q=80', 200, 'upcoming'),
(6, 'Robotics & Embedded Systems Showcase', 'Explore autonomous quadcopters, robotic arms, and industrial IoT hardware prototypes developed by final-year students.', 'Tech & Innovation', 'Robotics', 'Main Auditorium, North Wing', '2026-11-03 14:00:00', 'image/Robot.webp', 150, 'upcoming');

-- Seed Sample Registrations
INSERT INTO `registrations` (`registration_id`, `student_id`, `event_id`, `ticket_code`, `status`, `registered_at`) VALUES
(1, 1, 1, 'SH-2026-AI-1011', 'confirmed', '2026-09-28 10:15:00'),
(2, 1, 3, 'SH-2026-FS-1012', 'confirmed', '2026-09-29 11:30:00'),
(3, 2, 1, 'SH-2026-AI-1013', 'confirmed', '2026-09-29 14:20:00'),
(4, 3, 4, 'SH-2026-ML-1014', 'confirmed', '2026-09-30 09:00:00');
