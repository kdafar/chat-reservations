-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 09, 2025 at 11:11 AM
-- Server version: 8.0.36-28
-- PHP Version: 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `labshkwt`
--

-- --------------------------------------------------------

--
-- Table structure for table `state`
--

CREATE TABLE `state` (
  `id` smallint UNSIGNED NOT NULL,
  `state_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` int NOT NULL DEFAULT '1' COMMENT '1 = Yes , 2 = No',
  `is_deleted` int NOT NULL DEFAULT '2' COMMENT '1 = Yes , 2 = No',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `state`
--

INSERT INTO `state` (`id`, `state_name`, `state_name_ar`, `is_available`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 'Ahmadi', 'الأحمدي', 1, 2, '2023-03-23 10:11:44', '2025-07-11 11:42:57'),
(2, 'Jahra', 'الجهراء', 2, 2, '2023-03-23 10:11:44', '2025-02-07 17:05:51'),
(3, 'Hawalli', 'حولي', 1, 2, '2023-03-23 10:11:44', NULL),
(4, 'Assima', 'العاصمة', 1, 2, '2023-03-23 10:11:44', '2025-02-08 13:38:01'),
(5, 'Farwaniya', 'الفروانية', 1, 2, '2023-03-23 10:11:44', NULL),
(6, 'Mubarak Al Kabeer', 'مبارك الكبير', 2, 2, '2023-03-23 10:11:44', '2025-02-07 17:06:03'),
(7, 'السالميه', 'السالميه', 1, 1, '2024-09-23 12:20:19', '2024-09-23 12:22:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `state`
--
ALTER TABLE `state`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `state`
--
ALTER TABLE `state`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
