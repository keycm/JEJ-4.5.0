-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 08, 2026 at 01:52 PM
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
-- Database: `eco_land`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_categories`
--

CREATE TABLE `accounting_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `type` enum('INCOME','EXPENSE') NOT NULL DEFAULT 'EXPENSE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounting_categories`
--

INSERT INTO `accounting_categories` (`id`, `name`, `group_name`, `type`) VALUES
(15, 'Lot Sales', 'Income', 'INCOME');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(26, 1, 'Approved Reservation & Recorded Income', 'Res ID: 5 | Amount: ₱8,095,591,062.00', '2026-03-27 07:24:00'),
(51, 1, 'Exported Transactions to Excel', '', '2026-03-29 04:09:38'),
(52, 1, 'Issued Check Voucher', 'CV: CV-20260329-0001 | Payee: vuf sddf | Amount: ₱54,432,323.00', '2026-03-29 09:38:36'),
(53, 1, 'Exported Transactions to Excel', '', '2026-03-29 09:51:40'),
(54, 1, 'Exported Transactions to Excel', '', '2026-03-29 11:28:23'),
(55, 1, 'Added New Property', 'Lot ID: 15 | Block: 12345, Lot: 1 | Location: SAN MIGUEL(CAMBIO)', '2026-03-31 01:51:13'),
(56, 1, 'Added New Property', 'Lot ID: 16 | Block: 12345, Lot: 1 | Location: SAN MIGUEL(CAMBIO)', '2026-03-31 02:32:22'),
(57, 1, 'Updated Property', 'Lot ID: 16 | Block: 12345, Lot: 1 | Status: RESERVED', '2026-04-01 04:53:30'),
(58, 1, 'Updated Property', 'Lot ID: 16 | Block: 12345, Lot: 1 | Status: AVAILABLE', '2026-04-01 04:54:39'),
(59, 1, 'Approved Reservation & Recorded Income', 'Res ID: 11 | Amount: ₱200,000.00', '2026-04-01 04:55:25'),
(60, 1, 'Updated Property', 'Lot ID: 16 | Block: 1, Lot: 1 | Status: RESERVED', '2026-04-01 04:58:33'),
(61, 1, 'Updated Property', 'Lot ID: 16 | Block: 1, Lot: 1 | Status: AVAILABLE', '2026-04-01 05:00:10'),
(62, 1, 'Approved Reservation & Recorded Income', 'Res ID: 12 | Amount: ₱3,700,000.00', '2026-04-01 05:01:08'),
(63, 1, 'Updated Payment Terms', 'Res ID: 12 | Term: INSTALLMENT', '2026-04-01 05:01:40'),
(64, 1, 'Processed POS Transaction', 'OR: OR-20260401-0003 | Type: INCOME | Amount: ₱24,456.00', '2026-04-01 05:31:00'),
(65, 1, 'Added New Property', 'Lot ID: 17 | Block: 12345, Lot: 7675 | Location: SAN MIGUEL', '2026-04-05 06:31:03'),
(66, 1, 'Updated Payment Terms', 'Res ID: 12 | Term: INSTALLMENT', '2026-04-05 07:36:52'),
(67, 1, 'Added New Property', 'Lot ID: 18 | Block: 12345, Lot: 7675 | Location: San vicente', '2026-04-05 07:48:18'),
(68, 1, 'Updated Property', 'Lot ID: 17 | Block: 12345, Lot: 7675 | Status: RESERVED', '2026-04-05 07:49:50'),
(69, 1, 'Bulk Added Properties', 'Added 20 lots to Block 1 in SAN MIGUEL(CAMBIO)', '2026-04-05 07:58:12'),
(70, 1, 'Updated Property', 'Lot ID: 19 | Block: 1, Lot: 1 | Status: AVAILABLE', '2026-04-05 08:12:04'),
(71, 1, 'Updated Property', 'Lot ID: 20 | Block: 1, Lot: 2 | Status: AVAILABLE', '2026-04-05 08:12:26'),
(72, 1, 'Approved Reservation & Recorded Income', 'Res ID: 13 | Amount: ₱3,500,000.00', '2026-04-05 12:37:54'),
(73, 1, 'Updated Payment Terms', 'Res ID: 13 | Term: CASH', '2026-04-05 12:38:39'),
(74, 1, 'Approved Reservation & Recorded Income', 'Res ID: 14 | Amount: ₱3,500,000.00', '2026-04-05 14:18:05'),
(75, 1, 'Updated Payment Terms', 'Res ID: 14 | Term: CASH', '2026-04-05 14:18:18'),
(76, 1, 'Updated Payment Terms', 'Res ID: 14 | Term: INSTALLMENT', '2026-04-05 14:18:28'),
(77, 1, 'Approved Reservation & Recorded Income', 'Res ID: 15 | Amount: ₱3,500,000.00', '2026-04-05 14:47:59'),
(78, 1, 'Updated Payment Terms', 'Res ID: 15 | Term: CASH', '2026-04-05 14:48:17');

