-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 29, 2026 at 03:22 PM
-- Server version: 11.4.10-MariaDB-cll-lve
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sms_clayon_sms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `actor_type` enum('admin','client','system') NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `email`, `phone`, `password_hash`, `plan_id`, `status`, `created_at`) VALUES
(2, 'Simon NJogu', 'simonjogu001@gmail.com', '0711486334', '$2y$10$LqhCb8dSLzGpEr2i2/w4GO1de8x1FTi4FNR/j8pgYSCmtuogXuVcy', 1, 'active', '2026-05-04 09:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `client_api_keys`
--

CREATE TABLE `client_api_keys` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(16) NOT NULL,
  `plain_api_key` varchar(255) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','revoked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_api_keys`
--

INSERT INTO `client_api_keys` (`id`, `client_id`, `key_hash`, `key_prefix`, `last_used_at`, `status`, `created_at`) VALUES
(5, 2, '$2y$10$9b0riG2G1LsQxSuAM3TtIOFYMqzJJzy05yDRbT/4X92ARABGHkCZe', 'sk_live_f335', '2026-05-04 12:22:27', 'revoked', '2026-05-04 10:24:46'),
(6, 2, '$2y$10$Tc71nxfDJecCtexXo1JzrujSZIDmTw2ByC/f0uoucinhtoNxgkcFm', 'sk_live_b6d7', '2026-05-04 13:56:50', 'revoked', '2026-05-04 13:56:05'),
(7, 2, '$2y$10$mgNrovqzVSWIHLit86Pq.OVwCp4a3ykTVniI4WDNVU5k/Q2iCrhtC', 'sk_live_e6d4', '2026-07-29 10:53:43', 'active', '2026-05-04 14:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_reports`
--

CREATE TABLE `delivery_reports` (
  `id` bigint(20) NOT NULL,
  `sms_request_id` bigint(20) NOT NULL,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_transactions`
--

CREATE TABLE `mpesa_transactions` (
  `id` bigint(20) NOT NULL,
  `client_id` int(11) NOT NULL,
  `checkout_request_id` varchar(100) NOT NULL,
  `merchant_request_id` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `units_credited` decimal(15,4) DEFAULT 0.0000,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `callback_payload` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mpesa_transactions`
--

INSERT INTO `mpesa_transactions` (`id`, `client_id`, `checkout_request_id`, `merchant_request_id`, `phone`, `amount`, `units_credited`, `status`, `callback_payload`, `created_at`, `updated_at`) VALUES
(1, 2, 'ws_CO_13052026234814571711486334', 'f99f-4d5e-a89b-f4625afe77ab280151', '254711486334', 2.00, 3.2000, 'completed', '{\"Body\":{\"stkCallback\":{\"MerchantRequestID\":\"f99f-4d5e-a89b-f4625afe77ab280151\",\"CheckoutRequestID\":\"ws_CO_13052026234814571711486334\",\"ResultCode\":0,\"ResultDesc\":\"The service request is processed successfully.\",\"CallbackMetadata\":{\"Item\":[{\"Name\":\"Amount\",\"Value\":2},{\"Name\":\"MpesaReceiptNumber\",\"Value\":\"UED854BEHX\"},{\"Name\":\"Balance\"},{\"Name\":\"TransactionDate\",\"Value\":20260513234824},{\"Name\":\"PhoneNumber\",\"Value\":254711486334}]}}}}', '2026-05-13 20:48:14', '2026-05-13 20:48:25'),
(2, 2, 'ws_CO_13052026235636326711486334', 'f00f-40fa-8e45-735e2cc19b3f14960962', '254711486334', 2.00, 3.2000, 'completed', '{\"Body\":{\"stkCallback\":{\"MerchantRequestID\":\"f00f-40fa-8e45-735e2cc19b3f14960962\",\"CheckoutRequestID\":\"ws_CO_13052026235636326711486334\",\"ResultCode\":0,\"ResultDesc\":\"The service request is processed successfully.\",\"CallbackMetadata\":{\"Item\":[{\"Name\":\"Amount\",\"Value\":2},{\"Name\":\"MpesaReceiptNumber\",\"Value\":\"UED854BFUZ\"},{\"Name\":\"Balance\"},{\"Name\":\"TransactionDate\",\"Value\":20260513235644},{\"Name\":\"PhoneNumber\",\"Value\":254711486334}]}}}}', '2026-05-13 20:56:36', '2026-05-13 20:56:44'),
(3, 2, 'ws_CO_14052026003253826711486334', '198f-4ed7-ad3b-c67199c4bd901683915', '254711486334', 1.00, 1.6000, 'completed', '{\"Body\":{\"stkCallback\":{\"MerchantRequestID\":\"198f-4ed7-ad3b-c67199c4bd901683915\",\"CheckoutRequestID\":\"ws_CO_14052026003253826711486334\",\"ResultCode\":0,\"ResultDesc\":\"The service request is processed successfully.\",\"CallbackMetadata\":{\"Item\":[{\"Name\":\"Amount\",\"Value\":1},{\"Name\":\"MpesaReceiptNumber\",\"Value\":\"UEE854BBZZ\"},{\"Name\":\"Balance\"},{\"Name\":\"TransactionDate\",\"Value\":20260514003302},{\"Name\":\"PhoneNumber\",\"Value\":254711486334}]}}}}', '2026-05-13 21:32:54', '2026-05-13 21:33:02'),
(4, 2, 'ws_CO_14052026145244175711486334', '05fe-4d90-9d58-42d4fef1ed8c924491', '254711486334', 2.00, 3.2000, 'completed', '{\"Body\":{\"stkCallback\":{\"MerchantRequestID\":\"05fe-4d90-9d58-42d4fef1ed8c924491\",\"CheckoutRequestID\":\"ws_CO_14052026145244175711486334\",\"ResultCode\":0,\"ResultDesc\":\"The service request is processed successfully.\",\"CallbackMetadata\":{\"Item\":[{\"Name\":\"Amount\",\"Value\":2},{\"Name\":\"MpesaReceiptNumber\",\"Value\":\"UEE854D7OB\"},{\"Name\":\"Balance\"},{\"Name\":\"TransactionDate\",\"Value\":20260514145255},{\"Name\":\"PhoneNumber\",\"Value\":254711486334}]}}}}', '2026-05-14 11:52:44', '2026-05-14 11:52:55');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_plans`
--

CREATE TABLE `pricing_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `provider_markup_type` enum('percentage','fixed') DEFAULT 'percentage',
  `markup_value` decimal(10,4) NOT NULL,
  `min_topup` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_plans`
--

INSERT INTO `pricing_plans` (`id`, `plan_name`, `provider_markup_type`, `markup_value`, `min_topup`, `status`, `created_at`) VALUES
(1, 'Default', 'percentage', 25.0000, 100.00, 'active', '2026-05-04 09:52:52'),
(2, 'Premium', 'percentage', 30.0000, 500.00, 'active', '2026-05-04 09:52:52'),
(3, 'Starter', 'fixed', 0.2500, 50.00, 'active', '2026-05-04 09:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `provider_sms_logs`
--

CREATE TABLE `provider_sms_logs` (
  `id` bigint(20) NOT NULL,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `sms_request_id` bigint(20) DEFAULT NULL,
  `direction` enum('outgoing','incoming') DEFAULT 'outgoing',
  `sms_type` varchar(20) DEFAULT 'plain',
  `sender_name` varchar(20) DEFAULT NULL,
  `recipient` varchar(20) DEFAULT NULL,
  `sms_count` int(11) DEFAULT 1,
  `provider_cost` decimal(15,4) DEFAULT 0.0000,
  `status` varchar(50) DEFAULT 'pending',
  `provider_date` timestamp NULL DEFAULT NULL,
  `raw_payload` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_sms_logs`
--

INSERT INTO `provider_sms_logs` (`id`, `provider_message_id`, `sms_request_id`, `direction`, `sms_type`, `sender_name`, `recipient`, `sms_count`, `provider_cost`, `status`, `provider_date`, `raw_payload`, `created_at`) VALUES
(1, '9debb7a1-12f5-42d8-a04b-89d39ae5485a', 1, 'outgoing', 'plain', 'TALKSASA', '254721871211', 2, 0.0000, 'pending', NULL, '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"9debb7a1-12f5-42d8-a04b-89d39ae5485a\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/9debb7a1-12f5-42d8-a04b-89d39ae5485a\"}}', '2026-05-13 20:50:54'),
(2, '6eb153d8-d0b1-4d9b-9dfb-389bc775d141', 2, 'outgoing', 'plain', 'TALKSASA', '254758369875', 4, 0.0000, 'pending', NULL, '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"6eb153d8-d0b1-4d9b-9dfb-389bc775d141\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/6eb153d8-d0b1-4d9b-9dfb-389bc775d141\"}}', '2026-05-15 11:34:47'),
(3, '2f65456b-568d-4224-a24c-c244b65eacbb', 3, 'outgoing', 'plain', 'TALKSASA', '254711486334', 1, 0.0000, 'pending', NULL, '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"2f65456b-568d-4224-a24c-c244b65eacbb\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/2f65456b-568d-4224-a24c-c244b65eacbb\"}}', '2026-07-29 10:59:28'),
(4, 'b76f1c57-64f0-40ce-a17c-f2c484d814de', 4, 'outgoing', 'plain', 'TALKSASA', '254711486334', 1, 0.0000, 'pending', NULL, '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"b76f1c57-64f0-40ce-a17c-f2c484d814de\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/b76f1c57-64f0-40ce-a17c-f2c484d814de\"}}', '2026-07-29 12:35:31'),
(5, 'aadcae3b-a535-416d-a62c-23253d1d5a78', 5, 'outgoing', 'plain', 'TALKSASA', '254711486334', 1, 0.0000, 'pending', NULL, '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"aadcae3b-a535-416d-a62c-23253d1d5a78\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/aadcae3b-a535-416d-a62c-23253d1d5a78\"}}', '2026-07-29 13:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `sender_ids`
--

CREATE TABLE `sender_ids` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `sender_id` varchar(20) NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sender_ids`
--

INSERT INTO `sender_ids` (`id`, `client_id`, `sender_id`, `approval_status`, `status`, `created_at`) VALUES
(1, 1, 'CLAYON', 'approved', 'active', '2026-05-04 09:52:52'),
(2, 1, 'ADMIN', 'approved', 'active', '2026-05-04 09:52:52'),
(3, 1, 'TEST', 'approved', 'active', '2026-05-04 09:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `sms_attempts`
--

CREATE TABLE `sms_attempts` (
  `id` bigint(20) NOT NULL,
  `sms_request_id` bigint(20) NOT NULL,
  `attempt_no` int(11) NOT NULL,
  `provider_request_payload` longtext DEFAULT NULL,
  `provider_response_payload` longtext DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_attempts`
--

INSERT INTO `sms_attempts` (`id`, `sms_request_id`, `attempt_no`, `provider_request_payload`, `provider_response_payload`, `http_code`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 1, 1, '{\"recipient\":\"254721871211\",\"sender_id\":\"TALKSASA\",\"message\":\"Your CASHWRITR account registration failed due to incomplete payment.Please contact your refer for link.\",\"type\":\"plain\",\"callback_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\"}', '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"9debb7a1-12f5-42d8-a04b-89d39ae5485a\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/9debb7a1-12f5-42d8-a04b-89d39ae5485a\"}}', 202, '', '2026-05-13 20:50:54', '2026-05-13 20:50:54'),
(2, 2, 1, '{\"recipient\":\"254758369875\",\"sender_id\":\"TALKSASA\",\"message\":\"Your Account was created succesfully. Use password:12345678.Login to https:\\/\\/cashwrite.co.ke\\/login.php to change password and activate account.\",\"type\":\"plain\",\"callback_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\"}', '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"6eb153d8-d0b1-4d9b-9dfb-389bc775d141\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/6eb153d8-d0b1-4d9b-9dfb-389bc775d141\"}}', 202, '', '2026-05-15 11:34:47', '2026-05-15 11:34:47'),
(3, 3, 1, '{\"recipient\":\"254711486334\",\"sender_id\":\"TALKSASA\",\"message\":\"testing out webhoooks dlr\",\"type\":\"plain\",\"callback_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\"}', '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"2f65456b-568d-4224-a24c-c244b65eacbb\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/2f65456b-568d-4224-a24c-c244b65eacbb\"}}', 202, '', '2026-07-29 10:59:28', '2026-07-29 10:59:28'),
(4, 4, 1, '{\"recipient\":\"254711486334\",\"sender_id\":\"TALKSASA\",\"message\":\"testing dlr implementation\",\"type\":\"plain\",\"callback_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\"}', '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"b76f1c57-64f0-40ce-a17c-f2c484d814de\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/b76f1c57-64f0-40ce-a17c-f2c484d814de\"}}', 202, '', '2026-07-29 12:35:31', '2026-07-29 12:35:31'),
(5, 5, 1, '{\"recipient\":\"254711486334\",\"sender_id\":\"TALKSASA\",\"message\":\"testing with callback on payload\",\"type\":\"plain\",\"callback_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\",\"dlr_url\":\"https:\\/\\/cashwrite.co.ke\\/sms\\/dlr.php\"}', '{\"status\":\"success\",\"message\":\"Your SMS is being processed and will be delivered\",\"data\":{\"queue_uid\":\"aadcae3b-a535-416d-a62c-23253d1d5a78\",\"status\":\"accepted\",\"recipients_count\":1,\"sms_count\":1,\"estimated_cost\":1,\"check_status_url\":\"https:\\/\\/bulksms.talksasa.com\\/api\\/v3\\/sms\\/queue\\/aadcae3b-a535-416d-a62c-23253d1d5a78\"}}', 202, '', '2026-07-29 13:02:04', '2026-07-29 13:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` bigint(20) NOT NULL,
  `sms_request_id` bigint(20) NOT NULL,
  `client_id` int(11) NOT NULL,
  `status` enum('pending','locked','failed','dead_letter') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `next_attempt_at` timestamp NULL DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_requests`
--

CREATE TABLE `sms_requests` (
  `id` bigint(20) NOT NULL,
  `client_id` int(11) NOT NULL,
  `request_reference` varchar(64) NOT NULL,
  `recipient` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `sender_id` varchar(20) NOT NULL,
  `estimated_segments` int(11) DEFAULT 1,
  `estimated_cost` decimal(15,4) DEFAULT 0.0000,
  `final_cost` decimal(15,4) DEFAULT 0.0000,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `delivery_status` varchar(50) DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','accepted','completed','failed','refunded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_requests`
--

INSERT INTO `sms_requests` (`id`, `client_id`, `request_reference`, `recipient`, `message`, `sender_id`, `estimated_segments`, `estimated_cost`, `final_cost`, `provider_message_id`, `delivery_status`, `delivered_at`, `status`, `created_at`) VALUES
(1, 2, 'req_6a04e42dc7fd9701822400', '254721871211', 'Your CASHWRITR account registration failed due to incomplete payment.Please contact your refer for link.', 'TALKSASA', 2, 2.0000, 2.0000, '9debb7a1-12f5-42d8-a04b-89d39ae5485a', 'pending', NULL, 'accepted', '2026-05-13 20:50:53'),
(2, 2, 'req_6a0704d72e449841464331', '254758369875', 'Your Account was created succesfully. Use password:12345678.Login to https://cashwrite.co.ke/login.php to change password and activate account.', 'TALKSASA', 4, 4.0000, 4.0000, '6eb153d8-d0b1-4d9b-9dfb-389bc775d141', 'pending', NULL, 'accepted', '2026-05-15 11:34:47'),
(3, 2, 'req_6a69dd0fd3006424024768', '254711486334', 'testing out webhoooks dlr', 'TALKSASA', 1, 1.0000, 1.0000, '2f65456b-568d-4224-a24c-c244b65eacbb', 'pending', NULL, 'accepted', '2026-07-29 10:59:27'),
(4, 2, 'req_6a69f392c2a3a322844120', '254711486334', 'testing dlr implementation', 'TALKSASA', 1, 1.0000, 1.0000, 'b76f1c57-64f0-40ce-a17c-f2c484d814de', 'pending', NULL, 'accepted', '2026-07-29 12:35:30'),
(5, 2, 'req_6a69f9cc4522a090360749', '254711486334', 'testing with callback on payload', 'TALKSASA', 1, 1.0000, 1.0000, 'aadcae3b-a535-416d-a62c-23253d1d5a78', 'pending', NULL, 'accepted', '2026-07-29 13:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'PROVIDER_UNITS_PER_KES', '2.0', '2026-05-04 11:38:05'),
(2, 'RESELLER_BASE_SEGMENT_SIZE', '60', '2026-05-04 11:38:05');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_accounts`
--

CREATE TABLE `wallet_accounts` (
  `client_id` int(11) NOT NULL,
  `balance_units` decimal(15,4) DEFAULT 0.0000,
  `reserved_units` decimal(15,4) DEFAULT 0.0000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_accounts`
--

INSERT INTO `wallet_accounts` (`client_id`, `balance_units`, `reserved_units`, `updated_at`) VALUES
(2, 6.2000, 0.0000, '2026-07-29 13:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_ledger`
--

CREATE TABLE `wallet_ledger` (
  `id` bigint(20) NOT NULL,
  `client_id` int(11) NOT NULL,
  `entry_type` enum('credit','debit','reserved','refund','adjustment') NOT NULL,
  `units` decimal(15,4) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_ledger`
--

INSERT INTO `wallet_ledger` (`id`, `client_id`, `entry_type`, `units`, `reference`, `note`, `created_at`) VALUES
(1, 2, 'credit', 2.0000, 'INIT_ADMIN', 'Admin account initialization', '2026-05-04 09:52:52'),
(2, 2, 'credit', 3.2000, 'UED854BEHX', 'M-Pesa Topup - ws_CO_13052026234814571711486334 (Plan: 1, Rate: 1.6 Units/KES)', '2026-05-13 20:48:25'),
(3, 2, 'reserved', 2.0000, 'req_6a04e42dc7fd9701822400', 'SMS Send Request Reserved - 254721871211 (pending provider confirmation)', '2026-05-13 20:50:53'),
(4, 2, 'debit', 2.0000, '9debb7a1-12f5-42d8-a04b-89d39ae5485a', 'SMS accepted by provider - Provider ID: 9debb7a1-12f5-42d8-a04b-89d39ae5485a', '2026-05-13 20:50:54'),
(5, 2, 'credit', 3.2000, 'UED854BFUZ', 'M-Pesa Topup - ws_CO_13052026235636326711486334 (Plan: 1, Rate: 1.6 Units/KES)', '2026-05-13 20:56:44'),
(6, 2, 'credit', 1.6000, 'UEE854BBZZ', 'M-Pesa Topup - ws_CO_14052026003253826711486334 (Plan: 1, Rate: 1.6 Units/KES)', '2026-05-13 21:33:02'),
(7, 2, 'credit', 3.2000, 'UEE854D7OB', 'M-Pesa Topup - ws_CO_14052026145244175711486334 (Plan: 1, Rate: 1.6 Units/KES)', '2026-05-14 11:52:55'),
(8, 2, 'reserved', 4.0000, 'req_6a0704d72e449841464331', 'SMS Send Request Reserved - 254758369875 (pending provider confirmation)', '2026-05-15 11:34:47'),
(9, 2, 'debit', 4.0000, '6eb153d8-d0b1-4d9b-9dfb-389bc775d141', 'SMS accepted by provider - Provider ID: 6eb153d8-d0b1-4d9b-9dfb-389bc775d141', '2026-05-15 11:34:47'),
(10, 2, 'reserved', 1.0000, 'req_6a69dd0fd3006424024768', 'SMS Send Request Reserved - 254711486334 (pending provider confirmation)', '2026-07-29 10:59:27'),
(11, 2, 'debit', 1.0000, '2f65456b-568d-4224-a24c-c244b65eacbb', 'SMS accepted by provider - Provider ID: 2f65456b-568d-4224-a24c-c244b65eacbb', '2026-07-29 10:59:28'),
(12, 2, 'reserved', 1.0000, 'req_6a69f392c2a3a322844120', 'SMS Send Request Reserved - 254711486334 (pending provider confirmation)', '2026-07-29 12:35:30'),
(13, 2, 'debit', 1.0000, 'b76f1c57-64f0-40ce-a17c-f2c484d814de', 'SMS accepted by provider - Provider ID: b76f1c57-64f0-40ce-a17c-f2c484d814de', '2026-07-29 12:35:31'),
(14, 2, 'reserved', 1.0000, 'req_6a69f9cc4522a090360749', 'SMS Send Request Reserved - 254711486334 (pending provider confirmation)', '2026-07-29 13:02:04'),
(15, 2, 'debit', 1.0000, 'aadcae3b-a535-416d-a62c-23253d1d5a78', 'SMS accepted by provider - Provider ID: aadcae3b-a535-416d-a62c-23253d1d5a78', '2026-07-29 13:02:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `client_api_keys`
--
ALTER TABLE `client_api_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_key_hash` (`key_hash`);

--
-- Indexes for table `delivery_reports`
--
ALTER TABLE `delivery_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_request_id` (`sms_request_id`),
  ADD KEY `idx_provider_message_id` (`provider_message_id`);

--
-- Indexes for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkout_request_id` (`checkout_request_id`),
  ADD KEY `idx_checkout_request_id` (`checkout_request_id`),
  ADD KEY `idx_client_id` (`client_id`);

--
-- Indexes for table `pricing_plans`
--
ALTER TABLE `pricing_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `provider_sms_logs`
--
ALTER TABLE `provider_sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider_message_id` (`provider_message_id`),
  ADD KEY `idx_sms_request_id` (`sms_request_id`);

--
-- Indexes for table `sender_ids`
--
ALTER TABLE `sender_ids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`);

--
-- Indexes for table `sms_attempts`
--
ALTER TABLE `sms_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_request_id` (`sms_request_id`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_next_attempt` (`status`,`next_attempt_at`),
  ADD KEY `idx_sms_request_id` (`sms_request_id`);

--
-- Indexes for table `sms_requests`
--
ALTER TABLE `sms_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_reference` (`request_reference`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_provider_message_id` (`provider_message_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `wallet_accounts`
--
ALTER TABLE `wallet_accounts`
  ADD PRIMARY KEY (`client_id`);

--
-- Indexes for table `wallet_ledger`
--
ALTER TABLE `wallet_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_reference` (`reference`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `client_api_keys`
--
ALTER TABLE `client_api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `delivery_reports`
--
ALTER TABLE `delivery_reports`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pricing_plans`
--
ALTER TABLE `pricing_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `provider_sms_logs`
--
ALTER TABLE `provider_sms_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sender_ids`
--
ALTER TABLE `sender_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sms_attempts`
--
ALTER TABLE `sms_attempts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_requests`
--
ALTER TABLE `sms_requests`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wallet_ledger`
--
ALTER TABLE `wallet_ledger`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
