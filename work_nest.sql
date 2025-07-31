-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 30, 2025 at 03:34 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `work_nest`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int NOT NULL,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `manager_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `manager_id`, `created_at`, `updated_at`) VALUES
(1, 'Ban phân tích & thiết kế', 'Phụ trách phân tích và thiết kế cho dự án', 3, '2025-07-30 21:44:38', '2025-07-30 21:53:57'),
(2, 'Ban kỹ thuật phần mềm', 'Phụ trách kỹ thuật, phát triển hệ thống cho dự án', 4, '2025-07-30 21:45:51', '2025-07-30 21:53:54'),
(3, 'Ban kiểm thử', 'Phụ trách kiểm thử hệ thống và bảo trì cho dự án', 5, '2025-07-30 21:46:25', '2025-07-30 21:53:51'),
(4, 'Ban dịch vụ & khách hàng', 'Phụ trách kết nối, trao đổi dịch vụ, chăm sóc khách hàng', 6, '2025-07-30 21:47:15', '2025-07-30 21:53:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT '#',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status_id` tinyint(1) NOT NULL DEFAULT '1',
  `priority` tinyint(1) NOT NULL DEFAULT '2',
  `manager_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `start_date`, `end_date`, `status_id`, `priority`, `manager_id`, `created_at`, `updated_at`) VALUES
(1, 'Xây dựng ứng dụng quản lý chi tiêu', 'Mô tả xây dựng ứng dụng quản lý chi tiêu', '2025-08-01', '2025-09-01', 1, 2, 2, '2025-07-30 21:50:01', '2025-07-30 21:53:21'),
(2, 'Xây dựng hệ thống quản lý đặt tour du lịch', 'Mô tả xây dựng hệ thống quản lý đặt tour du lịch', '2025-08-05', '2025-09-05', 1, 2, 2, '2025-07-30 21:51:14', '2025-07-30 21:53:25'),
(3, 'Xây dựng hệ thống quản lý nhân viên', 'Mô tả xây dựng hệ thống quản lý nhân viên', '2025-08-10', '2025-09-10', 1, 2, 2, '2025-07-30 21:52:01', '2025-07-30 21:53:29'),
(4, 'Xây dựng hệ thống bán hàng', 'Mô tả xây dựng hệ thống bán hàng', '2025-08-15', '2025-09-15', 1, 2, 2, '2025-07-30 21:52:32', '2025-07-30 21:52:57');

-- --------------------------------------------------------

--
-- Table structure for table `project_departments`
--

CREATE TABLE `project_departments` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `department_id` int NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_departments`
--

INSERT INTO `project_departments` (`id`, `project_id`, `department_id`, `assigned_at`) VALUES
(17, 4, 4, '2025-07-30 21:52:57'),
(18, 4, 3, '2025-07-30 21:52:57'),
(19, 4, 2, '2025-07-30 21:52:57'),
(20, 4, 1, '2025-07-30 21:52:57'),
(21, 1, 4, '2025-07-30 21:53:21'),
(22, 1, 3, '2025-07-30 21:53:21'),
(23, 1, 2, '2025-07-30 21:53:21'),
(24, 1, 1, '2025-07-30 21:53:21'),
(25, 2, 4, '2025-07-30 21:53:25'),
(26, 2, 3, '2025-07-30 21:53:25'),
(27, 2, 2, '2025-07-30 21:53:25'),
(28, 2, 1, '2025-07-30 21:53:25'),
(29, 3, 4, '2025-07-30 21:53:29'),
(30, 3, 3, '2025-07-30 21:53:29'),
(31, 3, 2, '2025-07-30 21:53:29'),
(32, 3, 1, '2025-07-30 21:53:29');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `report_type` enum('daily','weekly','monthly','project','task') NOT NULL,
  `task_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `project_id` int NOT NULL,
  `department_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `assigned_by` int NOT NULL,
  `status_id` tinyint(1) NOT NULL DEFAULT '1',
  `priority` tinyint(1) NOT NULL DEFAULT '2',
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `progress` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','project_manager','department_manager','staff') NOT NULL DEFAULT 'staff',
  `department_id` int DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'assets/images/avatar-default.png',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `department_id`, `phone`, `avatar`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$yREdQm2QDUQIbCMuNiHI1ePbFS1hJLNSVwM4AJDf0624p..Uqgn1m', 'admin', NULL, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 21:55:09', NULL),
