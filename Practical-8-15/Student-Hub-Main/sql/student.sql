-- =====================================================================
-- StudentHub - Students Table Definition & Seed Data
-- File: sql/student.sql
-- Description: Creates and seeds the `students` table (Entity 1: Initial Registration)
-- Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- Drop students table if existing (respect foreign key checks)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `students`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- TABLE: students (Primary Student Account Registration Table)
-- Primary Key: student_id
-- Unique Keys: uq_students_code (STU-001, etc.), uq_students_email, uq_students_roll_number
-- Indexes: idx_students_department
-- -------------------------------------------------------------
CREATE TABLE `students` (
    `student_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_code` VARCHAR(30) NOT NULL COMMENT 'Unique formatted ID e.g. STU-001, STU-002',
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    `roll_number` VARCHAR(30) DEFAULT NULL,
    `department` VARCHAR(50) DEFAULT NULL,
    `semester` TINYINT UNSIGNED DEFAULT 1,
    `phone` VARCHAR(15) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student' COMMENT 'Role-based access control',
    `remember_token` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed SHA256 token for remember-me',
    `last_login` DATETIME DEFAULT NULL COMMENT 'Timestamp of most recent successful login',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`student_id`),
    UNIQUE KEY `uq_students_code` (`student_code`),
    UNIQUE KEY `uq_students_email` (`email`),
    UNIQUE KEY `uq_students_roll_number` (`roll_number`),
    INDEX `idx_students_department` (`department`),
    INDEX `idx_students_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SEED DATA: Students (Minimum 20 Seeded Records for Practical 11 CRUD)
-- Default password: 'password123' (hashed via password_hash bcrypt)
-- Includes: 1 Admin (Admin User - ADM-001) + 21 Students across departments
-- -------------------------------------------------------------
INSERT INTO `students` (`student_id`, `student_code`, `first_name`, `last_name`, `email`, `password`, `roll_number`, `department`, `semester`, `phone`, `bio`, `role`, `last_login`) VALUES
(1, 'STU-001', 'Alex', 'Johnson', 'alex.ce23@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22CE042', 'Computer Engineering', 5, '+91 98765 43210', 'Focusing on full-stack web applications and cloud architectures.', 'student', '2026-10-09 10:30:00'),
(2, 'ADM-001', 'Admin', 'User', 'admin@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', 'STAFF01', 'Academic Affairs & IT', 8, '+91 98765 00000', 'StudentHub System Administrator overseeing campus event registrations, user authentication, and portal operations.', 'admin', '2026-10-09 11:00:00'),
(3, 'STU-002', 'Marcus', 'Vance', 'marcus.vance@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22CE001', 'Computer Engineering', 5, '+91 98250 11223', 'Competitive programming lead and open-source enthusiast.', 'student', '2026-10-10 14:20:00'),
(4, 'STU-003', 'Elena', 'Rostova', 'elena.rostova@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '21IT042', 'Information Technology', 7, '+91 98251 22334', 'Machine learning and deep learning researcher.', 'student', '2026-10-10 15:45:00'),
(5, 'STU-004', 'Devon', 'Price', 'devon.price@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23CE089', 'Computer Engineering', 3, '+91 98252 33445', 'Robotics and embedded IoT systems coordinator.', 'student', '2026-10-11 09:10:00'),
(6, 'STU-005', 'Priya', 'Sharma', 'priya.sharma@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22IT015', 'Information Technology', 5, '+91 98253 44556', 'Cybersecurity and network vulnerability analyst.', 'student', '2026-10-11 11:30:00'),
(7, 'STU-006', 'Rohan', 'Patel', 'rohan.patel@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23EC033', 'Electronics & Communication', 3, '+91 98254 55667', 'VLSI design and signal processing scholar.', 'student', '2026-10-11 12:15:00'),
(8, 'STU-007', 'Aarav', 'Mehta', 'aarav.mehta@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22ME019', 'Mechanical Engineering', 5, '+91 98255 66778', 'CAD/CAM design and sustainable automotive engineering.', 'student', '2026-10-11 14:00:00'),
(9, 'STU-008', 'Ananya', 'Desai', 'ananya.desai@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '21CL008', 'Civil Engineering', 7, '+91 98256 77889', 'Smart structural modeling and environmental hydrology.', 'student', '2026-10-11 16:30:00'),
(10, 'STU-009', 'Siddharth', 'Joshi', 'siddharth.joshi@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22EE024', 'Electrical Engineering', 5, '+91 98257 88990', 'Renewable microgrid architectures and smart energy metering.', 'student', '2026-10-12 08:45:00'),
(11, 'STU-010', 'Neha', 'Kulkarni', 'neha.kulkarni@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23CE105', 'Computer Engineering', 3, '+91 98258 99001', 'Frontend developer specialized in modern design systems.', 'student', '2026-10-12 09:30:00'),
(12, 'STU-011', 'Vikram', 'Rathod', 'vikram.rathod@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '21IT078', 'Information Technology', 7, '+91 98259 00112', 'Big data pipeline optimization and distributed computing.', 'student', '2026-10-12 10:15:00'),
(13, 'STU-012', 'Kavya', 'Shah', 'kavya.shah@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22EC051', 'Electronics & Communication', 5, '+91 98260 11223', 'FPGA microarchitectures and wireless telemetry systems.', 'student', '2026-10-12 11:00:00'),
(14, 'STU-013', 'Arjun', 'Nair', 'arjun.nair@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23ME044', 'Mechanical Engineering', 3, '+91 98261 22334', 'Computational fluid dynamics and drone aerodynamics.', 'student', '2026-10-12 11:45:00'),
(15, 'STU-014', 'Diya', 'Verma', 'diya.verma@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22CL031', 'Civil Engineering', 5, '+91 98262 33445', 'Geotechnical analysis and earthquake-resistant foundations.', 'student', '2026-10-12 12:30:00'),
(16, 'STU-015', 'Tanvi', 'Pandya', 'tanvi.pandya@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23EE012', 'Electrical Engineering', 3, '+91 98263 44556', 'High voltage power distribution and electric vehicle drivetrains.', 'student', '2026-10-12 13:15:00'),
(17, 'STU-016', 'Karan', 'Malhotra', 'karan.malhotra@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '21CE067', 'Computer Engineering', 7, '+91 98264 55667', 'DevOps engineer and Kubernetes cluster maintainer.', 'student', '2026-10-12 13:50:00'),
(18, 'STU-017', 'Sneha', 'Iyer', 'sneha.iyer@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22IT090', 'Information Technology', 5, '+91 98265 66778', 'Database administrator and indexing optimization researcher.', 'student', '2026-10-12 14:10:00'),
(19, 'STU-018', 'Aditya', 'Chauhan', 'aditya.chauhan@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23EC018', 'Electronics & Communication', 3, '+91 98266 77889', 'Embedded Linux systems and automotive CAN bus protocols.', 'student', '2026-10-12 14:30:00'),
(20, 'STU-019', 'Rhea', 'Bhatt', 'rhea.bhatt@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '22ME058', 'Mechanical Engineering', 5, '+91 98267 88990', 'Thermal heat exchangers and industrial 3D additive manufacturing.', 'student', '2026-10-12 14:45:00'),
(21, 'STU-020', 'Manav', 'Dave', 'manav.dave@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '21CL045', 'Civil Engineering', 7, '+91 98268 99001', 'GIS spatial mapping and urban transit infrastructure.', 'student', '2026-10-12 15:00:00'),
(22, 'STU-021', 'Ishita', 'Parikh', 'ishita.parikh@charusat.edu.in', '$2y$10$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', '23EE037', 'Electrical Engineering', 3, '+91 98269 00112', 'Power electronics, battery management, and inverter design.', 'student', '2026-10-12 15:15:00');

