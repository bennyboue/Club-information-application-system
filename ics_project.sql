-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2025 at 08:40 PM
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
(16, 'Swimming & Aquatics Club', 'SAC', 'Competitive swimming, water safety, and aquatic sports. Improve your swimming techniques and participate in inter-school competitions.', NULL, '2025-06-17 18:20:45');

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
-- Table structure for table `memberships`
--

CREATE TABLE `memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `role` enum('student','club_patron','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------

--
-- Table for announcements
--
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `surname`, `email`, `school_id`, `password`, `role`, `created_at`) VALUES
(15, 'Kian', 'Mwiberi', 'mwiberikian1@gmail.com', '159377', '$2y$10$wOgFdcU8oVWpBwyD65K1y.jimT9JUm7BeeMfGYWnmTS/uMVIyVvwi', 'admin', '2025-06-15 20:46:29'),
(35, 'Mwiberi', 'Stacey', 'Staceyjudy75@gmail.com', '12345', '$2y$10$X7BXjG.PUEa/5Dsr5Bw63.Fi3p6HgFyQWy9XoZdbKzkHjkQD4W4/a', 'student', '2025-06-15 22:34:53'),
(36, 'K', 'Johnson', 'mwiberikian5@gmail.com', '1234', '$2y$10$7gtXXTL2PHeLA6LeZkWB1.KJl1pVYaFy2AHSJs.zOWQ8nQFe7s9Ry', 'club_patron', '2025-06-15 23:08:18'),
(37, 'john_student', 'Doe', 'john.doe@school.edu', '54321', '$2y$10$example.hash.for.password123', 'student', '2025-06-15 23:52:00'),
(38, 'mary_patron', 'Smith', 'mary.smith@school.edu', '98765', '$2y$10$example.hash.for.password456', 'club_patron', '2025-06-15 23:52:00'),
(39, 'admin_wilson', 'Wilson', 'admin.wilson@school.edu', '11111', '$2y$10$example.hash.for.password789', 'admin', '2025-06-15 23:52:00'),
(40, 'Steve', 'Pat', 'mwiberikian9@gmail.com', '2345', '$2y$10$6zJ39811pnk.SHw3piXcmuT2gft1zfJNWAh5AmJqj8YC.F.riaw2q', 'club_patron', '2025-06-15 23:53:41'),
(43, 'Kiki', 'Muche', 'mwiberikian6@gmail.com', '1630', '$2y$10$UcbST5Tcrdd7YhfQeB9z/e.QqF6.Kntn/wnSkzBoj/Dp8lQenK8ji', 'student', '2025-06-17 17:45:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `initials` (`initials`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `memberships`
--
ALTER TABLE `memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`club_id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `school_id` (`school_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clubs`
--
ALTER TABLE `clubs`
  ADD CONSTRAINT `clubs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
