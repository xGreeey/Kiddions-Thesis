-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 18, 2025 at 11:25 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u485501277_mmtvtc`
--

-- --------------------------------------------------------

--
-- Table structure for table `abuse_tracking`
--

CREATE TABLE `abuse_tracking` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `identifier_type` enum('email','student_number','ip') NOT NULL,
  `action_type` enum('login','otp_request','otp_verify','password_reset','account_lockout') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `lockout_count` int(11) DEFAULT 0,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `cleared_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `abuse_tracking`
--

INSERT INTO `abuse_tracking` (`id`, `identifier`, `identifier_type`, `action_type`, `ip_address`, `success`, `lockout_count`, `details`, `locked_until`, `created_at`, `cleared_at`) VALUES
(1, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:47:39', NULL),
(2, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:47:43', NULL),
(3, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:47:44', NULL),
(4, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:47:48', NULL),
(5, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:49:58', NULL),
(6, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:50:01', NULL),
(7, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:51:22', NULL),
(8, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:51:24', NULL),
(9, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:51:34', NULL),
(10, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:51:36', NULL),
(11, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:52:53', NULL),
(12, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:52:56', NULL),
(13, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:55:40', NULL),
(14, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:55:42', NULL),
(15, 'Leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 16:56:33', NULL),
(16, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 16:56:36', NULL),
(17, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:02:08', NULL),
(18, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:02:11', NULL),
(19, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-07 17:03:32', '2025-10-07 17:03:47'),
(20, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-07 17:03:47', '2025-10-07 17:03:58'),
(21, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:03:47', NULL),
(22, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:03:49', NULL),
(23, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:03:58', NULL),
(24, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:04:00', NULL),
(25, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-07 17:04:26', '2025-10-07 17:04:38'),
(26, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:04:38', NULL),
(27, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:04:40', NULL),
(28, 'Leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:07:01', NULL),
(29, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:07:03', NULL),
(30, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:10:20', NULL),
(31, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:10:23', NULL),
(32, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:21:08', NULL),
(33, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:21:10', NULL),
(34, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:22:25', NULL),
(35, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:22:27', NULL),
(36, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:26:45', NULL),
(37, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:26:49', NULL),
(38, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:34:23', NULL),
(39, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:34:26', NULL),
(40, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:38:18', NULL),
(41, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:38:21', NULL),
(42, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:50:06', NULL),
(43, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:50:08', NULL),
(44, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 17:55:23', NULL),
(45, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 17:55:25', NULL),
(46, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:03:37', NULL),
(47, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:03:42', NULL),
(48, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:21:22', NULL),
(49, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:21:24', NULL),
(50, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-07 18:25:13', '2025-10-07 18:25:19'),
(51, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:25:19', NULL),
(52, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:25:24', NULL),
(53, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-07 18:27:03', '2025-10-07 18:27:10'),
(54, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:27:10', NULL),
(55, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:1318::1f1:1d9', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:27:13', NULL),
(56, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:32:51', NULL),
(57, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:32:54', NULL),
(58, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:48:46', NULL),
(59, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:48:49', NULL),
(60, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 18:53:03', NULL),
(61, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 18:53:05', NULL),
(62, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:03:15', NULL),
(63, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:03:20', NULL),
(64, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:14:09', NULL),
(65, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:14:12', NULL),
(66, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:29:37', NULL),
(67, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:29:39', NULL),
(68, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:31:41', NULL),
(69, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:31:44', NULL),
(70, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:32:24', NULL),
(71, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:32:28', NULL),
(72, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-07 19:56:17', NULL),
(73, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-07 19:56:20', NULL),
(74, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ae0:1318::2e4:91', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-08 06:23:25', '2025-10-08 06:23:32'),
(75, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ae0:1318::2e4:91', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 06:23:32', NULL),
(76, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ae0:1318::2e4:91', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 06:23:35', NULL),
(77, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-08 09:54:01', '2025-10-08 09:54:08'),
(78, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 09:54:08', NULL),
(79, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 09:54:13', NULL),
(80, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 10:21:20', NULL),
(81, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 10:21:23', NULL),
(82, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-08 10:30:54', '2025-10-08 10:31:14'),
(83, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-08 10:31:02', '2025-10-08 10:31:14'),
(84, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 10:31:14', NULL),
(85, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 10:31:19', NULL),
(86, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 10:46:20', NULL),
(87, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 10:46:23', NULL),
(88, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 10:58:42', NULL),
(89, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 10:58:44', NULL),
(90, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 11:01:26', NULL),
(91, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 11:01:29', NULL),
(92, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 11:02:49', NULL),
(93, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:1318::1f1:20d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 11:02:52', NULL),
(94, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 11:25:07', NULL),
(95, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 11:25:10', NULL),
(96, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 11:36:53', NULL),
(97, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 11:36:55', NULL),
(98, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 11:49:33', NULL),
(99, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 11:49:37', NULL),
(100, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 12:15:12', NULL),
(101, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 12:15:15', NULL),
(102, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 12:15:54', NULL),
(103, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 12:15:56', NULL),
(104, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fcc:18c8::278:b8', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 14:13:01', NULL),
(105, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fcc:18c8::278:b8', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 14:13:05', NULL),
(106, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 15:18:41', NULL),
(107, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 15:18:43', NULL),
(108, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 16:59:46', NULL),
(109, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 16:59:49', NULL),
(110, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:01:49', NULL),
(111, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:01:52', NULL),
(112, 'Leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:02:07', NULL),
(113, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:02:12', NULL),
(114, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fcc:18c8::278:b8', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:03:45', NULL),
(115, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fcc:18c8::278:b8', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:03:48', NULL),
(116, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:09:43', NULL),
(117, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:09:47', NULL),
(118, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:32:32', NULL),
(119, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:32:37', NULL),
(120, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-08 17:35:35', NULL),
(121, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-08 17:35:38', NULL),
(122, 'leemac.vincoy@gmail.com', 'email', 'login', '154.205.22.57', 0, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-09 07:17:26', NULL),
(123, 'leemac.vincoy@gmail.com', 'email', 'login', '154.205.22.57', 0, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-09 07:17:42', NULL),
(124, 'leemarc.vincoy@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 07:17:51', NULL),
(125, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 07:17:54', NULL),
(126, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 11:31:12', NULL),
(127, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 11:31:17', NULL),
(128, 'aldrininocencio212527@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 11:56:52', NULL),
(129, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 11:56:55', NULL),
(130, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 14:59:47', NULL),
(131, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 14:59:50', NULL),
(132, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 15:01:22', NULL),
(133, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 15:01:25', NULL),
(134, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa1:25af::3c1:4a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 15:29:35', NULL),
(135, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa1:25af::3c1:4a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 15:29:39', NULL),
(136, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 15:33:18', NULL),
(137, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 15:33:20', NULL),
(138, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa1:25af::3c1:4a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-09 15:55:35', NULL),
(139, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa1:25af::3c1:4a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-09 15:55:37', NULL),
(140, 'leemarc.vincoy@gmail.com', 'email', 'login', '2001:fd8:416:6029:e187:5766:f102:741d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 02:15:32', NULL),
(141, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '2001:fd8:416:6029:e187:5766:f102:741d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 02:15:34', NULL),
(142, 'ianemrsonb@gmail.com', 'email', 'login', '203.160.191.135', 0, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-10 03:36:00', NULL),
(143, 'ianemErsonb@gmail.com', 'email', 'login', '203.160.191.135', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 03:36:07', NULL),
(144, 'ianemersonb@gmail.com', 'email', 'otp_request', '203.160.191.135', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 03:36:10', NULL),
(145, 'leemarc.vincoy@gmail.com', 'email', 'login', '203.160.191.135', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 05:34:24', NULL),
(146, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '203.160.191.135', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 05:34:28', NULL),
(147, 'aldrininocencio212527@gmail.com', 'email', 'login', '203.160.191.135', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 05:43:19', NULL),
(148, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '203.160.191.135', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 05:43:21', NULL),
(149, 'leemarc.vincoy@gmail.com', 'email', 'login', '203.160.191.135', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 05:44:36', NULL),
(150, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '203.160.191.135', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 05:44:38', NULL),
(151, 'ianemersonb@gmail.com', 'email', 'login', '154.205.22.51', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 05:49:05', NULL),
(152, 'ianemersonb@gmail.com', 'email', 'otp_request', '154.205.22.51', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 05:49:08', NULL),
(153, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 08:09:25', NULL),
(154, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 08:09:28', NULL),
(155, 'aldrininocencio212527@gmail.com', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 08:15:14', NULL),
(156, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 08:15:16', NULL),
(157, 'aldrininocencio212527@gmail.com', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 08:16:35', NULL),
(158, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 08:16:37', NULL),
(159, 'ianemersonb@gmail.com', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 08:36:26', NULL),
(160, 'ianemersonb@gmail.com', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 08:36:29', NULL),
(161, 'aldrininocencio212527@gmail.com', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 08:43:06', NULL),
(162, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 08:43:08', NULL),
(163, 'leemarc.vincoy@gmail.com', 'email', 'login', '119.92.55.187', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 09:21:48', NULL),
(164, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '119.92.55.187', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 09:21:50', NULL),
(165, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e4:7d', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 12:57:13', NULL),
(166, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e4:7d', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 12:57:15', NULL),
(167, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-10 23:28:59', '2025-10-10 23:29:05'),
(168, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 23:29:05', NULL),
(169, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 23:29:08', NULL),
(170, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-10 23:29:59', NULL),
(171, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-10 23:30:01', NULL),
(172, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 00:55:59', NULL),
(173, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 00:56:01', NULL),
(174, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 01:01:21', NULL),
(175, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 01:01:23', NULL),
(176, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 01:52:06', NULL),
(177, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 01:52:08', NULL),
(178, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-11 01:58:52', '2025-10-11 02:03:15'),
(179, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 02:03:15', NULL),
(180, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c2:52', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 02:03:18', NULL),
(181, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 02:40:09', NULL),
(182, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 02:40:11', NULL),
(183, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa6:1d0f::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 03:16:24', NULL),
(184, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa6:1d0f::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 03:16:26', NULL),
(185, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 03:53:11', NULL),
(186, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 03:53:14', NULL),
(187, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 04:55:36', NULL),
(188, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 04:55:40', NULL),
(189, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-11 08:18:00', '2025-10-11 08:18:11'),
(190, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 08:18:11', NULL),
(191, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 08:18:15', NULL),
(192, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa7:1028::19c:195', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 08:19:19', NULL),
(193, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa7:1028::19c:195', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 08:19:22', NULL),
(194, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-11 10:33:28', NULL),
(195, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-11 10:33:30', NULL),
(196, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 08:50:04', NULL),
(197, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 08:50:08', NULL),
(198, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 12:33:15', NULL),
(199, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 12:33:18', NULL),
(200, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 12:50:01', NULL),
(201, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 12:50:04', NULL),
(202, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 13:34:01', NULL),
(203, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 13:34:04', NULL),
(204, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 14:11:04', NULL),
(205, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 14:11:07', NULL),
(206, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 14:12:39', NULL),
(207, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 14:12:41', NULL),
(208, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 14:35:12', NULL),
(209, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 14:35:15', NULL),
(210, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa5:18be::277:b2', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-12 14:45:47', '2025-10-12 14:45:53'),
(211, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa5:18be::277:b2', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 14:45:53', NULL),
(212, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:18be::277:b2', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 14:45:56', NULL),
(213, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 15:17:21', NULL),
(214, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 15:17:24', NULL),
(215, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa5:18be::277:b2', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 15:30:36', NULL),
(216, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:18be::277:b2', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 15:30:39', NULL),
(217, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 15:49:25', NULL),
(218, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 15:49:28', NULL),
(219, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 15:55:23', NULL),
(220, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 15:55:25', NULL),
(221, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 16:18:20', NULL),
(222, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 16:18:22', NULL),
(223, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 17:10:50', NULL),
(224, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 17:10:54', NULL),
(225, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-12 17:57:01', NULL),
(226, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-12 17:57:03', NULL),
(227, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 07:02:40', NULL),
(228, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 07:02:42', NULL),
(229, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 07:06:21', NULL),
(230, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 07:06:23', NULL),
(231, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 07:15:54', NULL),
(232, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 07:15:57', NULL),
(233, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 08:39:01', NULL),
(234, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 08:39:04', NULL),
(235, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 08:39:41', NULL),
(236, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 08:39:44', NULL),
(237, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 08:40:48', NULL),
(238, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 08:40:50', NULL),
(239, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 08:43:07', NULL),
(240, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 08:43:10', NULL),
(241, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:11:29', NULL),
(242, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:11:31', NULL),
(243, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:24:32', NULL),
(244, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:24:37', NULL),
(245, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:39:41', NULL),
(246, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:39:44', NULL),
(247, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:46:24', NULL),
(248, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:46:28', NULL),
(249, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:50:34', NULL),
(250, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:50:36', NULL),
(251, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:51:24', NULL),
(252, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:51:26', NULL),
(253, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 10:52:46', NULL),
(254, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:18c8::278:c0', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 10:52:48', NULL),
(255, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 12:21:29', NULL),
(256, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 12:21:33', NULL),
(257, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 13:12:44', NULL),
(258, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 13:12:46', NULL),
(259, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 13:20:10', NULL),
(260, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 13:20:13', NULL),
(261, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 13:33:29', NULL),
(262, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 13:33:31', NULL),
(263, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 13:41:49', NULL),
(264, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 13:41:53', NULL),
(265, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 13:44:43', NULL),
(266, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 13:44:47', NULL),
(267, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:07:54', NULL),
(268, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:07:56', NULL),
(269, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:15:36', NULL),
(270, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:15:39', NULL),
(271, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:17:43', NULL),
(272, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:17:46', NULL),
(273, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:41:15', NULL),
(274, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:41:19', NULL),
(275, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:50:04', NULL),
(276, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa5:25af::3c1:3e', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:50:06', NULL),
(277, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 14:52:05', NULL),
(278, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 14:52:08', NULL),
(279, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 15:37:10', NULL),
(280, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 15:37:13', NULL),
(281, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 15:59:15', NULL),
(282, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 15:59:18', NULL),
(283, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 16:26:07', NULL),
(284, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 16:26:10', NULL),
(285, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '2a09:bac5:4fa4:1d0f::2e5:5b', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 17:39:49', NULL),
(286, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa4:1d0f::2e5:5b', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 17:39:56', NULL),
(287, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 17:44:30', NULL),
(288, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 17:44:33', NULL),
(289, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-13 18:27:21', NULL),
(290, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-13 18:27:24', NULL),
(291, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 06:46:10', NULL),
(292, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 06:46:14', NULL),
(293, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 06:48:53', NULL),
(294, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 06:49:00', NULL),
(295, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 06:50:45', NULL),
(296, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 06:50:49', NULL),
(297, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 07:05:47', NULL),
(298, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 07:05:54', NULL),
(299, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 07:13:55', NULL),
(300, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 07:13:59', NULL),
(301, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 07:20:51', NULL),
(302, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 07:20:53', NULL),
(303, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 07:38:56', NULL),
(304, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 07:38:58', NULL),
(305, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:28:39', NULL),
(306, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:28:41', NULL),
(307, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:30:43', NULL),
(308, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:30:47', NULL),
(309, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:31:44', NULL),
(310, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:31:47', NULL),
(311, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:33:50', NULL),
(312, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:33:53', NULL),
(313, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:57:41', NULL),
(314, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:57:44', NULL),
(315, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 12:58:04', NULL),
(316, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 12:58:06', NULL),
(317, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:04:20', NULL),
(318, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:04:27', NULL),
(319, 'inocenciojohnaldrin@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:04:49', NULL),
(320, 'inocenciojohnaldrin@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:04:56', NULL),
(321, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:26:43', NULL),
(322, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:26:47', NULL),
(323, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:30:28', NULL),
(324, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:30:31', NULL),
(325, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:33:23', NULL),
(326, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:33:27', NULL),
(327, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-14 13:35:17', NULL),
(328, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-14 13:35:21', NULL),
(329, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 01:57:16', NULL),
(330, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 01:57:19', NULL),
(331, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 02:27:23', NULL),
(332, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 02:27:26', NULL),
(333, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 03:21:37', NULL),
(334, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 03:21:39', NULL),
(335, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-15 04:21:43', '2025-10-15 04:21:50'),
(336, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 04:21:50', NULL),
(337, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 04:21:52', NULL);
INSERT INTO `abuse_tracking` (`id`, `identifier`, `identifier_type`, `action_type`, `ip_address`, `success`, `lockout_count`, `details`, `locked_until`, `created_at`, `cleared_at`) VALUES
(338, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 04:33:29', NULL),
(339, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 04:33:31', NULL),
(340, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:13:48', NULL),
(341, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:13:51', NULL),
(342, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:15:48', NULL),
(343, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5aa0:8::2e5:4f', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:15:52', NULL),
(344, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:18:30', NULL),
(345, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:18:34', NULL),
(346, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:25:28', NULL),
(347, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:25:30', NULL),
(348, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:33:39', NULL),
(349, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:33:41', NULL),
(350, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-15 05:42:04', '2025-10-15 05:42:39'),
(351, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 05:42:39', NULL),
(352, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 05:42:41', NULL),
(353, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 06:35:28', NULL),
(354, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 06:35:33', NULL),
(355, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::19b:13a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 07:01:19', NULL),
(356, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::19b:13a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 07:01:22', NULL),
(357, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ac0:8::19b:13a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 07:06:17', NULL),
(358, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ac0:8::19b:13a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 07:06:19', NULL),
(359, 'leemarc.vincoy@gmail.com', 'email', 'login', '180.190.63.233', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 07:09:55', NULL),
(360, 'leemarc.vincoy@gmail.com', 'email', 'otp_request', '180.190.63.233', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 07:09:58', NULL),
(361, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa2:101e::19b:13a', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-15 08:25:07', '2025-10-15 08:25:13'),
(362, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa2:101e::19b:13a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 08:25:13', NULL),
(363, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa2:101e::19b:13a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 08:25:15', NULL),
(364, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac5:4fa2:101e::19b:13a', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 08:56:07', NULL),
(365, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac5:4fa2:101e::19b:13a', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 08:56:10', NULL),
(366, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 12:10:38', NULL),
(367, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 12:10:41', NULL),
(368, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '112.204.126.167', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 12:17:59', NULL),
(369, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '112.204.126.167', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 12:18:05', NULL),
(370, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 14:34:14', NULL),
(371, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 14:34:21', NULL),
(372, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:09:12', NULL),
(373, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:09:15', NULL),
(374, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:35:56', NULL),
(375, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:35:59', NULL),
(376, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:38:47', NULL),
(377, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:38:49', NULL),
(378, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-15 21:42:22', '2025-10-15 21:42:27'),
(379, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:42:27', NULL),
(380, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:42:32', NULL),
(381, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:44:24', NULL),
(382, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:44:28', NULL),
(383, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:46:17', NULL),
(384, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:46:20', NULL),
(385, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:47:33', NULL),
(386, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:47:37', NULL),
(387, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:49:39', NULL),
(388, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:49:42', NULL),
(389, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:50:18', NULL),
(390, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:50:22', NULL),
(391, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:52:38', NULL),
(392, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:52:43', NULL),
(393, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:55:21', NULL),
(394, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:55:25', NULL),
(395, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 21:57:11', NULL),
(396, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 21:57:15', NULL),
(397, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 22:00:23', NULL),
(398, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 22:00:30', NULL),
(399, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 22:14:33', NULL),
(400, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 22:14:37', NULL),
(401, 'aldrininocencio212527@gmail.com', 'email', 'login', '136.158.58.89', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 22:16:54', NULL),
(402, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '136.158.58.89', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 22:16:57', NULL),
(403, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '2001:fd8:443:ab2f:193f:9a0b:8d95:a3ef', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 22:33:18', NULL),
(404, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '2001:fd8:443:ab2f:193f:9a0b:8d95:a3ef', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 22:33:22', NULL),
(405, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.142', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 22:38:52', NULL),
(406, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.142', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 22:38:55', NULL),
(407, 'aldrininocencio212527@gmil.com', 'email', 'login', '2001:fd8:180a:4980:7471:cb40:5831:ea58', 0, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-15 23:51:44', NULL),
(408, 'aldrininocencio212527@gmail.com', 'email', 'login', '2001:fd8:180a:4980:7471:cb40:5831:ea58', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-15 23:52:25', NULL),
(409, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2001:fd8:180a:4980:7471:cb40:5831:ea58', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-15 23:52:27', NULL),
(410, 'ianemersonb@gmail.com', 'email', 'login', '2001:fd8:180a:4980:7471:cb40:5831:ea58', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 00:06:12', NULL),
(411, 'ianemersonb@gmail.com', 'email', 'otp_request', '2001:fd8:180a:4980:7471:cb40:5831:ea58', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 00:06:15', NULL),
(412, 'ianemersonb@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 00:54:24', NULL),
(413, 'ianemersonb@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 00:54:29', NULL),
(414, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 01:23:53', NULL),
(415, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 01:23:58', NULL),
(416, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 01:27:09', NULL),
(417, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 01:27:13', NULL),
(418, 'ianemersonb@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 01:50:19', NULL),
(419, 'ianemersonb@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 01:50:23', NULL),
(420, 'aldrininocencio212527@gmil.com', 'email', 'login', '154.205.22.57', 0, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-16 01:54:40', NULL),
(421, 'aldrininocencio212527@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 01:54:59', NULL),
(422, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 01:55:01', NULL),
(423, 'aldrininocencio212527@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:04:53', NULL),
(424, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:04:55', NULL),
(425, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:08:16', NULL),
(426, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:08:18', NULL),
(427, 'aldrininocencio212527@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:09:00', NULL),
(428, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:09:04', NULL),
(429, 'lebrondeidree.satumba@my.jru.edu', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:12:27', NULL),
(430, 'lebrondeidree.satumba@my.jru.edu', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:12:29', NULL),
(431, 'ianemersonb@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:19:08', NULL),
(432, 'ianemersonb@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:19:10', NULL),
(433, 'aldrininocencio212527@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:22:21', NULL),
(434, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:22:24', NULL),
(435, 'ianemersonb@gmail.com', 'email', 'login', '154.205.22.57', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 02:25:13', NULL),
(436, 'ianemersonb@gmail.com', 'email', 'otp_request', '154.205.22.57', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 02:25:16', NULL),
(437, 'aldrininocencio212527@gmail.com', 'email', 'login', '154.205.22.51', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-16 08:53:30', NULL),
(438, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '154.205.22.51', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-16 08:53:36', NULL),
(439, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ae0:8::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:04:27', NULL),
(440, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ae0:8::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:04:31', NULL),
(441, 'aldrininocencio212527@gmail.com', 'email', 'login', '2a09:bac1:5ae0:8::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:16:29', NULL),
(442, 'aldrininocencio212527@gmail.com', 'email', 'otp_request', '2a09:bac1:5ae0:8::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:16:33', NULL),
(443, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:22:53', NULL),
(444, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:22:57', NULL),
(445, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:26:11', NULL),
(446, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:26:16', NULL),
(447, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:44:22', NULL),
(448, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:44:25', NULL),
(449, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 08:52:25', NULL),
(450, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 08:52:27', NULL),
(451, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"reason\":\"invalid_credentials\"}', NULL, '2025-10-18 09:10:57', '2025-10-18 09:11:07'),
(452, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 09:11:07', NULL),
(453, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 09:11:09', NULL),
(454, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 09:46:12', NULL),
(455, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 09:46:17', NULL),
(456, 'ianemersonb@gmail.com', 'email', 'login', '103.254.214.141', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 09:56:30', NULL),
(457, 'ianemersonb@gmail.com', 'email', 'otp_request', '103.254.214.141', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 09:56:33', NULL),
(458, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 10:05:25', NULL),
(459, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 10:05:28', NULL),
(460, 'johnaldrin.inocencio@my.jru.edu', 'email', 'login', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"login_successful\":true}', NULL, '2025-10-18 10:24:42', NULL),
(461, 'johnaldrin.inocencio@my.jru.edu', 'email', 'otp_request', '2a09:bac5:4fa4:1d05::2e4:a4', 1, 0, '{\"otp_sent\":true}', NULL, '2025-10-18 10:24:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `add_trainees`
--

CREATE TABLE `add_trainees` (
  `id` int(11) NOT NULL,
  `surname` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `firstname` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `middlename` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `student_number` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `contact_number` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `contact_method` enum('phone','email') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'phone',
  `course` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date_enrolled` date NOT NULL,
  `additional_notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` enum('active','inactive','graduated','dropped') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `add_trainees`
--

INSERT INTO `add_trainees` (`id`, `surname`, `firstname`, `middlename`, `student_number`, `contact_number`, `email`, `contact_method`, `course`, `date_enrolled`, `additional_notes`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(19, 'Sample', 'Student', '', '101010', '', 'kariyagaleechristian@gmail.com', 'email', 'Housekeeping', '2025-10-09', '', 'active', '2025-10-09 11:58:12', '2025-10-09 11:58:12', NULL),
(20, 'siao', 'alfonso', 'gonzales', '123-456-789', '', 'siao.alfonso@gmail.com', 'email', 'Basic Computer Literacy', '2025-10-10', 'no payment for id', 'active', '2025-10-10 08:49:14', '2025-10-10 08:49:14', NULL),
(21, 'sadfasdf', 'sadfasdf', '', '234234', '', 'sadfasdf@gmail.com', 'email', 'Automotive Servicing', '2025-10-11', '', 'active', '2025-10-10 23:30:43', '2025-10-10 23:30:43', NULL),
(22, 'aaaaaaaaaaaaaa', 'aaaaaaaaaaaaaa', '', '43534435', '', 'aaaaaaaaaaaaaa@gmail.copm', 'email', 'RAC Servicing', '2025-10-11', '', 'active', '2025-10-11 01:52:36', '2025-10-11 01:52:36', NULL),
(23, 'asdfsdaafd', 'asdfsdaafd', '', '3214324', '', 'asdfsdaafd@gmail.com', 'email', 'RAC Servicing', '2025-10-11', '', 'active', '2025-10-11 02:03:52', '2025-10-11 02:03:52', NULL),
(27, 'ino', 'drin', '', '8345893489', '', 'inocenciojohnaldrin@gmail.com', 'email', 'RAC Servicing', '2025-10-13', '', 'active', '2025-10-13 14:07:14', '2025-10-13 14:07:14', NULL),
(28, 'ian', 'ian', '', '565543', '', 'ianemersonb@gmail.com', 'email', 'RAC Servicing', '2025-10-13', '', 'active', '2025-10-13 14:14:40', '2025-10-13 14:14:40', NULL),
(29, 'dsfasdf', 'fasfadsf', '', '54365464', '', 'asdf@gmail.com', 'email', 'RAC Servicing', '2025-10-13', '', 'active', '2025-10-13 14:15:05', '2025-10-13 14:15:05', NULL),
(30, 'asdfs', 'sdfasdf', '', '45654654', '', 'zzzzz@gmail.com', 'email', 'RAC Servicing', '2025-10-16', '', 'active', '2025-10-13 14:15:21', '2025-10-13 14:15:21', NULL),
(31, 'JAMAL', 'WILLIAMS', '', '123321123', '', 'koroneshee@gmail.com', 'email', 'Hairdressing', '2025-10-14', 'dwasd', 'active', '2025-10-14 13:32:37', '2025-10-14 13:32:37', NULL),
(32, 'vincoy', 'leemarc', '', '43534535', '', 'leemarc.vincoy@gmail.com', 'email', 'Electrical Installation and Maintenance', '2025-10-15', '', 'active', '2025-10-15 05:18:44', '2025-10-15 05:18:44', NULL),
(33, 'satumba', 'lebron', '', '4456456554', '', 'lebrondeidree.satumba@my.jru.edu', 'email', 'RAC Servicing', '2025-10-15', '', 'active', '2025-10-15 05:41:11', '2025-10-15 05:41:11', NULL),
(35, 'Go', 'Patricia', '', '4337783023', '', 'greycruz0000000@gmail.com', 'email', 'RAC Servicing', '2025-10-16', '', 'active', '2025-10-15 21:14:14', '2025-10-15 21:14:14', NULL),
(36, 'Cruz', 'Grey', '', '4389933922', '', 'greycruz0000000000@gmail.com', 'email', 'RAC Servicing', '2025-10-16', '', 'active', '2025-10-15 21:16:02', '2025-10-15 21:16:02', NULL),
(40, 'Vincoy', 'Leemarc', 'G.', '12385243432', '', 'leemarcruss.vincoy@my.jru.edu', 'email', 'Automotive Servicing', '2025-10-16', '', 'active', '2025-10-16 02:06:55', '2025-10-16 02:06:55', NULL),
(41, 'Vincoy', 'Leemarc', 'B.', '64425685', '', 'leemarc.vincoy26@gmail.com', 'email', 'RAC Servicing', '2025-10-16', '', 'active', '2025-10-16 02:10:50', '2025-10-16 02:10:50', NULL),
(42, 'Inocencio', 'John Aldrin', '', '439834893', '', 'johnaldrin.inocencio@my.jru.edu', 'email', 'RAC Servicing', '2025-10-18', '', 'active', '2025-10-18 08:17:18', '2025-10-18 08:17:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_alerts`
--

CREATE TABLE `admin_alerts` (
  `id` int(11) NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `identifier_type` varchar(50) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `priority` varchar(20) DEFAULT 'normal',
  `audience` varchar(50) DEFAULT 'all',
  `date_created` datetime DEFAULT current_timestamp(),
  `expiry_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `type`, `priority`, `audience`, `date_created`, `expiry_date`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
(52, 'Orientation for Newly Enrolled Trainees', 'Attention new trainees!\r\nMMTVTC will conduct a general orientation this coming 27th of October at the MMTVTC Hall\r\nDuring the session, we will discuss training guidelines, system navigation (Grades, Job Matching, and Career Analytics), and student responsibilities.', 'general', 'normal', 'all', '2025-10-15 21:20:28', NULL, 1, '2025-10-15 21:20:28', '2025-10-15 21:20:28', NULL),
(53, 'Account Activation and System Access', 'All newly enrolled trainees must activate their online accounts by creating password on the link sent to their registered email addresses. Once logged in, you can monitor grades, and stay informed about training center announcements.', 'general', 'high', 'all', '2025-10-15 21:21:57', NULL, 1, '2025-10-15 21:21:57', '2025-10-15 21:21:57', NULL),
(54, 'Training Uniform and ID Issuance', 'Newly enrolled trainees are advised to claim their official training ID starting November 1, 2025 at the MMTVTC Registrar’s Office. Please bring a valid ID and proof of enrollment for verification.', 'general', 'high', 'all', '2025-10-15 21:23:00', NULL, 1, '2025-10-15 21:23:00', '2025-10-15 21:23:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `assessment_name` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `assessment_type_id` int(11) NOT NULL,
  `total_items` int(11) NOT NULL,
  `date_given` date NOT NULL,
  `quarter` int(11) DEFAULT 1,
  `subject` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_by` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_types`
--

CREATE TABLE `assessment_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_types`
--

INSERT INTO `assessment_types` (`id`, `type_name`, `category_id`, `created_at`) VALUES
(1, 'Quiz', 1, '2025-08-21 17:55:36'),
(2, 'Homework', 1, '2025-08-21 17:55:36'),
(3, 'Activity', 2, '2025-08-21 17:55:36'),
(4, 'Exam', 3, '2025-08-21 17:55:36');

-- --------------------------------------------------------

--
-- Table structure for table `computed_grades`
--

CREATE TABLE `computed_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `written_work_avg` decimal(5,2) DEFAULT 0.00,
  `performance_task_avg` decimal(5,2) DEFAULT 0.00,
  `quarterly_assessment_avg` decimal(5,2) DEFAULT 0.00,
  `final_grade` decimal(5,2) DEFAULT 0.00,
  `remarks` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'INCOMPLETE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ATS', 'AUTOMOTIVE SERVICING (ATS)', 1, '2025-10-01 15:53:59', NULL),
(2, 'BCL', 'BASIC COMPUTER LITERACY (BCL)', 1, '2025-10-01 15:53:59', NULL),
(3, 'BEC', 'BEAUTY CARE (NAIL CARE) (BEC)', 1, '2025-10-01 15:53:59', NULL),
(4, 'BPP', 'BREAD AND PASTRY PRODUCTION (BPP)', 1, '2025-10-01 15:53:59', NULL),
(5, 'CSS', 'COMPUTER SYSTEMS SERVICING (CSS)', 1, '2025-10-01 15:53:59', NULL),
(6, 'DRM', 'DRESSMAKING (DRM)', 1, '2025-10-01 15:53:59', NULL),
(7, 'EIM', 'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)', 1, '2025-10-01 15:53:59', NULL),
(8, 'EPAS', 'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICNG (EPAS)', 1, '2025-10-01 15:53:59', NULL),
(9, 'EVM', 'EVENTS MANAGEMENT SERVICES (EVM)', 1, '2025-10-01 15:53:59', NULL),
(10, 'FBS', 'FOOD AND BEVERAGE SERVICES (FBS)', 1, '2025-10-01 15:53:59', NULL),
(11, 'FOP', 'FOOD PROCESSING (FOP)', 1, '2025-10-01 15:53:59', NULL),
(12, 'HDR', 'HAIRDRESSING (HDR)', 1, '2025-10-01 15:53:59', NULL),
(13, 'HSK', 'HOUSEKEEPING (HSK)', 1, '2025-10-01 15:53:59', NULL),
(14, 'MAT', 'MASSAGE THERAPY (MAT)', 1, '2025-10-01 15:53:59', NULL),
(15, 'RAC', 'RAC SERVICING (RAC)', 1, '2025-10-01 15:53:59', NULL),
(16, 'SMAW', 'SHIELDED METAL ARC WELDING (SMAW)', 1, '2025-10-01 15:53:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_answers`
--

CREATE TABLE `exam_answers` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','paragraph','short_answer','checkbox') NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `question_order` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_question_options`
--

CREATE TABLE `exam_question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_submissions`
--

CREATE TABLE `exam_submissions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `score` decimal(5,2) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) DEFAULT 0,
  `status` enum('in_progress','submitted','graded') DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_categories`
--

CREATE TABLE `grade_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `weight_percentage` decimal(5,2) NOT NULL,
  `description` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grade_categories`
--

INSERT INTO `grade_categories` (`id`, `category_name`, `weight_percentage`, `description`, `created_at`) VALUES
(1, 'Written Work', 25.00, 'Quizzes, assignments, and written activities', '2025-08-21 17:55:22'),
(2, 'Performance Task', 50.00, 'Laboratory activities, projects, and practical work', '2025-08-21 17:55:22'),
(3, 'Quarterly Assessment', 25.00, 'Major exams and quarterly assessments', '2025-08-21 17:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `grade_details`
--

CREATE TABLE `grade_details` (
  `id` int(11) NOT NULL,
  `student_number` varchar(32) NOT NULL,
  `grade_number` tinyint(1) NOT NULL COMMENT '1..4',
  `component` varchar(50) NOT NULL,
  `date_given` date DEFAULT NULL,
  `raw_score` int(11) NOT NULL,
  `total_items` int(11) NOT NULL,
  `transmuted` decimal(5,2) NOT NULL COMMENT '0-100 scale',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_details`
--

INSERT INTO `grade_details` (`id`, `student_number`, `grade_number`, `component`, `date_given`, `raw_score`, `total_items`, `transmuted`, `created_at`, `updated_at`) VALUES
(58, '435345', 1, 'homework', '2020-02-12', 13, 20, 53.00, '2025-10-08 17:06:59', '2025-10-08 17:06:59'),
(59, '435345', 1, 'activity', '2025-10-09', 20, 20, 100.00, '2025-10-09 15:30:50', '2025-10-09 15:30:50'),
(60, '435345', 2, 'absent', '2025-10-09', 50, 100, 50.00, '2025-10-09 15:33:51', '2025-10-09 15:33:51'),
(61, '435345', 3, 'homework', NULL, 50, 50, 100.00, '2025-10-09 15:56:00', '2025-10-09 15:56:00'),
(63, '9563211331', 1, 'homework', '2025-10-10', 5, 5, 100.00, '2025-10-09 16:09:07', '2025-10-09 16:09:07'),
(64, '435345', 1, 'activity', '2025-10-11', 4, 5, 90.00, '2025-10-10 08:23:48', '2025-10-10 08:23:48'),
(65, '0000000000', 1, 'quiz', '2025-10-11', 43, 50, 93.00, '2025-10-11 08:19:42', '2025-10-11 08:19:42'),
(66, '0000000000', 2, 'absent', NULL, 50, 100, 50.00, '2025-10-11 08:19:48', '2025-10-11 08:19:48'),
(67, '435345', 2, 'absent', '2025-10-03', 50, 100, 50.00, '2025-10-11 10:34:34', '2025-10-11 10:34:34'),
(68, '999999999', 1, 'quiz', '2025-10-14', 5, 20, 63.00, '2025-10-14 07:06:13', '2025-10-14 07:06:13'),
(69, '999999999', 2, 'present', NULL, 49, 100, 49.00, '2025-10-14 07:12:11', '2025-10-14 07:12:11'),
(70, '999999999', 1, 'quiz', '2025-10-14', 5, 50, 55.00, '2025-10-14 07:41:07', '2025-10-14 07:41:07'),
(71, '8345893489', 1, 'activity', '2025-10-15', 38, 50, 88.00, '2025-10-15 06:36:09', '2025-10-15 06:36:09'),
(72, '8345893489', 2, 'present', '2025-10-15', 100, 100, 100.00, '2025-10-15 06:36:41', '2025-10-15 06:36:41'),
(73, '8345893489', 4, 'Pre-Assessment', NULL, 15, 20, 87.50, '2025-10-15 06:37:07', '2025-10-15 06:37:07'),
(74, '4337783023', 1, 'quiz', '2025-10-16', 45, 50, 95.00, '2025-10-15 22:35:07', '2025-10-15 22:35:07'),
(75, '4337783023', 2, 'present', NULL, 100, 100, 100.00, '2025-10-15 22:36:27', '2025-10-15 22:36:27'),
(76, '4337783023', 3, 'homework', NULL, 45, 50, 95.00, '2025-10-15 22:37:15', '2025-10-15 22:37:15'),
(77, '4337783023', 4, 'Pre-Assessment', NULL, 45, 50, 95.00, '2025-10-15 22:37:55', '2025-10-15 22:37:55'),
(78, '4389933922', 4, 'Pre-Assessment', NULL, 45, 50, 95.00, '2025-10-16 01:35:04', '2025-10-16 01:35:04'),
(79, '4389933922', 1, 'quiz', '2025-10-16', 23, 30, 85.00, '2025-10-16 01:35:54', '2025-10-18 09:33:47'),
(80, '4389933922', 1, 'quiz', '2025-10-16', 23, 40, 85.00, '2025-10-16 01:39:47', '2025-10-18 09:33:47'),
(81, '4389933922', 2, 'Present', '2025-10-18', 100, 100, 100.00, '2025-10-18 08:39:51', '2025-10-18 10:00:07'),
(82, '4337783023', 2, 'Present', '2025-10-18', 100, 100, 100.00, '2025-10-18 08:44:47', '2025-10-18 08:44:47'),
(83, '4389933922', 2, 'Present', '2025-10-19', 100, 100, 100.00, '2025-10-18 08:47:28', '2025-10-18 08:47:28'),
(84, '4389933922', 2, 'Absent', '2025-10-20', 50, 100, 50.00, '2025-10-18 08:47:50', '2025-10-18 08:47:50'),
(85, '4389933922', 2, 'Present', '2025-10-21', 100, 100, 100.00, '2025-10-18 08:50:25', '2025-10-18 08:50:25'),
(86, '8345893489', 2, 'Absent', '2025-10-21', 50, 100, 50.00, '2025-10-18 08:50:37', '2025-10-18 08:50:52'),
(87, '999999999', 2, 'Present', '2025-10-21', 100, 100, 100.00, '2025-10-18 08:50:53', '2025-10-18 08:50:53'),
(88, '8345893489', 2, 'Present', '2025-10-18', 100, 100, 100.00, '2025-10-18 08:53:10', '2025-10-18 08:53:10'),
(89, '4337783023', 2, 'Absent', '2025-10-19', 50, 100, 50.00, '2025-10-18 08:53:24', '2025-10-18 08:53:24'),
(90, '999999999', 2, 'Present', '2025-10-18', 100, 100, 100.00, '2025-10-18 09:21:12', '2025-10-18 09:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `instructors`
--

CREATE TABLE `instructors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `instructor_number` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `primary_course` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructors`
--

INSERT INTO `instructors` (`id`, `user_id`, `instructor_number`, `first_name`, `last_name`, `middle_name`, `email`, `primary_course`, `created_at`, `updated_at`) VALUES
(3, 79, '8345893489', 'drin', 'ino', '', 'inocenciojohnaldrin@gmail.com', 'RAC Servicing', '2025-10-13 14:07:14', '2025-10-13 14:07:14'),
(5, 83, '43534535', 'leemarc', 'vincoy', '', 'leemarc.vincoy@gmail.com', 'Electrical Installation and Maintenance', '2025-10-15 05:18:44', '2025-10-15 05:18:44'),
(6, 84, '4456456554', 'lebron', 'satumba', '', 'lebrondeidree.satumba@my.jru.edu', 'RAC Servicing', '2025-10-15 05:41:11', '2025-10-15 05:41:11'),
(8, 93, '439834893', 'John Aldrin', 'Inocencio', '', 'johnaldrin.inocencio@my.jru.edu', 'RAC Servicing', '2025-10-18 08:17:18', '2025-10-18 08:17:18');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `company` varchar(150) NOT NULL,
  `location` varchar(150) NOT NULL,
  `salary` varchar(100) DEFAULT NULL,
  `experience` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `company`, `location`, `salary`, `experience`, `description`, `course`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Software Engineer', 'CISCO', 'Diyan lang', 'basta walang tax', '50yrs', 'bahala ka', NULL, 0, '2025-09-22 08:52:44', '2025-09-26 09:11:51'),
(2, 'asdf', 'asdf', 'asdf', '234', '3', '', NULL, 0, '2025-09-26 09:16:18', '2025-09-26 09:16:24'),
(3, 'sf', 'asdf', 'asdf', 'asfd', 'sdaf', 'asdf', NULL, 0, '2025-09-26 09:16:44', '2025-09-26 09:16:46'),
(4, 'asdf', 'asdf', 'sdfa', 'sdafasd', 'a', 'sdfads', NULL, 0, '2025-09-26 09:20:06', '2025-09-26 09:20:11'),
(5, 'asd', 'sadf', 'safd', 'asfd', 'asfd', 'fdsasdf', NULL, 0, '2025-09-26 09:28:53', '2025-09-26 09:34:55'),
(6, 'Contractor', 'Sunwest Construction and Development Corporation', 'Taguig City', '2,000,000', 'No experience needed', '', NULL, 0, '2025-09-26 09:47:32', '2025-10-15 05:36:04'),
(7, 'Contractor', 'Sunwest Construction and Development Corporation', 'Bulacan', '5,000,000', 'No experience needed', '', 'BASIC COMPUTER LITERACY (BCL)', 0, '2025-09-26 09:50:27', '2025-10-15 05:36:02'),
(8, 'Contractor', 'SCDC', 'Negros Occidental', '10,000,000', 'No experience needed', '', 'DRESSMAKING (DRM)', 0, '2025-09-26 09:56:44', '2025-10-15 05:36:01'),
(9, 'Engineer', 'SCDC', 'Marawi', '500,000,000', 'No experience needed', '', 'SHIELDED METAL ARC WELDING (SMAW)', 0, '2025-09-26 10:04:52', '2025-10-15 05:35:59'),
(10, 'Waching Machine Fixer', 'Haenz', 'Mandaluyong City', '500', '1-2 years experience needed', '', 'AUTOMOTIVE SERVICING (ATS)', 0, '2025-09-28 06:53:58', '2025-10-15 05:35:57'),
(11, 'Embalsamador', 'St. Peterskie', 'Pasig City', '100,000', '3 or more years experience needed', '', 'DRESSMAKING (DRM)', 0, '2025-09-28 07:04:06', '2025-10-10 08:17:26'),
(12, 'asfdsfad', 'a', 'safdsad', '321321', '1-2 years experience needed', '', 'BREAD AND PASTRY PRODUCTION (BPP)', 0, '2025-10-01 15:42:57', '2025-10-01 16:06:45'),
(13, 'Aircon Tube Sipping', 'Sipsip Poso', 'Balintawak', '2,500,000', '3 or more years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-01 17:02:08', '2025-10-10 08:17:34'),
(14, 'adfd', 'sadf', 'asdf', 'sd', 'No experience needed', 'asdf', 'SHIELDED METAL ARC WELDING (SMAW)', 0, '2025-10-01 17:06:14', '2025-10-01 17:06:45'),
(15, 'Baking', 'Julies Bakeshop', 'Mandaluyong', '10,000', '3 or more years experience needed', '', 'FOOD AND BEVERAGE SERVICES (FBS)', 0, '2025-10-04 15:05:19', '2025-10-06 14:21:49'),
(16, 'aaaaaaaaaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaaaaaaa', '32233333333333', '1-2 years experience needed', '', 'AUTOMOTIVE SERVICING (ATS)', 0, '2025-10-06 15:13:19', '2025-10-06 15:14:11'),
(17, 'zxvzxcvx', 'zxvzxcvx', 'zxvzxcvx', 'zxvzxcvx', '1-2 years experience needed', 'zxvzxcvx', 'AUTOMOTIVE SERVICING (ATS)', 0, '2025-10-07 15:33:53', '2025-10-07 16:07:23'),
(18, 'ASDFSDFSDFSDF', 'ASDFSDFSDFSDF', 'ASDFSDFSDFSDF', '32312213', 'No experience needed', '', 'COMPUTER SYSTEMS SERVICING (CSS)', 0, '2025-10-07 16:07:05', '2025-10-07 16:07:30'),
(19, 'cacaca', 'cacaca', 'cacaca', '1312321', 'No experience needed', '', 'SHIELDED METAL ARC WELDING (SMAW)', 0, '2025-10-08 10:23:36', '2025-10-08 17:04:47'),
(20, 'asdfasdfsa', 'sadfsdaf', 'sadfas', '24324', 'No experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-08 17:04:59', '2025-10-10 08:17:25'),
(21, 'asdfsadfsafa', 'asdfadsfadsf', 'asdfadsfadsf', '2432432', '1-2 years experience needed', '', 'COMPUTER SYSTEMS SERVICING (CSS)', 0, '2025-10-08 17:05:47', '2025-10-10 08:17:16'),
(22, 'SAMPLE', 'SAMPLE', 'SAMPLE', 'SAMPLE', 'No experience needed', 'SAMPLE', 'AUTOMOTIVE SERVICING (ATS)', 0, '2025-10-09 13:18:15', '2025-10-15 05:31:08'),
(23, 'asdf', 'sdaf', 'asdf', 'asdf', 'No experience needed', 'asdf', 'AUTOMOTIVE SERVICING (ATS)', 0, '2025-10-10 23:30:26', '2025-10-11 00:56:23'),
(24, 'asdfasd', 'sdfds', 'sdaf', '345345', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-13 14:27:14', '2025-10-15 05:31:07'),
(25, 'aaaaaaaaaa', 'sdf', 'dsfasdf', '33333333', 'No experience needed', 'dasf', 'RAC SERVICING (RAC)', 0, '2025-10-13 14:27:34', '2025-10-15 05:31:05'),
(26, 'sadfasdf', 'sadfasdf', 'sadfasdf', '345', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 12:59:46', '2025-10-15 05:31:04'),
(27, 'nnguuuuu', 'nnguuuuu', 'nnguuuuu', '23423', 'No experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:05:22', '2025-10-15 05:31:02'),
(28, 'nnguuuuu', 'nnguuuuu', 'nnguuuuu', '23423', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:05:29', '2025-10-15 05:31:01'),
(29, 'nnguuuuu', 'nnguuuuu', 'nnguuuuu', '23423', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:05:36', '2025-10-15 02:28:33'),
(30, 'nnguuuuu', 'nnguuuuu', 'nnguuuuu', '23423', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:05:42', '2025-10-15 02:28:31'),
(31, 'nnguuuuu', 'nnguuuuu', 'nnguuuuu', '23423', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:05:49', '2025-10-15 02:28:29'),
(32, 'check', 'malala', 'chec', '23942834', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:06:14', '2025-10-15 02:28:25'),
(33, 'manok', 'chicken', 'bituka ng manak', '6000', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 0, '2025-10-14 13:09:28', '2025-10-15 02:28:27'),
(34, 'Truck Mechanic', '2GO Travel', 'Mandaluyong', '35,000.00', '1-2 years experience needed', '', 'AUTOMOTIVE SERVICING (ATS)', 1, '2025-10-15 05:41:17', '2025-10-15 05:41:17'),
(35, 'Maintinace', 'Lancaster Hotel', 'Mandaluyong', '30,000.00', '3 or more years experience needed', '', 'HOUSEKEEPING (HSK)', 1, '2025-10-15 05:42:56', '2025-10-15 05:42:56'),
(36, 'Truck Mechanic', 'Course: AUTOMOTIVE SERVICING (ATS)', 'Makati', '35,000.00', '1-2 years experience needed', '', '', 0, '2025-10-15 05:46:44', '2025-10-15 05:48:08'),
(37, 'Truck Mechanic', 'Course: AUTOMOTIVE SERVICING (ATS)', 'Makati', '35,000.00', '1-2 years experience needed', '', '', 0, '2025-10-15 05:46:55', '2025-10-15 05:48:08'),
(38, 'Event Manager', 'Edsa Shangri-La', 'Mandaluyong', '25,000.00 - 30,000.00', '1-2 years experience needed', '', 'EVENTS MANAGEMENT SERVICES (EVM)', 1, '2025-10-15 05:47:57', '2025-10-15 05:47:57'),
(39, 'Assistant IT', 'SM Prime Holding', 'Pasay', '30,000.00', '1-2 years experience needed', '', 'COMPUTER SYSTEMS SERVICING (CSS)', 0, '2025-10-15 05:52:53', '2025-10-15 06:55:59'),
(40, 'Room Attendant', 'Rockwell Holdings And Properties', 'Makati', '15,000 –  20,000/month', '1-2 years experience needed', '', 'HAIRDRESSING (HDR)', 1, '2025-10-15 06:07:58', '2025-10-15 06:07:58'),
(41, 'Food Processing Technician', 'PVL Food Center', 'Caloocan City', '14,000 – 19,000/month', 'No experience needed', '', 'FOOD PROCESSING (FOP)', 1, '2025-10-15 06:09:16', '2025-10-15 06:09:16'),
(42, 'Garment Sewer / Dressmaker', 'Onesimus', 'Mandaluyong', '14,000 –  19,000/month', '1-2 years experience needed', '', 'DRESSMAKING (DRM)', 1, '2025-10-15 06:12:25', '2025-10-15 06:12:25'),
(43, 'Junior Baker', 'Tous Le Jous', 'Mandaluyong', '14,000 – 18,000/month', 'No experience needed', '', 'BREAD AND PASTRY PRODUCTION (BPP)', 1, '2025-10-15 06:14:25', '2025-10-15 06:14:25'),
(44, 'Event Manager', 'Course: EVENTS MANAGEMENT SERVICES (EVM)', 'Mandaluyong', '25,000.00 - 30,000.00/month', '1-2 years experience needed', '', '', 0, '2025-10-15 06:15:00', '2025-10-15 06:28:36'),
(45, 'Event Manager', 'Course: EVENTS MANAGEMENT SERVICES (EVM)', 'Mandaluyong', '25,000.00 - 30,000.00/month', '1-2 years experience needed', '', '', 0, '2025-10-15 06:17:12', '2025-10-15 06:28:20'),
(46, 'Electrical Technician', 'Filcartoons', 'Mandaluyong', '18,000 – 25,000/month', '3 or more years experience needed', '', 'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)', 1, '2025-10-15 06:28:05', '2025-10-15 06:28:05'),
(47, 'Barista', 'Socialite Café Incorporated', 'Pasig', '18,000 – PHP 25,000/month + tips', '3 or more years experience needed', '', 'FOOD AND BEVERAGE SERVICES (FBS)', 1, '2025-10-15 06:33:37', '2025-10-15 06:33:37'),
(48, 'Butcher', 'All About Chicken Inc.', 'Caloocan', '14,000 – PHP 18,000/month', '1-2 years experience needed', '', 'FOOD PROCESSING (FOP)', 1, '2025-10-15 06:35:57', '2025-10-15 06:35:57'),
(49, 'Aircon Technician', 'QuickFix Aircon Industries', 'Manila', '16,000 – PHP 22,000/month', '1-2 years experience needed', '', 'RAC SERVICING (RAC)', 1, '2025-10-15 06:41:51', '2025-10-15 06:41:51'),
(50, 'Electrical Maintenance Technician', 'ColdAire Industries', 'Pasig', 'PHP 17,000 – PHP 25,000/month', '3 or more years experience needed', '', 'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)', 1, '2025-10-15 06:43:06', '2025-10-15 06:43:06'),
(51, 'Food & Beverage Service Crew', 'Goldilocks Bakeshop', 'Mandaluyong', '14,000 – PHP 18,000/month', '1-2 years experience needed', '', 'FOOD AND BEVERAGE SERVICES (FBS)', 1, '2025-10-15 06:44:13', '2025-10-15 06:44:13'),
(52, 'IT Support Technician', 'AXN', 'Taguig', '20,000 – PHP 28,000/month', '1-2 years experience needed', '', 'COMPUTER SYSTEMS SERVICING (CSS)', 1, '2025-10-15 06:45:28', '2025-10-15 06:45:28'),
(53, 'Office Assistant / Data Encoder', 'Marvel One', 'Quezon City', '13,000 – 17,000/month', '1-2 years experience needed', '', 'BASIC COMPUTER LITERACY (BCL)', 1, '2025-10-15 06:53:18', '2025-10-15 06:53:18'),
(54, 'Assistant IT', 'Course: COMPUTER SYSTEMS SERVICING (CSS)', 'Pasay', '30,000.00/month', '1-2 years experience needed', '', '', 1, '2025-10-15 06:55:09', '2025-10-15 06:55:09'),
(55, 'Event Manager', 'Course: EVENTS MANAGEMENT SERVICES (EVM)', 'Mandaluyong', '25,000.00 - 30,000.00/month', '1-2 years experience needed', '', '', 0, '2025-10-15 06:55:15', '2025-10-15 06:55:48');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `student_number` varchar(20) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mmtvtc_users`
--

CREATE TABLE `mmtvtc_users` (
  `id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `is_role` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `mmtvtc_users`
--

INSERT INTO `mmtvtc_users` (`id`, `student_number`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `otp_code`, `otp_expires_at`, `created_at`, `is_verified`, `is_role`, `verification_token`, `token_expiry`, `reset_token`, `reset_token_expires_at`, `failed_attempts`, `locked_until`, `last_login`, `last_login_ip`) VALUES
(1, '9563211331', 'Aldrin', NULL, 'Inocencio', 'aldrininocencio212527@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$WTU5dkhjeUFCNE53R3JNbA$g0GDfB+xHeD9KuhKySfYfqO+0Ldw6LbqNCoU5yqYqQI', NULL, NULL, '2025-05-21 12:31:16', 1, 2, NULL, NULL, NULL, NULL, 0, NULL, '2025-10-18 08:16:29', '2a09:bac1:5ae0:8::2e4:a4'),
(54, '999999999', 'Ian', '', 'Betorio', 'ianemersonb@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$Yi9sRmNOdHdhZlNNRndWcg$ySpnMVUkAUd+FIc5vs3/u30XSvVSSEBUQOf9l1Ui3gk', NULL, NULL, '2025-10-04 14:04:22', 1, 1, NULL, NULL, '3503c9d2b76d5f0a7031aa72c7f7757b1af4aa29959e3a0c387160a3bc87727e', '2025-10-15 12:20:04', 0, NULL, '2025-10-18 09:56:30', '103.254.214.141'),
(79, '8345893489', 'drin', '', 'ino', 'inocenciojohnaldrin@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$NmczUHQvOU0wWmRHbUlyNw$IUZxKu1hnvtqG9S/AXzIuAh7FYexYlotTQqE9j5Q5rA', NULL, NULL, '2025-10-13 14:07:14', 1, 0, NULL, NULL, NULL, NULL, 0, NULL, '2025-10-14 13:04:49', '136.158.58.89'),
(83, '43534535', 'leemarc', '', 'vincoy', 'leemarc.vincoy@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$UnF6M1FUSzlwRUpBWjVRNg$2CahGtYtcEPetI2jW7S3YQOjkiWCQmxB0p8GNQJ2Wqc', NULL, NULL, '2025-10-15 05:18:44', 1, 0, NULL, NULL, NULL, NULL, 0, NULL, '2025-10-15 07:09:55', '180.190.63.233'),
(84, '4456456554', 'lebron', '', 'satumba', 'lebrondeidree.satumba@my.jru.edu', '$argon2id$v=19$m=65536,t=4,p=3$alNvNFdlZTg0eEZuaGtkMw$XRbxzd9hTsZ1OXbGRC3a0JYfY9iHOGNb7ztGoKT/Ejc', NULL, NULL, '2025-10-15 05:41:11', 1, 1, NULL, NULL, NULL, NULL, 0, NULL, '2025-10-16 02:12:27', '154.205.22.57'),
(86, '4337783023', 'Patricia', '', 'Go', 'greycruz0000000@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$TmZ6ZGxVYnc4UGVFaEhJaQ$folori7jYAGVwoi0aqoKYdl+HMwwM6Stg4Lwb13nkCk', NULL, NULL, '2025-10-15 21:14:14', 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(87, '4389933922', 'Grey', '', 'Cruz', 'greycruz0000000000@gmail.com', '$argon2id$v=19$m=65536,t=4,p=3$eko5amNFSUdaSm9aOTkvNg$ffDoYCRTQy427ObP2AeCBnOjSqOMr5zOiKDJZyLcmSY', NULL, NULL, '2025-10-15 21:16:02', 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(91, '12385243432', 'Leemarc', 'G.', 'Vincoy', 'leemarcruss.vincoy@my.jru.edu', '', NULL, NULL, '2025-10-16 02:06:55', 0, 0, NULL, NULL, '59cb6a940110df810d64f06b8e76ad5051ff19d7d2db0252978198eeb8b9d278', '2025-10-16 02:16:55', 0, NULL, NULL, NULL),
(92, '64425685', 'Leemarc', 'B.', 'Vincoy', 'leemarc.vincoy26@gmail.com', '', NULL, NULL, '2025-10-16 02:10:50', 0, 0, NULL, NULL, '3782ede03e8bc7edf9b3e737ddde36b37c087b73af794a181d4eb27b3e6c0f40', '2025-10-16 02:20:50', 0, NULL, NULL, NULL),
(93, '439834893', 'John Aldrin', '', 'Inocencio', 'johnaldrin.inocencio@my.jru.edu', '$argon2id$v=19$m=65536,t=4,p=3$ZWQ0ZTBibjRFU0FOamhtNA$ObuHOOwtI0Y031zpwRplHQI3TWZAns872wc2LWbYaUE', NULL, NULL, '2025-10-18 08:17:18', 1, 1, NULL, NULL, NULL, NULL, 0, NULL, '2025-10-18 10:24:42', '2a09:bac5:4fa4:1d05::2e4:a4');

-- --------------------------------------------------------

--
-- Table structure for table `nc2_validations`
--

CREATE TABLE `nc2_validations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course` varchar(150) DEFAULT NULL,
  `nc2_link` text NOT NULL,
  `status` enum('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nc2_validations`
--

INSERT INTO `nc2_validations` (`id`, `student_id`, `course`, `nc2_link`, `status`, `admin_id`, `confirmed_at`, `created_at`, `updated_at`) VALUES
(4, 7, 'RAC Servicing', 'https://google.com', 'confirmed', 1, '2025-10-14 12:58:48', '2025-10-14 12:58:39', '2025-10-14 12:58:48'),
(5, 3, 'RAC Servicing', 'https://google.com', 'confirmed', 1, '2025-10-16 02:23:52', '2025-10-16 02:20:13', '2025-10-16 02:23:52');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `time_display` varchar(100) NOT NULL,
  `custom_time` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `icon`, `message`, `type`, `time_display`, `custom_time`, `created_at`, `updated_at`) VALUES
(43, 'Account Successfully Created', 'user-plus', 'Welcome to MMTVTC! Your student account has been activated.', 'info', 'Just now', '', '2025-10-15 21:24:30', '2025-10-15 21:24:30'),
(44, 'NC II Submission Approved', 'check-circle', 'Your TESDA NC II certification has been verified and approved. You can now access job opportunities in the Job Matching module.', 'success', 'Just now', '', '2025-10-15 21:24:50', '2025-10-15 21:24:50'),
(45, 'New Job Opportunity Added', 'info-circle', 'A new job post has been added that matches your course qualifications. Visit the Job Matching section to explore details.', 'info', 'Just now', '', '2025-10-15 21:25:02', '2025-10-15 21:25:02'),
(46, 'Upcoming Orientation Reminder', 'bullhorn', 'Don’t forget! The MMTVTC orientation for new trainees is scheduled on October 27, 2000 3:00 PM. Attendance is required.', 'info', 'Just now', '', '2025-10-15 21:25:51', '2025-10-15 21:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `otp_requests`
--

CREATE TABLE `otp_requests` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `request_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification_attempts`
--

CREATE TABLE `otp_verification_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_history`
--

CREATE TABLE `password_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_history`
--

INSERT INTO `password_history` (`id`, `user_id`, `password_hash`, `created_at`) VALUES
(2, 1, '$argon2id$v=19$m=65536,t=4,p=3$VkF1MVBJQmNEcDhrbWUzOA$dH++3TtDxZePJ5TNzdnXkVS5DlC6MUobKXzYpI/iT6U', '2025-10-07 17:05:19');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','paragraph','short_answer','checkbox') NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `question_order` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_question_options`
--

CREATE TABLE `quiz_question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_submissions`
--

CREATE TABLE `quiz_submissions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `score` decimal(5,2) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) DEFAULT 0,
  `status` enum('in_progress','submitted','graded') DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL DEFAULT 'general',
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_number` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `first_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `course` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `profile_photo` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `first_name`, `last_name`, `middle_name`, `email`, `course`, `profile_photo`, `created_at`, `updated_at`) VALUES
(3, 54, '999999999', 'Ian', 'Betorio', '', 'ianemersonb@gmail.com', 'RAC Servicing', '/images/avatars/stu_54_1759690583.jpg', '2025-10-04 14:04:43', '2025-10-16 02:25:30'),
(4, 1, '9563211331', 'Aldrin', 'Inocencio', '', 'aldrininocencio212527@gmail.com', NULL, '/images/avatars/stu_1_1759762428.jpg', '2025-10-05 17:36:06', '2025-10-15 08:25:24'),
(7, 79, '8345893489', 'drin', 'ino', '', 'inocenciojohnaldrin@gmail.com', 'RAC Servicing', NULL, '2025-10-14 12:58:19', '2025-10-14 13:05:04'),
(8, 86, '4337783023', 'Patricia', 'Go', '', 'greycruz0000000@gmail.com', 'RAC Servicing', NULL, '2025-10-15 21:14:52', '2025-10-15 21:14:52'),
(9, 87, '4389933922', 'Grey', 'Cruz', '', 'greycruz0000000000@gmail.com', 'RAC Servicing', NULL, '2025-10-15 21:16:22', '2025-10-15 21:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `student_grades`
--

CREATE TABLE `student_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `raw_score` decimal(5,2) NOT NULL,
  `transmuted_grade` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `abuse_tracking`
--
ALTER TABLE `abuse_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`,`identifier_type`),
  ADD KEY `idx_ip_action` (`ip_address`,`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_locked_until` (`locked_until`),
  ADD KEY `idx_lockout_count` (`lockout_count`);

--
-- Indexes for table `add_trainees`
--
ALTER TABLE `add_trainees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_add_trainees_email` (`email`),
  ADD KEY `idx_course` (`course`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_enrolled` (`date_enrolled`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_add_trainees_creator` (`created_by`);

--
-- Indexes for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_admin_alerts_reviewer` (`reviewed_by`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_active` (`is_active`),
  ADD KEY `idx_announcements_type` (`type`),
  ADD KEY `idx_announcements_date` (`date_created`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_type_id` (`assessment_type_id`);

--
-- Indexes for table `assessment_types`
--
ALTER TABLE `assessment_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `computed_grades`
--
ALTER TABLE `computed_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_quarter` (`student_id`,`quarter`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_instructor` (`instructor_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `exam_answers`
--
ALTER TABLE `exam_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission_question` (`submission_id`,`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`),
  ADD KEY `idx_submission` (`submission_id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam` (`exam_id`),
  ADD KEY `idx_order` (`question_order`);

--
-- Indexes for table `exam_question_options`
--
ALTER TABLE `exam_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question` (`question_id`),
  ADD KEY `idx_order` (`option_order`);

--
-- Indexes for table `exam_submissions`
--
ALTER TABLE `exam_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_exam_student` (`exam_id`,`student_id`),
  ADD KEY `idx_exam` (`exam_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `grade_categories`
--
ALTER TABLE `grade_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grade_details`
--
ALTER TABLE `grade_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_grade` (`student_number`,`grade_number`);

--
-- Indexes for table `instructors`
--
ALTER TABLE `instructors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user` (`user_id`),
  ADD UNIQUE KEY `uniq_instructor_number` (`instructor_number`),
  ADD UNIQUE KEY `uniq_instructor_email` (`email`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mmtvtc_users`
--
ALTER TABLE `mmtvtc_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `nc2_validations`
--
ALTER TABLE `nc2_validations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`request_time`),
  ADD KEY `idx_ip_time` (`ip`,`request_time`);

--
-- Indexes for table `otp_verification_attempts`
--
ALTER TABLE `otp_verification_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip`,`attempt_time`);

--
-- Indexes for table `password_history`
--
ALTER TABLE `password_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_instructor` (`instructor_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission_question` (`submission_id`,`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`),
  ADD KEY `idx_submission` (`submission_id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz` (`quiz_id`),
  ADD KEY `idx_order` (`question_order`);

--
-- Indexes for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question` (`question_id`),
  ADD KEY `idx_order` (`option_order`);

--
-- Indexes for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_quiz_student` (`quiz_id`,`student_id`),
  ADD KEY `idx_quiz` (`quiz_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action` (`ip`,`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_rate_limits_user` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_assessment` (`student_id`,`assessment_id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `abuse_tracking`
--
ALTER TABLE `abuse_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=462;

--
-- AUTO_INCREMENT for table `add_trainees`
--
ALTER TABLE `add_trainees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_types`
--
ALTER TABLE `assessment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `computed_grades`
--
ALTER TABLE `computed_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_answers`
--
ALTER TABLE `exam_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_question_options`
--
ALTER TABLE `exam_question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_submissions`
--
ALTER TABLE `exam_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grade_categories`
--
ALTER TABLE `grade_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `grade_details`
--
ALTER TABLE `grade_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `instructors`
--
ALTER TABLE `instructors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `mmtvtc_users`
--
ALTER TABLE `mmtvtc_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `nc2_validations`
--
ALTER TABLE `nc2_validations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `otp_requests`
--
ALTER TABLE `otp_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verification_attempts`
--
ALTER TABLE `otp_verification_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_history`
--
ALTER TABLE `password_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `add_trainees`
--
ALTER TABLE `add_trainees`
  ADD CONSTRAINT `fk_add_trainees_creator` FOREIGN KEY (`created_by`) REFERENCES `mmtvtc_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `admin_alerts`
--
ALTER TABLE `admin_alerts`
  ADD CONSTRAINT `fk_admin_alerts_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `mmtvtc_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `fk_assessments_type` FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_types` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `assessment_types`
--
ALTER TABLE `assessment_types`
  ADD CONSTRAINT `fk_assessment_types_category` FOREIGN KEY (`category_id`) REFERENCES `grade_categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `computed_grades`
--
ALTER TABLE `computed_grades`
  ADD CONSTRAINT `fk_computed_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_answers`
--
ALTER TABLE `exam_answers`
  ADD CONSTRAINT `exam_answers_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `exam_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `exam_question_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_question_options`
--
ALTER TABLE `exam_question_options`
  ADD CONSTRAINT `exam_question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_submissions`
--
ALTER TABLE `exam_submissions`
  ADD CONSTRAINT `exam_submissions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `instructors`
--
ALTER TABLE `instructors`
  ADD CONSTRAINT `fk_instructors_user` FOREIGN KEY (`user_id`) REFERENCES `mmtvtc_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_history`
--
ALTER TABLE `password_history`
  ADD CONSTRAINT `fk_password_history_user` FOREIGN KEY (`user_id`) REFERENCES `mmtvtc_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `quiz_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `quiz_question_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  ADD CONSTRAINT `quiz_question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  ADD CONSTRAINT `quiz_submissions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD CONSTRAINT `fk_rate_limits_user` FOREIGN KEY (`user_id`) REFERENCES `mmtvtc_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user_id` FOREIGN KEY (`user_id`) REFERENCES `mmtvtc_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD CONSTRAINT `fk_student_grades_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
