-- =====================================================================
-- StudentHub - Events Table Definition & Seed Data
-- File: sql/events.sql
-- Description: Creates and populates the campus events entity
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

USE `studenthub_db`;

-- Drop existing table if needed (respecting foreign key relationships)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `events`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- TABLE: events
-- Primary Key: event_id
-- Indexes: idx_events_category, idx_events_date, idx_events_status
-- -------------------------------------------------------------
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

-- -------------------------------------------------------------
-- SEED DATA: Campus Events (from data/events.json)
-- -------------------------------------------------------------
INSERT INTO `events` (`event_id`, `title`, `description`, `category`, `badge`, `location`, `event_date`, `image`, `max_capacity`, `status`) VALUES
(1, 'AI Workshop', 'Join leading researchers for a deep dive into the ethical implications of large language models in academia.', 'Tech & Innovation', 'Tech', 'Main Library, Hall B', '2026-10-15 14:00:00', 'image/AI-one.jpg', 120, 'upcoming'),
(2, 'Autumn Arts Festival', 'Celebrate the change of seasons with live music, local food vendors, and student art exhibitions.', 'Arts & Culture', 'Cultural', 'Central Quad', '2026-10-18 11:00:00', 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&auto=format&fit=crop&q=80', 250, 'upcoming'),
(3, 'Full-Stack Web Development Workshop', 'Learn how to build production-ready web applications that handle millions of users using modern architectures.', 'Tech & Innovation', 'Academic', 'Archives Dept, Room 402', '2026-10-22 15:30:00', 'image/Full-Stack.jpg', 80, 'upcoming'),
(4, 'Machine Learning & Deep Learning Bootcamp', 'Learn how LLMs work under the hood, how deep learning models train on massive datasets, and techniques for handling noisy data.', 'Tech & Innovation', 'ML', 'Computer Engineering Block, Lab 3', '2026-10-25 18:00:00', 'image/ML.webp', 60, 'upcoming'),
(5, 'Inter-College Hackathon 2026', 'A 24-hour sprint to solve real-world problems with cutting-edge software and hardware prototypes.', 'Tech & Innovation', 'Hackathon', 'Tech Hub Auditorium', '2026-10-28 09:00:00', 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=600&auto=format&fit=crop&q=80', 200, 'upcoming'),
(6, 'Robotics & Embedded Systems Showcase', 'Explore autonomous quadcopters, robotic arms, and industrial IoT hardware prototypes developed by final-year students.', 'Tech & Innovation', 'Robotics', 'Main Auditorium, North Wing', '2026-11-03 14:00:00', 'image/Robot.webp', 150, 'upcoming');
