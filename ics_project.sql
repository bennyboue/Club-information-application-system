-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2025 at 10:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ics_project`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity`
--

CREATE TABLE `admin_activity` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target` varchar(255) NOT NULL,
  `performed_by` varchar(255) NOT NULL,
  `action_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `announcement_type` enum('general','club','event','urgent','maintenance') DEFAULT 'general',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `is_public` tinyint(1) DEFAULT 1,
  `publish_date` datetime DEFAULT NULL,
  `expire_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notification_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `initials` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `name`, `initials`, `description`, `created_by`, `created_at`) VALUES
(1, 'Chess Club', 'CC', 'Strategic thinking and competitive chess games. Join us for tournaments, training sessions, and intellectual challenges that sharpen your mind.', NULL, '2025-06-17 18:20:45'),
(2, 'Scrabble Society', 'SS', 'Word enthusiasts unite! Expand your vocabulary while enjoying competitive word games and linguistic challenges with fellow word lovers.', NULL, '2025-06-17 18:20:45'),
(3, 'Mathematics Olympiad Club', 'MOC', 'Advanced mathematical problem solving and competition preparation. Perfect for students who love numbers and logical reasoning.', NULL, '2025-06-17 18:20:45'),
(4, 'Debate & Critical Thinking Club', 'DCTC', 'Develop your argumentation skills, public speaking abilities, and critical thinking through structured debates and discussions.', NULL, '2025-06-17 18:20:45'),
(5, 'Painting & Visual Arts Club', 'PVAC', 'Express creativity through various painting techniques, color theory, and visual storytelling. All skill levels welcome!', NULL, '2025-06-17 18:20:45'),
(6, 'Sculpting & 3D Arts Club', 'S3AC', 'Work with clay, wood, and modern materials to create three-dimensional masterpieces. Learn traditional and contemporary sculpting techniques.', NULL, '2025-06-17 18:20:45'),
(7, 'Music Harmony Club', 'MHC', 'Vocal and instrumental music ensemble. From classical to contemporary, join us in creating beautiful harmonies and musical performances.', NULL, '2025-06-17 18:20:45'),
(8, 'Creative Writing & Literature Club', 'CWLC', 'Poets, storytellers, and literature enthusiasts gather to share original works, analyze great literature, and inspire creativity.', NULL, '2025-06-17 18:20:45'),
(9, 'Physics & Astronomy Club', 'PAC', 'Explore the mysteries of the universe, conduct experiments, and observe celestial phenomena through telescopes and scientific inquiry.', NULL, '2025-06-17 18:20:45'),
(10, 'Chemistry Lab Enthusiasts', 'CLE', 'Safe chemical experiments, molecular model building, and understanding the building blocks of matter in hands-on laboratory sessions.', NULL, '2025-06-17 18:20:45'),
(11, 'Biology & Environmental Science Club', 'BESC', 'Study living organisms, ecosystems, and environmental conservation through field trips, lab work, and nature observation.', NULL, '2025-06-17 18:20:45'),
(12, 'Robotics & Engineering Club', 'REC', 'Build robots, learn programming, and solve engineering challenges using technology, coding, and innovative problem-solving approaches.', NULL, '2025-06-17 18:20:45'),
(13, 'Football Champions Club', 'FCC', 'Competitive football training, matches, and team building. Develop athletic skills, teamwork, and sportsmanship on the field.', NULL, '2025-06-17 18:20:45'),
(14, 'Basketball Elite Club', 'BEC', 'Shooting hoops, strategic plays, and competitive basketball. Join our team for training sessions, tournaments, and skill development.', NULL, '2025-06-17 18:20:45'),
(15, 'Track & Field Athletics Club', 'TFAC', 'Running, jumping, throwing events and athletic competitions. Train for various track and field events with professional coaching.', NULL, '2025-06-17 18:20:45'),
(16, 'Swimming & Aquatics Club', 'SAC', 'Competitive swimming, water safety, and aquatic sports. Improve your swimming techniques and participate in inter-school competitions.', NULL, '2025-06-17 18:20:45'),
(17, 'Gamers Party', 'VGP', 'A haven for all kinds of gamers', 48, '2025-06-30 15:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `club_managers`
--

CREATE TABLE `club_managers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `assigned_date` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `club_managers`
--

