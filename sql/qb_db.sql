-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 08:54 AM
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
-- Database: `qb_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(225) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password`, `phone`, `created_at`, `profile_photo`) VALUES
(1, 'admin', 'admin@gmail.com', 'admin123', '', '2025-11-19 09:12:38', '/project/Capstone-Car-Service-Draft4/images/admins/admin-1.png');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` varchar(50) NOT NULL,
  `status` enum('Pending','Scheduled','In Progress','Completed','Cancelled','Picked Up') DEFAULT 'Pending',
  `progress_step` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `additional_notes` text DEFAULT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `admin_note` text DEFAULT NULL,
  `service_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`service_photos`)),
  `scheduled_by_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `customer_id`, `vehicle_id`, `preferred_date`, `preferred_time`, `status`, `progress_step`, `additional_notes`, `estimated_cost`, `admin_note`, `service_photos`, `scheduled_by_admin_id`, `created_at`) VALUES
(31, 28, 13, '2025-11-28', '05:00 PM', 'Picked Up', 7, '', 0.00, NULL, NULL, NULL, '2025-11-24 19:17:28');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_images`
--

CREATE TABLE `appointment_images` (
  `image_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_services`
--

CREATE TABLE `appointment_services` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `price_charged` decimal(10,2) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `mechanic_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_services`
--

INSERT INTO `appointment_services` (`id`, `appointment_id`, `service_name`, `price_charged`, `completion_date`, `mechanic_notes`) VALUES
(34, 11, 'Tyre Installation', NULL, NULL, NULL),
(35, 12, 'Wiper Blade Replacement', NULL, NULL, NULL),
(36, 13, 'Tyre Installation', NULL, NULL, NULL),
(37, 14, 'Tyre Installation', NULL, NULL, NULL),
(38, 15, 'Tyre Installation', NULL, NULL, NULL),
(39, 16, 'Tyre Installation', NULL, NULL, NULL),
(40, 17, 'Tyre Installation', NULL, NULL, NULL),
(41, 18, 'Tyre Installation', NULL, NULL, NULL),
(42, 19, 'Tyre Installation', NULL, NULL, NULL),
(43, 20, 'Wheel Alignment', NULL, NULL, NULL),
(44, 21, 'Wiper Blade Replacement', NULL, NULL, NULL),
(45, 22, 'Wheel Alignment', NULL, NULL, NULL),
(46, 23, 'Tyre Installation', NULL, NULL, NULL),
(47, 24, 'Tyre Installation', NULL, NULL, NULL),
(48, 25, 'Tyre Installation', NULL, NULL, NULL),
(49, 26, 'Wheel Balancing', NULL, NULL, NULL),
(50, 27, 'Tyre Installation', NULL, NULL, NULL),
(51, 28, 'Wiper Blade Replacement', NULL, NULL, NULL),
(52, 29, 'Brake Shoe Replacement', NULL, NULL, NULL),
(55, 30, 'Wheel Alignment', NULL, NULL, NULL),
(56, 30, 'Wiper Blade Replacement', NULL, NULL, NULL),
(57, 31, 'Tyre Installation', NULL, NULL, NULL),
(58, 32, 'Wheel Alignment', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `preferred_contact` enum('Email','Phone') NOT NULL DEFAULT 'Email',
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `member_since` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `full_name`, `email`, `password`, `phone`, `address`, `preferred_contact`, `profile_photo`, `is_verified`, `member_since`, `notifications_enabled`, `email_verified`) VALUES
(28, 'Joe', 'voonjoe9@gmail.com', '$2y$10$/oBWCgVBveoxJhtYp6gV.elpfgGqLJobRh0CDXWu32OgekaWcB7Ma', '0143388442', NULL, 'Email', '../../images/customers/customer-28.jpg', 1, '2025-11-23 12:26:23', 1, 0),
(31, 'chaiyeejin', 'yeejinchai35@gmail.com', '$2y$10$BJeZ3DnQV1lXAqZgsBmaCu/ZlznTtLJU0IrscsOBpvLzr1QrgXTNS', '01111189280', NULL, 'Email', NULL, 1, '2025-11-25 07:38:18', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `license_plate_number` varchar(20) NOT NULL,
  `vehicle_model` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `customer_id`, `license_plate_number`, `vehicle_model`) VALUES
(13, 28, 'W8338C', 'gtr r44'),
(18, 28, 'BLY118', 'BMW 5 Series');

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `token_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `token_type` enum('Email_Verify','Password_Reset') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_tokens`
--

INSERT INTO `verification_tokens` (`token_id`, `customer_id`, `admin_id`, `token`, `token_type`, `created_at`, `expires_at`) VALUES
(1, 13, NULL, 'b29b3c36c823a8e753287b0298dc8f0b52154ec75bdf33bcaae42a98e473e266', 'Email_Verify', '2025-11-20 07:12:24', '2025-11-21 08:12:24'),
(7, 29, NULL, 'bef881fb0934d2a27166ed1a7b624ae4b19b8910b75601f1318f9144ea91f247', 'Password_Reset', '2025-11-22 16:17:28', '2025-11-22 18:17:28'),
(18, 28, NULL, '9af8145fca88bc1dd9bcc6bc553e5b552829ffb5cfb93840f4f5a297a7b6f751', 'Password_Reset', '2025-11-25 07:16:05', '2025-11-25 09:16:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `scheduled_by_admin_id` (`scheduled_by_admin_id`);

--
-- Indexes for table `appointment_images`
--
ALTER TABLE `appointment_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `service_id` (`service_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `license_plate_number` (`license_plate_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `appointment_images`
--
ALTER TABLE `appointment_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_services`
--
ALTER TABLE `appointment_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
