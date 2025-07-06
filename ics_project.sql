-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 06, 2025 at 06:59 PM
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

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `club_id`, `created_by`, `announcement_type`, `priority`, `status`, `is_public`, `publish_date`, `expire_date`, `created_at`, `updated_at`, `notification_id`) VALUES
(7, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: SYSTEM SHUTDOWN AT 12AM 2ND JULY.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 15:36:35', '2025-07-01 15:36:35', 1),
(8, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [13th July] at [4pm] in [STMB 5th Floor]. Please come prepared with any materials mentioned in previous communications.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 23:07:09', '2025-07-01 23:07:09', 2),
(9, 'New Event Announcement', 'We are excited to announce our upcoming event: [EVENT_NAME] on [DATE]. Join us for [BRIEF_DESCRIPTION]. More details to follow.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 23:08:07', '2025-07-01 23:08:07', 3),
(10, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 23:47:33', '2025-07-01 23:47:33', 4),
(11, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 23:49:54', '2025-07-01 23:49:54', 5),
(12, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-01 23:51:35', '2025-07-01 23:51:35', 6),
(13, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: [Ray]', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-02 13:40:14', '2025-07-02 13:40:14', 7),
(14, 'Ray', 'rkl coming', 13, 54, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-02 13:42:19', '2025-07-02 13:42:19', NULL),
(16, 'HAPPY BDAY HM', 'Haapppyybdaay Hope!!!', 1, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-03 13:13:04', '2025-07-03 13:13:04', 9),
(17, 'See', 'See', 15, 47, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-03 13:49:04', '2025-07-03 13:49:04', NULL),
(18, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: [DETAILS]', NULL, 48, 'general', 'medium', 'draft', 1, NULL, NULL, '2025-07-04 06:59:46', '2025-07-04 06:59:46', 10);

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `patron_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `initials` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `patron_id`, `name`, `initials`, `description`, `created_by`, `created_at`) VALUES
(1, 47, 'Chess Club', 'CC', 'Strategic thinking and competitive chess games. Join us for tournaments, training sessions, and intellectual challenges that sharpen your mind.', 48, '2025-06-17 18:20:45'),
(2, 57, 'Scrabble Society', 'SS', 'Word enthusiasts unite! Expand your vocabulary while enjoying competitive word games and linguistic challenges with fellow word lovers.', 48, '2025-06-17 18:20:45'),
(3, 54, 'Mathematics Olympiad Club', 'MOC', 'Advanced mathematical problem solving and competition preparation. Perfect for students who love numbers and logical reasoning.', 48, '2025-06-17 18:20:45'),
(4, 48, 'Debate & Critical Thinking Club', 'DCTC', 'Develop your argumentation skills, public speaking abilities, and critical thinking through structured debates and discussions.', 48, '2025-06-17 18:20:45'),
(5, 48, 'Painting & Visual Arts Club', 'PVAC', 'Express creativity through various painting techniques, color theory, and visual storytelling. All skill levels welcome!', 48, '2025-06-17 18:20:45'),
(6, 48, 'Sculpting & 3D Arts Club', 'S3AC', 'Work with clay, wood, and modern materials to create three-dimensional masterpieces. Learn traditional and contemporary sculpting techniques.', 48, '2025-06-17 18:20:45'),
(7, 48, 'Music Harmony Club', 'MHC', 'Vocal and instrumental music ensemble. From classical to contemporary, join us in creating beautiful harmonies and musical performances.', 48, '2025-06-17 18:20:45'),
(8, 48, 'Creative Writing & Literature Club', 'CWLC', 'Poets, storytellers, and literature enthusiasts gather to share original works, analyze great literature, and inspire creativity.', 48, '2025-06-17 18:20:45'),
(9, 48, 'Physics & Astronomy Club', 'PAC', 'Explore the mysteries of the universe, conduct experiments, and observe celestial phenomena through telescopes and scientific inquiry.', 48, '2025-06-17 18:20:45'),
(10, 48, 'Chemistry Lab Enthusiasts', 'CLE', 'Safe chemical experiments, molecular model building, and understanding the building blocks of matter in hands-on laboratory sessions.', 48, '2025-06-17 18:20:45'),
(11, 48, 'Biology & Environmental Science Club', 'BESC', 'Study living organisms, ecosystems, and environmental conservation through field trips, lab work, and nature observation.', 48, '2025-06-17 18:20:45'),
(12, 48, 'Robotics & Engineering Club', 'REC', 'Build robots, learn programming, and solve engineering challenges using technology, coding, and innovative problem-solving approaches.', 48, '2025-06-17 18:20:45'),
(13, 54, 'Football Champions Club', 'FCC', 'Competitive football training, matches, and team building. Develop athletic skills, teamwork, and sportsmanship on the field.', 48, '2025-06-17 18:20:45'),
(14, 48, 'Basketball Elite Club', 'BEC', 'Shooting hoops, strategic plays, and competitive basketball. Join our team for training sessions, tournaments, and skill development.', 48, '2025-06-17 18:20:45'),
(15, 47, 'Track & Field Athletics Club', 'TFAC', 'Running, jumping, throwing events and athletic competitions. Train for various track and field events with professional coaching.', 48, '2025-06-17 18:20:45'),
(16, 48, 'Swimming & Aquatics Club', 'SAC', 'Competitive swimming, water safety, and aquatic sports. Improve your swimming techniques and participate in inter-school competitions.', 48, '2025-06-17 18:20:45');

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
  `is_patron` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `club_managers`
--

INSERT INTO `club_managers` (`id`, `user_id`, `club_id`, `assigned_date`, `status`, `is_patron`, `created_at`, `updated_at`) VALUES
(2, 47, 15, '2025-07-02 03:05:06', 'active', 1, '2025-07-02 00:05:06', '2025-07-02 00:05:06'),
(3, 54, 13, '2025-07-02 16:38:38', 'active', 1, '2025-07-02 13:38:38', '2025-07-02 13:38:38'),
(4, 57, 2, '2025-07-04 09:51:53', 'active', 1, '2025-07-04 06:51:53', '2025-07-04 06:51:53');

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

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `club_id`, `title`, `description`, `event_date`, `created_at`) VALUES
(12, 13, 'Ray', 'Rkl coming', '2025-12-09', '2025-07-02 14:10:44'),
(13, 15, 'Stacey', 'Mwiberiiii!!!', '2025-07-15', '2025-07-06 11:59:04');

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
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `club_id`, `status`, `joined_at`) VALUES
(12, 43, 9, 'approved', '2025-06-18 05:56:12'),
(13, 43, 4, 'approved', '2025-06-20 06:29:24'),
(14, 49, 7, 'approved', '2025-06-24 11:06:29'),
(15, 51, 4, 'approved', '2025-06-30 22:45:53'),
(16, 51, 15, 'pending', '2025-07-02 00:28:25'),
(17, 56, 1, 'pending', '2025-07-03 13:04:02'),
(18, 51, 2, 'pending', '2025-07-04 07:02:18'),
(19, 58, 7, 'pending', '2025-07-05 14:17:48'),
(24, 58, 15, 'pending', '2025-07-06 13:00:46');

-- --------------------------------------------------------

--
-- Table structure for table `membership_requests`
--

CREATE TABLE `membership_requests` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_requests`
--

INSERT INTO `membership_requests` (`id`, `club_id`, `user_id`, `status`, `created_at`) VALUES
(1, 15, 58, 'approved', '2025-07-06 12:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('announcement','reminder','alert','system') DEFAULT 'announcement',
  `priority` enum('normal','high','urgent') DEFAULT 'normal',
  `target_audience` enum('all','students','club_managers','club_members','admins') DEFAULT 'all',
  `club_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_immediate` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `priority`, `target_audience`, `club_id`, `created_by`, `created_at`, `expires_at`, `is_immediate`, `sent_at`, `is_active`) VALUES
(1, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: SYSTEM SHUTDOWN AT 12AM 2ND JULY.', 'announcement', 'high', 'all', NULL, 48, '2025-07-01 15:36:35', '2025-07-31 15:36:35', 1, '2025-07-01 15:36:35', 1),
(2, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [13th July] at [4pm] in [STMB 5th Floor]. Please come prepared with any materials mentioned in previous communications.', 'announcement', 'high', 'students', NULL, 48, '2025-07-01 23:07:09', '2025-07-31 23:07:09', 0, NULL, 1),
(3, 'New Event Announcement', 'We are excited to announce our upcoming event: [EVENT_NAME] on [DATE]. Join us for [BRIEF_DESCRIPTION]. More details to follow.', 'announcement', 'urgent', 'club_managers', NULL, 48, '2025-07-01 23:08:07', '2025-07-31 23:08:07', 0, NULL, 1),
(4, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', 'announcement', 'normal', 'club_managers', NULL, 48, '2025-07-01 23:47:33', '2025-07-31 23:47:33', 0, NULL, 1),
(5, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', 'announcement', 'normal', 'club_managers', NULL, 48, '2025-07-01 23:49:54', '2025-07-31 23:49:54', 0, NULL, 1),
(6, 'Meeting Reminder', 'This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.', 'announcement', 'normal', 'club_managers', NULL, 48, '2025-07-01 23:51:35', '2025-07-31 23:51:35', 0, NULL, 1),
(7, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: [Ray]', 'announcement', 'high', 'all', NULL, 48, '2025-07-02 13:40:14', '2025-08-01 13:40:14', 1, '2025-07-02 13:40:14', 1),
(9, 'HAPPY BDAY HM', 'Haapppyybdaay Hope!!!', 'announcement', 'high', 'students', 1, 48, '2025-07-03 13:13:04', '2025-08-02 13:13:04', 1, '2025-07-03 13:13:04', 1),
(10, 'Urgent Notice', 'URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: [DETAILS]', 'announcement', 'urgent', 'club_managers', NULL, 48, '2025-07-04 06:59:46', '2025-08-03 06:59:46', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `notification_id`, `admin_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 48, 'created', '{\"count\":8,\"users\":[{\"id\":43,\"username\":\"Kiki\",\"email\":\"mwiberikian6@gmail.com\"},{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":48,\"username\":\"Bruce\",\"email\":\"bruce@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":50,\"username\":\"Leroy\",\"email\":\"ndiao@gmail.com\"},{\"id\":51,\"username\":\"Kian\",\"email\":\"mwiberikian1@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"},{\"id\":55,\"username\":\"Reed\",\"email\":\"mwiberikian2@gmail.com\"}]}', '2025-07-01 15:36:35'),
(2, 2, 48, 'created', '{\"count\":3,\"users\":[{\"id\":43,\"username\":\"Kiki\",\"email\":\"mwiberikian6@gmail.com\"},{\"id\":50,\"username\":\"Leroy\",\"email\":\"ndiao@gmail.com\"},{\"id\":51,\"username\":\"Kian\",\"email\":\"mwiberikian1@gmail.com\"}]}', '2025-07-01 23:07:09'),
(3, 3, 48, 'created', '{\"count\":3,\"users\":[{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"}]}', '2025-07-01 23:08:07'),
(4, 4, 48, 'created', '{\"count\":3,\"users\":[{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"}]}', '2025-07-01 23:47:33'),
(5, 5, 48, 'created', '{\"count\":3,\"users\":[{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"}]}', '2025-07-01 23:49:54'),
(6, 6, 48, 'created', '{\"count\":3,\"users\":[{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"}]}', '2025-07-01 23:51:35'),
(7, 7, 48, 'created', '{\"count\":8,\"users\":[{\"id\":43,\"username\":\"Kiki\",\"email\":\"mwiberikian6@gmail.com\"},{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":48,\"username\":\"Bruce\",\"email\":\"bruce@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":50,\"username\":\"Leroy\",\"email\":\"ndiao@gmail.com\"},{\"id\":51,\"username\":\"Kian\",\"email\":\"mwiberikian1@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"},{\"id\":55,\"username\":\"Reed\",\"email\":\"mwiberikian2@gmail.com\"}]}', '2025-07-02 13:40:14'),
(8, 9, 48, 'created', '{\"count\":4,\"users\":[{\"id\":43,\"username\":\"Kiki\",\"email\":\"mwiberikian6@gmail.com\"},{\"id\":50,\"username\":\"Leroy\",\"email\":\"ndiao@gmail.com\"},{\"id\":51,\"username\":\"Kian\",\"email\":\"mwiberikian1@gmail.com\"},{\"id\":56,\"username\":\"Hope\",\"email\":\"hope@gmail.com\"}]}', '2025-07-03 13:13:04'),
(9, 10, 48, 'created', '{\"count\":4,\"users\":[{\"id\":47,\"username\":\"Steve\",\"email\":\"steve1@gmail.com\"},{\"id\":49,\"username\":\"Joshua\",\"email\":\"Josh@gmail.com\"},{\"id\":54,\"username\":\"Clark\",\"email\":\"mwiberikian3@gmail.com\"},{\"id\":57,\"username\":\"Benjamin\",\"email\":\"benja@gmail.com\"}]}', '2025-07-04 06:59:46');

-- --------------------------------------------------------

--
-- Table structure for table `patron_requests`
--

CREATE TABLE `patron_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `request_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(47, 'Steve', 'Rogers', 'steve1@gmail.com', '9745', '$2y$10$B.hP09lZMlM7UAlDLR06Muxr.RKyYF29CV1nIuNwn0wtjbhGPYVSG', 'club_manager', '2025-06-24 10:31:47'),
(48, 'Bruce', 'Wayne', 'bruce@gmail.com', '163035', '$2y$10$tGN.0YHc3ETbhkgDKgH5J.ig/4aDjqfuktwo372u9arWqy5UFOG.6', 'admin', '2025-06-24 10:37:16'),
(49, 'Joshua', 'Adalo', 'Josh@gmail.com', '689848', '$2y$10$dTsLcezzHgwNO/0tZZfnJuq.awpeoD5N..kEDBnEnKxoH9O9uaT36', 'club_manager', '2025-06-24 11:05:57'),
(50, 'Leroy', 'Ndiao', 'ndiao@gmail.com', '168045', '$2y$10$uNxJWJKg4QPvrqjsFJBs1.N1KoxCiou5DgZikLA/0PpWiGCP6uO9i', 'student', '2025-06-30 17:25:30'),
(51, 'Kian', 'Muchemi', 'mwiberikian1@gmail.com', '1365', '$2y$10$N3TsWAM2hfgHKnu7lySy1uSmKz1n9nvhhXD4BR8bG/XCDLjR9DHeq', 'student', '2025-06-30 22:44:31'),
(54, 'Clark', 'Kent', 'mwiberikian3@gmail.com', '4422', '$2y$10$UuPJdFdusoQto13JoZ1zMudE7AKFUXV6cfstjSckvxfL.mbPZ8T.C', 'club_manager', '2025-06-30 22:54:37'),
(55, 'Reed', 'Richards', 'mwiberikian2@gmail.com', '1230', '$2y$10$xRpXs8ONNgLYBL68CPud3uRRU/2uFOHF5q3F4wOZUNDqJ/uldXQqu', 'admin', '2025-06-30 23:01:01'),
(56, 'Hope', 'Muthoni', 'hope@gmail.com', '1243', '$2y$10$GG0aKDwZqtFEffUPeyGPY.AliUtKu8pdlkBhlSyGkrm08Y8QWNrvm', 'student', '2025-07-03 13:00:25'),
(57, 'Benjamin', 'Mb', 'benja@gmail.com', '9090', '$2y$10$/BBvcNzXViS6amQG4DKFp.Ji8KTlMOBg1HfzWss.oFwMNW5vvJMSy', 'club_manager', '2025-07-04 06:50:26'),
(58, 'Stacey', 'Mwiberi', 'Staceyjudy75@gmail.com', '0346', '$2y$10$mESz7rdt1rAOT5srKFX.VO/Vl2X92bi.c1OtBjE0DusBeto0aC2DG', 'student', '2025-07-05 14:12:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `notification_id`, `read_at`, `created_at`, `status`, `is_read`) VALUES
(1, 43, 1, '2025-07-01 22:55:44', '2025-07-01 15:36:35', 'unread', 1),
(2, 47, 1, '2025-07-01 22:58:23', '2025-07-01 15:36:35', 'unread', 1),
(3, 48, 1, NULL, '2025-07-01 15:36:35', 'unread', 0),
(4, 49, 1, NULL, '2025-07-01 15:36:35', 'unread', 0),
(5, 50, 1, NULL, '2025-07-01 15:36:35', 'unread', 0),
(6, 51, 1, '2025-07-02 00:28:44', '2025-07-01 15:36:35', 'unread', 1),
(7, 54, 1, NULL, '2025-07-01 15:36:35', 'unread', 0),
(8, 55, 1, NULL, '2025-07-01 15:36:35', 'unread', 0),
(9, 43, 2, NULL, '2025-07-01 23:07:09', 'unread', 0),
(10, 50, 2, NULL, '2025-07-01 23:07:09', 'unread', 0),
(11, 51, 2, '2025-07-02 13:40:52', '2025-07-01 23:07:09', 'unread', 1),
(12, 47, 3, '2025-07-01 23:56:42', '2025-07-01 23:08:07', 'unread', 1),
(13, 49, 3, NULL, '2025-07-01 23:08:07', 'unread', 0),
(14, 54, 3, NULL, '2025-07-01 23:08:07', 'unread', 0),
(15, 47, 4, '2025-07-01 23:56:41', '2025-07-01 23:47:33', 'unread', 1),
(16, 49, 4, NULL, '2025-07-01 23:47:33', 'unread', 0),
(17, 54, 4, NULL, '2025-07-01 23:47:33', 'unread', 0),
(18, 47, 5, '2025-07-01 23:56:39', '2025-07-01 23:49:54', 'unread', 1),
(19, 49, 5, NULL, '2025-07-01 23:49:54', 'unread', 0),
(20, 54, 5, NULL, '2025-07-01 23:49:54', 'unread', 0),
(21, 47, 6, '2025-07-01 23:56:06', '2025-07-01 23:51:35', 'unread', 1),
(22, 49, 6, NULL, '2025-07-01 23:51:35', 'unread', 0),
(23, 54, 6, NULL, '2025-07-01 23:51:35', 'unread', 0),
(24, 43, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(25, 47, 7, '2025-07-05 14:21:40', '2025-07-02 13:40:14', 'unread', 1),
(26, 48, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(27, 49, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(28, 50, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(29, 51, 7, '2025-07-04 07:02:31', '2025-07-02 13:40:14', 'unread', 1),
(30, 54, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(31, 55, 7, NULL, '2025-07-02 13:40:14', 'unread', 0),
(32, 43, 9, NULL, '2025-07-03 13:13:04', 'unread', 0),
(33, 50, 9, NULL, '2025-07-03 13:13:04', 'unread', 0),
(34, 51, 9, '2025-07-04 06:53:39', '2025-07-03 13:13:04', 'unread', 1),
(35, 56, 9, NULL, '2025-07-03 13:13:04', 'unread', 0),
(36, 47, 10, '2025-07-05 14:21:38', '2025-07-04 06:59:46', 'unread', 1),
(37, 49, 10, NULL, '2025-07-04 06:59:46', 'unread', 0),
(38, 54, 10, NULL, '2025-07-04 06:59:46', 'unread', 0),
(39, 57, 10, NULL, '2025-07-04 06:59:46', 'unread', 0);

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
  ADD KEY `announcements_ibfk_4` (`notification_id`);

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
-- Indexes for table `membership_requests`
--
ALTER TABLE `membership_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_club_id` (`club_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_target_audience` (`target_audience`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `patron_requests`
--
ALTER TABLE `patron_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `club_id` (`club_id`);

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
  ADD KEY `idx_status` (`status`),
  ADD KEY `user_notifications_ibfk_2` (`notification_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `club_managers`
--
ALTER TABLE `club_managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `membership_requests`
--
ALTER TABLE `membership_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `patron_requests`
--
ALTER TABLE `patron_requests`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_3` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `membership_requests`
--
ALTER TABLE `membership_requests`
  ADD CONSTRAINT `membership_requests_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `membership_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patron_requests`
--
ALTER TABLE `patron_requests`
  ADD CONSTRAINT `patron_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patron_requests_ibfk_2` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