-- --------------------------------------------------------

--
-- Table structure for table `delete_history`
--

CREATE TABLE `delete_history` (
  `id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_data` text NOT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_hidden` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('UNREAD','READ','RESPONDED') DEFAULT 'UNREAD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lots`
--

CREATE TABLE `lots` (
  `id` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phase_id` int(11) DEFAULT NULL,
  `block_no` varchar(10) DEFAULT NULL,
  `lot_no` varchar(10) DEFAULT NULL,
  `area` decimal(10,2) DEFAULT NULL,
  `price_per_sqm` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(12,2) DEFAULT NULL,
  `status` enum('AVAILABLE','RESERVED','SOLD') DEFAULT 'AVAILABLE',
  `property_overview` text DEFAULT NULL,
  `coordinates` varchar(50) DEFAULT NULL,
  `lot_image` varchar(255) DEFAULT 'default_lot.jpg',
  `property_type` enum('Subdivision','Lot','Land','Farm','Shop','Business') DEFAULT 'Subdivision',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `points` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lots`
--

INSERT INTO `lots` (`id`, `location`, `phase_id`, `block_no`, `lot_no`, `area`, `price_per_sqm`, `total_price`, `status`, `property_overview`, `coordinates`, `lot_image`, `property_type`, `latitude`, `longitude`, `points`) VALUES
(16, 'SAN MIGUEL(CAMBIO)', NULL, '1', '1', 1000.00, 3700.00, 3700000.00, 'SOLD', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: Spot Cash Payment\n\n📌 [PRICING CONFIGURATION]\r\nClassification: Front Lot\r\nPayment Terms: 3 Years Installment\r\n\r\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: Spot Cash Payment\r\n\r\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: Spot Cash Payment\r\n\r\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: Spot Cash Payment\r\n\r\n', '510.4,343.7 553.1,365.7 507.8,430.5 469,405.9', '', 'Lot', 14.96473899, 120.62037791, NULL),
(17, 'SAN MIGUEL', NULL, '12345', '7675', 9765.00, 797.00, 7782705.00, 'RESERVED', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: Spot Cash Payment\n\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: Spot Cash Payment\r\n\r\nokl', NULL, '', '', 14.58684936, 121.22228371, NULL),
(18, 'San vicente', NULL, '12345', '7675', 9765.00, 797.00, 7782705.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: Spot Cash Payment\n\n', NULL, '', '', NULL, NULL, NULL),
(19, 'SAN MIGUEL(CAMBIO)', NULL, '1', '1', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: Spot Cash Payment\n\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: 3 Years Installment\r\n\r\n', NULL, '1775376724_8-single-fillable-bracket.png', 'Lot', NULL, NULL, NULL),
(20, 'SAN MIGUEL(CAMBIO)', NULL, '1', '2', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: Spot Cash Payment\n\n📌 [PRICING CONFIGURATION]\r\nClassification: Inner Lot\r\nPayment Terms: 3 Years Installment\r\n\r\n', NULL, '1775376746_20240524_101700.png', 'Lot', NULL, NULL, NULL),
(21, 'SAN MIGUEL(CAMBIO)', NULL, '1', '3', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(22, 'SAN MIGUEL(CAMBIO)', NULL, '1', '4', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(23, 'SAN MIGUEL(CAMBIO)', NULL, '1', '5', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(24, 'SAN MIGUEL(CAMBIO)', NULL, '1', '6', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(25, 'SAN MIGUEL(CAMBIO)', NULL, '1', '7', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(26, 'SAN MIGUEL(CAMBIO)', NULL, '1', '8', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(27, 'SAN MIGUEL(CAMBIO)', NULL, '1', '9', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(28, 'SAN MIGUEL(CAMBIO)', NULL, '1', '10', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(29, 'SAN MIGUEL(CAMBIO)', NULL, '1', '11', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(30, 'SAN MIGUEL(CAMBIO)', NULL, '1', '12', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(31, 'SAN MIGUEL(CAMBIO)', NULL, '1', '13', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(32, 'SAN MIGUEL(CAMBIO)', NULL, '1', '14', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(33, 'SAN MIGUEL(CAMBIO)', NULL, '1', '15', 1000.00, 3500.00, 3500000.00, 'SOLD', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(34, 'SAN MIGUEL(CAMBIO)', NULL, '1', '16', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(35, 'SAN MIGUEL(CAMBIO)', NULL, '1', '17', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(36, 'SAN MIGUEL(CAMBIO)', NULL, '1', '18', 1000.00, 3500.00, 3500000.00, 'AVAILABLE', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(37, 'SAN MIGUEL(CAMBIO)', NULL, '1', '19', 1000.00, 3500.00, 3500000.00, 'SOLD', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL),
(38, 'SAN MIGUEL(CAMBIO)', NULL, '1', '20', 1000.00, 3500.00, 3500000.00, 'SOLD', '📌 [PRICING CONFIGURATION]\nClassification: Inner Lot\nPayment Terms: 3 Years Installment\n\n', NULL, '', 'Lot', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lot_gallery`
--

CREATE TABLE `lot_gallery` (
  `id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manager_permissions`
--

CREATE TABLE `manager_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `inv_full` tinyint(1) DEFAULT 0,
  `inv_property` tinyint(1) DEFAULT 0,
  `inv_status` tinyint(1) DEFAULT 0,
  `inv_price` tinyint(1) DEFAULT 0,
  `res_full` tinyint(1) DEFAULT 0,
  `res_process` tinyint(1) DEFAULT 0,
  `res_status` tinyint(1) DEFAULT 0,
  `res_terms` tinyint(1) DEFAULT 0,
  `fin_full` tinyint(1) DEFAULT 0,
  `fin_process` tinyint(1) DEFAULT 0,
  `fin_review` tinyint(1) DEFAULT 0,
  `fin_checks` tinyint(1) DEFAULT 0,
  `fin_accounts` tinyint(1) DEFAULT 0,
  `usr_full` tinyint(1) DEFAULT 0,
  `usr_buyers` tinyint(1) DEFAULT 0,
  `usr_promote` tinyint(1) DEFAULT 0,
  `usr_admins` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 4, 'Reservation Approved!', 'Your reservation for Block 1 Lot 19 is approved. Please settle your down payment within 20 days.', 1, '2026-04-05 14:18:05'),
(2, 4, 'Reservation Approved!', 'Your reservation for Block 1 Lot 15 is approved. Please settle your down payment within 20 days.', 1, '2026-04-05 14:48:01');

-- --------------------------------------------------------

--
-- Table structure for table `phases`
--

CREATE TABLE `phases` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `status` enum('ACTIVE','COMPLETED') DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `status`) VALUES
(3, 'General Operations', 'ACTIVE');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `reservation_date` datetime DEFAULT current_timestamp(),
  `status` enum('PENDING','APPROVED','CANCELLED') DEFAULT 'PENDING',
  `dp_proof` varchar(255) DEFAULT NULL,
  `dp_status` enum('UNPAID','VERIFYING','PAID') DEFAULT 'UNPAID',
  `payment_type` enum('CASH','INSTALLMENT') DEFAULT NULL,
  `installment_months` int(11) DEFAULT NULL,
  `monthly_payment` decimal(12,2) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `buyer_address` text DEFAULT NULL,
  `valid_id_file` varchar(255) DEFAULT NULL,
  `selfie_with_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `contact_number`, `email`, `birth_date`, `lot_id`, `reservation_date`, `status`, `dp_proof`, `dp_status`, `payment_type`, `installment_months`, `monthly_payment`, `payment_proof`, `notes`, `buyer_address`, `valid_id_file`, `selfie_with_id`) VALUES
(12, 1, '09667785843', NULL, '2026-04-14', 16, '2026-04-01 13:00:59', 'APPROVED', NULL, 'UNPAID', 'INSTALLMENT', 36, 82222.22, '1775019659_bg.jpg', NULL, 'Talang pulungmasle', '1775019659_bg3.png', '1775019659_bg2.png'),
(13, 1, '09667785843', NULL, '2026-04-14', 38, '2026-04-05 20:37:30', 'APPROVED', NULL, 'UNPAID', 'CASH', 0, 0.00, '1775392649_20240524_103910.png', NULL, 'Talang pulungmasle', '1775392649_20240524_101700.png', '1775392649_20240524_101709.png'),
(14, 4, '09667785843', NULL, '2026-04-14', 37, '2026-04-05 22:10:49', 'APPROVED', NULL, 'UNPAID', 'INSTALLMENT', 48, 58333.33, '1775398249_20240524_101709.png', NULL, 'Talang pulungmasle', '1775398249_20240524_101700.png', '1775398249_20240528_154755.png'),
(15, 4, '09667785843', NULL, '2026-04-14', 33, '2026-04-05 22:46:42', 'APPROVED', NULL, 'UNPAID', 'CASH', 0, 0.00, '1775400402_20240524_101700.png', NULL, 'Talang pulungmasle', '1775400402_Picture1.jpg', '1775400402_20240524_104408.png');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `or_number` varchar(50) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('INCOME','EXPENSE') NOT NULL,
  `category_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `payee` varchar(150) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `check_number` varchar(50) DEFAULT NULL,
  `is_check` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `reservation_id`, `or_number`, `transaction_date`, `type`, `category_id`, `project_id`, `amount`, `description`, `user_id`, `payee`, `bank_name`, `check_number`, `is_check`, `created_at`) VALUES
(33, NULL, 'OR-20260401-0001', '2026-04-01', 'INCOME', 15, 3, 200000.00, 'Payment for Lot (Block 12345 Lot 1) - Res#11', 1, NULL, NULL, NULL, 0, '2026-04-01 04:55:25'),
(34, NULL, 'OR-20260401-0002', '2026-04-01', 'INCOME', 15, 3, 3700000.00, 'Payment for Lot (Block 1 Lot 1) - Res#12', 1, NULL, NULL, NULL, 0, '2026-04-01 05:01:08'),
(35, 12, '4309345903470-39234', '2026-04-01', 'INCOME', 0, 0, 200000.00, 'Down Payment for Res#12 - BANk', 0, NULL, NULL, NULL, 0, '2026-04-01 05:03:03'),
(36, 12, 'DP-69CCA835C8AB1', '2026-04-01', 'INCOME', 0, 0, 540000.00, 'Down Payment for Res#12', 0, NULL, NULL, NULL, 0, '2026-04-01 05:08:05'),
(37, NULL, 'OR-20260401-0003', '2026-04-01', 'INCOME', 15, 3, 24456.00, '', 1, NULL, NULL, NULL, 0, '2026-04-01 05:31:00'),
(38, NULL, 'OR-20260405-0001', '2026-04-05', 'INCOME', 15, 3, 3500000.00, 'Payment for Lot (Block 1 Lot 20) - Res#13', 1, NULL, NULL, NULL, 0, '2026-04-05 12:37:54'),
(39, NULL, 'OR-20260405-0002', '2026-04-05', 'INCOME', 15, 3, 3500000.00, 'Payment for Lot (Block 1 Lot 19) - Res#14', 1, NULL, NULL, NULL, 0, '2026-04-05 14:18:05'),
(40, NULL, 'OR-20260405-0003', '2026-04-05', 'INCOME', 15, 3, 3500000.00, 'Payment for Lot (Block 1 Lot 15) - Res#15', 1, NULL, NULL, NULL, 0, '2026-04-05 14:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('SUPER ADMIN','ADMIN','MANAGER','AGENT','BUYER') DEFAULT 'BUYER',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `phone`, `role`, `created_at`) VALUES
(1, 'Super Admin', 'admin@test.com', '0192023a7bbd73250516f069df18b500', NULL, 'ADMIN', '2026-02-10 12:14:01'),
(2, 'Roms', 'admin@gmail.com', '7488e331b8b64e5794da3fa4eb10ad5d', '09667785843', 'BUYER', '2026-03-05 02:49:17'),
(4, 'Vincent paul D Pena', 'penapaul858@gmail.com', 'a44d52b99eafd5fdd094ad416a295f14', '0933 4257317', 'BUYER', '2026-03-27 07:03:12'),
(7, 'Vincent paul D Pena', 'keycm109@gmail.com', '3b813d6826a25b1980acfe32c7111366', '09667785843', 'BUYER', '2026-04-07 12:26:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_categories`
--
ALTER TABLE `accounting_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delete_history`
--
ALTER TABLE `delete_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lots`
--
ALTER TABLE `lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phase_id` (`phase_id`);

--
-- Indexes for table `lot_gallery`
--
ALTER TABLE `lot_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Indexes for table `manager_permissions`
--
ALTER TABLE `manager_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `phases`
--
ALTER TABLE `phases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `or_number` (`or_number`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_categories`
--
ALTER TABLE `accounting_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `delete_history`
--
ALTER TABLE `delete_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lots`
--
ALTER TABLE `lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `lot_gallery`
--
ALTER TABLE `lot_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `manager_permissions`
--
ALTER TABLE `manager_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `phases`
--
ALTER TABLE `phases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lots`
--
ALTER TABLE `lots`
  ADD CONSTRAINT `lots_ibfk_1` FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`);

--
-- Constraints for table `lot_gallery`
--
ALTER TABLE `lot_gallery`
  ADD CONSTRAINT `lot_gallery_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `manager_permissions`
--
ALTER TABLE `manager_permissions`
  ADD CONSTRAINT `manager_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