INSERT INTO `club_managers` (`id`, `user_id`, `club_id`, `assigned_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 49, 17, '2025-06-30 18:17:34', 'active', '2025-06-30 15:17:34', '2025-06-30 15:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `memberships`
--

CREATE TABLE `memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `club_id`, `status`, `joined_at`) VALUES
(12, 43, 9, 'approved', '2025-06-18 05:56:12'),
(13, 43, 4, 'approved', '2025-06-20 06:29:24'),
(14, 49, 7, 'approved', '2025-06-24 11:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('announcement','event','reminder','alert') DEFAULT 'announcement',
  `target_audience` enum('students','club_managers','all') DEFAULT 'all',
  `club_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_announcements`
--

CREATE TABLE `system_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` enum('general','urgent','maintenance','event') DEFAULT 'general',
  `target_audience` enum('all','students','club_managers','admins') DEFAULT 'all',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `publish_date` datetime DEFAULT NULL,
  `expire_date` datetime DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'school_name', 'Strathmore University'),
(2, 'max_clubs_per_student', '3'),
(3, 'enable_registration', '1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `school_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','club_manager','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `surname`, `email`, `school_id`, `password`, `role`, `created_at`) VALUES
(43, 'Kiki', 'Muche', 'mwiberikian6@gmail.com', '1630', '$2y$10$UcbST5Tcrdd7YhfQeB9z/e.QqF6.Kntn/wnSkzBoj/Dp8lQenK8ji', 'student', '2025-06-17 17:45:33'),
(45, 'Alvin', 'Muche', 'mwiberikia@gmail.com', '1111', '$2y$10$cZLK/NvsABfMJux81EDmpeTu2NUoLCxkS5Gf6MbFQa1un/ov4NmdG', '', '2025-06-18 06:19:22'),
(47, 'Steve', 'Rogers', 'steve1@gmail.com', '9745', '$2y$10$B.hP09lZMlM7UAlDLR06Muxr.RKyYF29CV1nIuNwn0wtjbhGPYVSG', 'club_manager', '2025-06-24 10:31:47'),
(48, 'Bruce', 'Wayne', 'bruce@gmail.com', '163035', '$2y$10$tGN.0YHc3ETbhkgDKgH5J.ig/4aDjqfuktwo372u9arWqy5UFOG.6', 'admin', '2025-06-24 10:37:16'),
(49, 'Joshua', 'Adalo', 'Josh@gmail.com', '689848', '$2y$10$dTsLcezzHgwNO/0tZZfnJuq.awpeoD5N..kEDBnEnKxoH9O9uaT36', 'club_manager', '2025-06-24 11:05:57'),
(50, 'Leroy', 'Ndiao', 'ndiao@gmail.com', '168045', '$2y$10$uNxJWJKg4QPvrqjsFJBs1.N1KoxCiou5DgZikLA/0PpWiGCP6uO9i', 'student', '2025-06-30 17:25:30');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_club_id` (`club_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_publish_date` (`publish_date`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_announcement_type` (`announcement_type`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `initials` (`initials`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `club_managers`
--
ALTER TABLE `club_managers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_club_manager` (`club_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_club_managers_status` (`status`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `memberships`
--
ALTER TABLE `memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`club_id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `idx_memberships_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `system_announcements`
--
ALTER TABLE `system_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_publish_date` (`publish_date`),
  ADD KEY `idx_target_audience` (`target_audience`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `school_id` (`school_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification` (`user_id`,`notification_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity`
--
ALTER TABLE `admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `club_managers`
--
ALTER TABLE `club_managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_announcements`
--
ALTER TABLE `system_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_3` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_4` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clubs`
--
ALTER TABLE `clubs`
  ADD CONSTRAINT `clubs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `club_managers`
--
ALTER TABLE `club_managers`
  ADD CONSTRAINT `club_managers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `club_managers_ibfk_2` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memberships`
--
ALTER TABLE `memberships`
  ADD CONSTRAINT `memberships_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memberships_ibfk_2` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_announcements`
--
ALTER TABLE `system_announcements`
  ADD CONSTRAINT `system_announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `system_announcements_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_notifications_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
