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
(6, 'Cybersecurity & Ethical Hacking Symposium', 'Discover vulnerability assessments, penetration testing techniques, and defense strategies for enterprise systems.', 'Tech & Innovation', 'Security', 'IT Seminar Hall 1', '2026-11-02 10:00:00', 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&auto=format&fit=crop&q=80', 100, 'upcoming'),
(7, 'Campus Photography & Short Film Expo', 'Showcasing evocative visual storytelling, documentary snippets, and award-winning student photography.', 'Arts & Culture', 'Media', 'Fine Arts Gallery, Room 101', '2026-11-05 16:00:00', 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=600&auto=format&fit=crop&q=80', 150, 'upcoming'),
(8, 'Classical & Contemporary Music Gala', 'An evening featuring instrumental solos, acoustic bands, and university choir performances.', 'Arts & Culture', 'Music', 'University Amphitheatre', '2026-11-08 18:30:00', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=600&auto=format&fit=crop&q=80', 200, 'upcoming'),
(9, 'Inter-Department Football Championship', 'Cheer for your department in the grand finals of the semester football knockout tournament.', 'Sports & Wellness', 'Sports', 'Main University Sports Ground', '2026-11-12 15:00:00', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=600&auto=format&fit=crop&q=80', 300, 'upcoming'),
(10, 'Mindfulness, Yoga & Mental Wellness', 'Guided meditation, stress reduction techniques, and breathing exercises for exam season wellness.', 'Sports & Wellness', 'Wellness', 'Student Wellness Center', '2026-11-15 07:00:00', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=600&auto=format&fit=crop&q=80', 80, 'upcoming'),
(11, 'Campus Marathon & 5K Fun Run', 'Join students and faculty for an energetic morning run promoting healthy living and community spirit.', 'Sports & Wellness', 'Fitness', 'North Gate Boulevard', '2026-11-19 06:30:00', 'https://images.unsplash.com/photo-1452626038306-9aae5e071dd3?w=600&auto=format&fit=crop&q=80', 250, 'upcoming'),
(12, 'Research Paper Writing & Publication Seminar', 'Guidelines from senior faculty on crafting peer-reviewed manuscripts, literature reviews, and citations.', 'Academic Seminars', 'Research', 'Auditorium C, Research Block', '2026-11-22 13:30:00', 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&auto=format&fit=crop&q=80', 120, 'upcoming'),
(13, 'Cloud Computing & DevOps Masterclass', 'Hands-on deployment pipelines, Docker container orchestration, and serverless architectures.', 'Tech & Innovation', 'Cloud', 'Software Lab 4, Level 2', '2026-11-26 14:00:00', 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&auto=format&fit=crop&q=80', 90, 'upcoming'),
(14, 'Literature & Poetry Open Mic Night', 'Express your creativity through spoken word, poetry recitations, and storytelling in a warm, welcoming vibe.', 'Arts & Culture', 'Literary', 'Student Lounge Cafe', '2026-11-29 17:30:00', 'https://images.unsplash.com/photo-1471107340929-a87cd0f5b5f3?w=600&auto=format&fit=crop&q=80', 70, 'upcoming'),
(15, 'Graduate Studies & Overseas Scholarships', 'Comprehensive session detailing GRE, TOEFL, scholarship applications, and overseas university admissions.', 'Academic Seminars', 'Higher Ed', 'International Relations Hall', '2026-12-03 11:00:00', 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&auto=format&fit=crop&q=80', 160, 'upcoming'),
(16, 'Badminton & Table Tennis Intra-College League', 'Compete in singles and doubles racket tournaments with medals and awards for top performers.', 'Sports & Wellness', 'Sports', 'Indoor Sports Complex', '2026-12-07 16:00:00', 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=600&auto=format&fit=crop&q=80', 100, 'upcoming');