(2, 'Hoàng Quản lý', 'manager@gmail.com', '$2y$10$yREdQm2QDUQIbCMuNiHI1ePbFS1hJLNSVwM4AJDf0624p..Uqgn1m', 'project_manager', NULL, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 22:34:04', NULL),
(3, 'Lê Trưởng phòng', 'department1@gmail.com', '$2y$10$yREdQm2QDUQIbCMuNiHI1ePbFS1hJLNSVwM4AJDf0624p..Uqgn1m', 'department_manager', 1, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 21:47:38', NULL),
(4, 'Nguyễn Trưởng Phòng', 'department2@gmail.com', '$2y$10$yREdQm2QDUQIbCMuNiHI1ePbFS1hJLNSVwM4AJDf0624p..Uqgn1m', 'department_manager', 2, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 21:47:45', NULL),
(5, 'Trần Trưởng Phòng', 'department3@gmail.com', '$2y$10$Vxe9NadwDyDimuplKvgsNOxlTf6G5r8om8PbusMhwwFb7ChHTm58i', 'department_manager', 3, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 21:47:51', NULL),
(6, 'Vũ Trưởng Phòng', 'department4@gmail.com', '$2y$10$ymNJfynVSqwXYJ5zR2wWcOhXDiJUhwA6nHKXqN9YlnCsu3HJuaR1K', 'department_manager', 4, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 19:35:00', '2025-07-30 21:47:57', NULL),
(7, 'Nguyễn Văn A', 'staff1@gmail.com', '$2y$10$cwJzjqQeCfbGfiN67CSaUOyFDXixpqKKRd3oqcDWe6xKGG39cNXyG', 'staff', 1, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:09:57', '2025-07-30 21:48:08', NULL),
(8, 'Nguyễn Văn B', 'staff2@gmail.com', '$2y$10$UiOCwY8m3ghf1x19ACLx9eoYICEuI9d/bZkw7Ju.kxS07P3Tub5x2', 'staff', 1, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:10:27', '2025-07-30 21:48:14', NULL),
(9, 'Nguyễn Văn C', 'staff3@gmail.com', '$2y$10$kiVxQ/vGdtaWwDAfQdpZg.ZHZpXZuHnh/MvnPSGQX50E0/jw4mKR6', 'staff', 2, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:11:39', '2025-07-30 21:48:21', NULL),
(10, 'Nguyễn Văn D', 'staff4@gmail.com', '$2y$10$ompjfrxeETvb9fU4oorPrOkMchRHj7wb1Svdir8Nf6dhj2Umlkbt6', 'staff', 2, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:12:08', '2025-07-30 21:48:26', NULL),
(11, 'Nguyễn Văn E', 'staff5@gmail.com', '$2y$10$Hl2ARnPj6JKfGgG.J5.sVebpB6m.zbU.U93QIULnrl2uqNNzQ1kaC', 'staff', 3, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:12:45', '2025-07-30 21:48:35', NULL),
(12, 'Nguyễn Văn G', 'staff6@gmail.com', '$2y$10$3fNzlj1XDWhLuMeQNKrgAuEIICT7eBTMgohbiBm7re8SesBctuqPC', 'staff', 3, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:13:37', '2025-07-30 21:48:44', NULL),
(13, 'Nguyễn Văn H', 'staff7@gmail.com', '$2y$10$czhaY6o.kkQbbwDj70IIIe1erXJUkBPEMu8SxXvCmiKgZXQ82z5QS', 'staff', 4, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:14:12', '2025-07-30 21:48:51', NULL),
(14, 'Nguyễn Văn K', 'staff8@gmail.com', '$2y$10$JD9AnIqHoYqM/6qr4m6IzuAJl3srlg9SU0gyBiRUICobDejQBtdFK', 'staff', 4, '0123456789', 'assets/images/avatar-default.png', '2025-05-17 23:14:55', '2025-07-30 21:48:58', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `project_departments`
--
ALTER TABLE `project_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_id_2` (`project_id`,`department_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `report_type` (`report_type`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_departments`
--
ALTER TABLE `project_departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `project_departments`
--
ALTER TABLE `project_departments`
  ADD CONSTRAINT `fk_pd_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pd_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
