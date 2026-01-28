-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 09, 2026 at 05:13 PM
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
-- Database: `bookinggodb`
--

-- --------------------------------------------------------

--
-- Table structure for table `add_ons`
--

CREATE TABLE `add_ons` (
  `id` bigint UNSIGNED NOT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `yearly_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enable` tinyint(1) NOT NULL DEFAULT '0',
  `package_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `add_ons`
--

INSERT INTO `add_ons` (`id`, `module`, `name`, `monthly_price`, `yearly_price`, `image`, `is_enable`, `package_name`, `created_at`, `updated_at`) VALUES
(1, 'CarService', 'Car Service', '0', '0', NULL, 0, 'car-service', '2025-12-19 05:46:36', '2025-12-27 01:35:06'),
(2, 'CarService', 'Car Service', '0', '0', NULL, 1, 'car-service', '2025-12-19 05:46:36', '2025-12-19 05:46:36'),
(3, 'GoogleCaptcha', 'Google Captcha', '0', '0', NULL, 0, 'google-captcha', '2025-12-19 05:46:36', '2025-12-27 01:36:01'),
(4, 'GoogleCaptcha', 'Google Captcha', '0', '0', NULL, 1, 'google-captcha', '2025-12-19 05:46:36', '2025-12-19 05:46:36'),
(5, 'LandingPage', 'cms', '0', '0', NULL, 0, 'landing-page', '2025-12-19 05:46:36', '2025-12-19 16:01:37'),
(6, 'LandingPage', 'cms', '0', '0', NULL, 1, 'landing-page', '2025-12-19 05:46:36', '2025-12-19 05:46:36'),
(7, 'Paypal', 'Paypal', '0', '0', NULL, 0, 'paypal', '2025-12-19 05:46:36', '2025-12-27 01:35:17'),
(8, 'Paypal', 'Paypal', '0', '0', NULL, 1, 'paypal', '2025-12-19 05:46:36', '2025-12-19 05:46:36'),
(9, 'Photography', 'Photography', '0', '0', NULL, 0, 'photography', '2025-12-19 05:46:36', '2025-12-27 01:35:22'),
(10, 'Photography', 'Photography', '0', '0', NULL, 1, 'photography', '2025-12-19 05:46:36', '2025-12-19 05:46:36'),
(11, 'Stripe', 'Stripe', '0', '0', NULL, 0, 'stripe', '2025-12-19 05:46:36', '2025-12-27 01:35:27'),
(12, 'Stripe', 'Stripe', '0', '0', NULL, 1, 'stripe', '2025-12-19 05:46:36', '2025-12-19 05:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `location_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `staff_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_field` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `customer_id`, `location_id`, `service_id`, `staff_id`, `name`, `email`, `contact`, `date`, `time`, `notes`, `payment_type`, `appointment_status`, `attachment`, `custom_field`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(9, NULL, 1, 4, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '17:30-17:50', '', 'manually', '2', 'uploads/Appointment/screencapture-bookinggo-test-business-2025-12-27-21_38_35_1766872153.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sd\"}', 2, 3, '2025-12-27 16:49:13', '2025-12-28 11:32:59'),
(10, NULL, 1, 4, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '17:30-17:50', '', 'manually', '3', 'uploads/Appointment/screencapture-bookinggo-test-business-2025-12-27-21_38_35_1766872153.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sd\"}', 2, 3, '2025-12-27 16:49:13', '2025-12-28 11:33:10'),
(11, NULL, 1, 19, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '20:50-21:10', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-appointments-amad-diagnostic-centre-gujranwala-2025-12-28-21_34_35_1766939704.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sadsad sad sad\"}', 2, 3, '2025-12-28 11:35:04', '2025-12-28 11:35:04'),
(12, NULL, 1, 19, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '20:50-21:10', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-appointments-amad-diagnostic-centre-gujranwala-2025-12-28-21_34_35_1766939704.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sadsad sad sad\"}', 2, 3, '2025-12-28 11:35:04', '2025-12-28 11:35:04'),
(13, NULL, 1, 12, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '30-12-2025', '18:15-18:30', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-profile-2025-12-27-21_51_10_1766939781.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sa dsad a ds\"}', 2, 3, '2025-12-28 11:36:21', '2025-12-28 11:36:21'),
(14, NULL, 1, 12, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '30-12-2025', '18:15-18:30', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-profile-2025-12-27-21_51_10_1766939781.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sa dsad a ds\"}', 2, 3, '2025-12-28 11:36:21', '2025-12-28 11:36:21'),
(15, NULL, 1, 30, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '30-12-2025', '19:10-19:30', '', 'manually', 'Pending', 'uploads/Appointment/2025-12-28 21_36_23-Greenshot_1766940419.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sadsadsa\"}', 2, 3, '2025-12-28 11:46:59', '2025-12-28 11:46:59'),
(16, NULL, 1, 28, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '18:25-18:50', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-appointments-amad-diagnostic-centre-gujranwala-2025-12-28-21_34_35_1766940836.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sad sa dsa\"}', 2, 3, '2025-12-28 11:53:56', '2025-12-28 11:53:56'),
(17, NULL, 1, 31, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '18:25-18:50', '', 'manually', 'Pending', 'uploads/Appointment/screencapture-bookinggo-test-appointments-amad-diagnostic-centre-gujranwala-2025-12-28-21_34_35_1766941505.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sad sad sa\"}', 2, 3, '2025-12-28 12:05:05', '2025-12-28 12:05:05'),
(18, NULL, 1, 41, 6, 'Dr Haif Atif Hussain', 'drmianwaheed@gmail.com', '03007131730', '31-12-2025', '20:05-20:30', '', 'manually', 'Pending', 'uploads/Appointment/2025-12-28 21_36_23-Greenshot_1766942597.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sad a\"}', 2, 3, '2025-12-28 12:23:17', '2025-12-28 12:23:17'),
(19, NULL, 1, 18, 6, 'Faisal Maqsood ANWAR', 'mindreader420123@gmail.com', '03214261950', '31-12-2025', '18:15-18:30', '', 'manually', 'Pending', 'uploads/Appointment/2025-12-28 22_23_55-Greenshot_1766946212.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"sad sa dsa dsa\"}', 2, 3, '2025-12-28 13:23:32', '2025-12-28 13:23:32'),
(20, NULL, 1, 31, 6, 'Faisal Maqsood ANWAR', 'mindre23@gmail.com', '03214261950', '31-12-2025', '20:05-20:30', '', 'manually', 'Pending', 'uploads/Appointment/2025-12-28 23_24_03-Downloads - File Explorer_1767002843.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"asa sa s\"}', 2, 3, '2025-12-29 10:07:23', '2025-12-29 10:07:23'),
(21, NULL, 1, 31, 6, 'Super Admin', 'superadmin@example.com', '03214242421', '31-12-2025', '18:50-19:15', '', 'manually', 'Pending', NULL, '{\"Describe Patient Sign \\/ Symptoms Here :\":\"dsd d dfs ds ds\"}', 2, 3, '2025-12-30 06:44:30', '2025-12-30 06:44:30'),
(22, NULL, 1, 27, 6, 'dr maryam', 'maryam@gmail.com', '03213131331', '31-12-2025', '21:00-21:30', '', 'manually', '2', 'uploads/Appointment/screencapture-localhost-3001-2025-12-29-00_19_35_1767082148.png', '{\"Describe Patient Sign \\/ Symptoms Here :\":\"i have pain in abdomen !\"}', 2, 3, '2025-12-30 08:09:09', '2025-12-30 08:13:14');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_payments`
--

CREATE TABLE `appointment_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `appointment_id` bigint UNSIGNED DEFAULT NULL,
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` date NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_payments`
--

INSERT INTO `appointment_payments` (`id`, `appointment_id`, `payment_type`, `amount`, `payment_date`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 9, 'manually', '1500', '2025-12-27', 2, 3, '2025-12-27 16:49:14', '2025-12-27 16:49:14'),
(2, 10, 'manually', '1500', '2025-12-27', 2, 3, '2025-12-27 16:49:14', '2025-12-27 16:49:14'),
(3, 11, 'manually', '1200', '2025-12-28', 2, 3, '2025-12-28 11:35:04', '2025-12-28 11:35:04'),
(4, 12, 'manually', '1200', '2025-12-28', 2, 3, '2025-12-28 11:35:04', '2025-12-28 11:35:04'),
(5, 13, 'manually', '1000', '2025-12-28', 2, 3, '2025-12-28 11:36:21', '2025-12-28 11:36:21'),
(6, 14, 'manually', '1000', '2025-12-28', 2, 3, '2025-12-28 11:36:21', '2025-12-28 11:36:21'),
(7, 15, 'manually', '2000', '2025-12-28', 2, 3, '2025-12-28 11:46:59', '2025-12-28 11:46:59'),
(8, 16, 'manually', '2000', '2025-12-28', 2, 3, '2025-12-28 11:53:56', '2025-12-28 11:53:56'),
(9, 17, 'manually', '2500', '2025-12-28', 2, 3, '2025-12-28 12:05:05', '2025-12-28 12:05:05'),
(10, 18, 'manually', '2200', '2025-12-28', 2, 3, '2025-12-28 12:23:17', '2025-12-28 12:23:17'),
(11, 19, 'manually', '1000', '2025-12-28', 2, 3, '2025-12-28 13:23:32', '2025-12-28 13:23:32'),
(12, 20, 'manually', '2500', '2025-12-29', 2, 3, '2025-12-29 10:07:23', '2025-12-29 10:07:23'),
(13, 21, 'manually', '2500', '2025-12-30', 2, 3, '2025-12-30 06:44:30', '2025-12-30 06:44:30'),
(14, 22, 'manually', '2800', '2025-12-30', 2, 3, '2025-12-30 08:09:09', '2025-12-30 08:09:09');

-- --------------------------------------------------------

--
-- Table structure for table `bank_transfer_payments`
--

CREATE TABLE `bank_transfer_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `request` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double NOT NULL DEFAULT '0',
  `price_currency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `business` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `theme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disable` int NOT NULL DEFAULT '1',
  `form_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `layouts` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `name`, `status`, `slug`, `is_disable`, `form_type`, `layouts`, `theme_color`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Amad Diagnostic Centre - Gujranwala', 'active', 'amad-diagnostic-centre-gujranwala', 1, 'form-layout', 'Formlayout11', 'color1-Formlayout11', 3, '2025-12-20 00:15:37', '2025-12-20 00:15:37');

-- --------------------------------------------------------

--
-- Table structure for table `business_holidays`
--

CREATE TABLE `business_holidays` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_hours`
--

CREATE TABLE `business_hours` (
  `id` bigint UNSIGNED NOT NULL,
  `day_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `day_off` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `break_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_hours`
--

INSERT INTO `business_hours` (`id`, `day_name`, `start_time`, `end_time`, `day_off`, `break_hours`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'monday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(2, 'tuesday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(3, 'wednesday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(4, 'thursday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(5, 'friday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(6, 'saturday', '15:30:00', '21:30:00', 'off', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58'),
(7, 'sunday', '09:30:00', '18:00:00', 'on', '\"\"', 2, 3, '2025-12-26 14:30:58', '2025-12-26 14:30:58');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('amad_diagnostic_centre_cache_admin_settings', 'a:56:{s:10:\"title_text\";s:35:\"Amad Diagnostic Centre - Gujranwala\";s:11:\"footer_text\";s:82:\"Copyright © 2025 All rights reserved. Powered by PolytronX - Business Digitalized\";s:5:\"color\";s:7:\"theme-3\";s:10:\"color_flag\";s:5:\"false\";s:12:\"landing_page\";s:3:\"off\";s:8:\"site_rtl\";s:3:\"off\";s:6:\"signup\";s:2:\"on\";s:18:\"email_verification\";s:2:\"on\";s:16:\"site_transparent\";s:2:\"on\";s:15:\"cust_darklayout\";s:3:\"off\";s:15:\"storage_setting\";s:5:\"local\";s:24:\"local_storage_validation\";s:24:\"gif,jpeg,jpg,pdf,png,svg\";s:29:\"local_storage_max_upload_size\";s:4:\"2025\";s:6:\"s3_key\";N;s:9:\"s3_secret\";N;s:9:\"s3_region\";N;s:9:\"s3_bucket\";N;s:6:\"s3_url\";N;s:11:\"s3_endpoint\";N;s:18:\"s3_max_upload_size\";s:4:\"2024\";s:10:\"wasabi_key\";N;s:13:\"wasabi_secret\";N;s:13:\"wasabi_region\";N;s:13:\"wasabi_bucket\";N;s:10:\"wasabi_url\";N;s:11:\"wasabi_root\";N;s:22:\"wasabi_max_upload_size\";s:4:\"2024\";s:21:\"s3_storage_validation\";N;s:25:\"wasabi_storage_validation\";N;s:10:\"meta_title\";s:35:\"Amad Diagnostic Centre - Gujranwala\";s:13:\"meta_keywords\";s:35:\"Amad Diagnostic Centre - Gujranwala\";s:16:\"meta_description\";s:35:\"Amad Diagnostic Centre - Gujranwala\";s:10:\"meta_image\";s:40:\"uploads/meta/adc-logo (1)_1766817131.png\";s:15:\"defult_timezone\";s:12:\"Asia/Karachi\";s:15:\"defult_language\";s:2:\"en\";s:16:\"site_date_format\";s:5:\"d-m-Y\";s:16:\"site_time_format\";s:5:\"g:i A\";s:7:\"favicon\";s:35:\"uploads/logo/favicon_1766817451.png\";s:9:\"logo_dark\";s:37:\"uploads/logo/logo_dark_1766817467.png\";s:10:\"logo_light\";s:38:\"uploads/logo/logo_light_1766817489.png\";s:12:\"plan_package\";s:2:\"on\";s:15:\"currency_format\";s:1:\"1\";s:15:\"defult_currancy\";s:3:\"PKR\";s:22:\"defult_currancy_symbol\";s:3:\"₨\";s:13:\"enable_cookie\";s:3:\"off\";s:17:\"necessary_cookies\";s:2:\"on\";s:14:\"cookie_logging\";s:2:\"on\";s:12:\"cookie_title\";s:15:\"We use cookies!\";s:18:\"cookie_description\";s:130:\"Hi, this website uses essential cookies to ensure its proper operation and tracking cookies to understand how you interact with it\";s:21:\"strictly_cookie_title\";s:26:\"Strictly necessary cookies\";s:27:\"strictly_cookie_description\";s:128:\"These cookies are essential for the proper functioning of my website. Without these cookies, the website would not work properly\";s:28:\"more_information_description\";s:88:\"For any queries in relation to our policy on cookies and your choices, please contact us\";s:13:\"contactus_url\";s:1:\"#\";s:15:\"custome_package\";s:2:\"on\";s:27:\"bank_transfer_payment_is_on\";s:2:\"on\";s:11:\"bank_number\";s:81:\"Amad Diagnostic Centre - Gujranwala\r\nAllied Bank Limited\r\nIBAN#PK76ABPL3642479343\";}', 2082436879),
('amad_diagnostic_centre_cache_CarService', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:1;s:6:\"module\";s:10:\"CarService\";s:4:\"name\";s:11:\"Car Service\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:11:\"car-service\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:06\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:1;s:6:\"module\";s:10:\"CarService\";s:4:\"name\";s:11:\"Car Service\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:11:\"car-service\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:06\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:10:\"CarService\";s:5:\"alias\";s:11:\"Car Service\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:81:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/CarService/favicon.png\";s:11:\"description\";s:11:\"Theme Addon\";s:8:\"priority\";i:50;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.7;s:12:\"package_name\";s:11:\"car-service\";s:7:\"display\";b:1;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023),
('amad_diagnostic_centre_cache_company_settings_0_3', 'a:0:{}', 2082437070),
('amad_diagnostic_centre_cache_company_settings_2_3', 'a:21:{s:8:\"currency\";s:3:\"PKR\";s:15:\"currency_symbol\";s:3:\"PKR\";s:10:\"title_text\";s:22:\"Amad Diagnostic Centre\";s:11:\"footer_text\";s:82:\"Copyright © 2025 All rights reserved. Powered by PolytronX - Business Digitalized\";s:5:\"color\";s:7:\"theme-3\";s:10:\"color_flag\";s:5:\"false\";s:8:\"site_rtl\";s:3:\"off\";s:16:\"site_transparent\";s:3:\"off\";s:15:\"cust_darklayout\";s:3:\"off\";s:15:\"defult_timezone\";s:12:\"Asia/Karachi\";s:15:\"defult_language\";s:2:\"en\";s:16:\"site_date_format\";s:5:\"d-m-Y\";s:16:\"site_time_format\";s:5:\"g:i A\";s:18:\"appointment_prefix\";s:9:\"#ADC00000\";s:14:\"week_start_day\";s:1:\"1\";s:12:\"booking_mode\";s:3:\"1,2\";s:14:\"default_status\";s:7:\"Pending\";s:27:\"bank_transfer_payment_is_on\";s:2:\"on\";s:11:\"bank_number\";s:72:\"Amad Diagnostic Centre\r\nAllied Bank Limited\r\nIBAN # PK77ABPL098493893295\";s:12:\"maximum_slot\";s:3:\"150\";s:19:\"custom_field_enable\";s:2:\"on\";}', 2082430023),
('amad_diagnostic_centre_cache_GoogleCaptcha', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:3;s:6:\"module\";s:13:\"GoogleCaptcha\";s:4:\"name\";s:14:\"Google Captcha\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:14:\"google-captcha\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:36:01\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:3;s:6:\"module\";s:13:\"GoogleCaptcha\";s:4:\"name\";s:14:\"Google Captcha\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:14:\"google-captcha\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:36:01\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:13:\"GoogleCaptcha\";s:5:\"alias\";s:14:\"Google Captcha\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:84:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/GoogleCaptcha/favicon.png\";s:11:\"description\";s:0:\"\";s:8:\"priority\";i:880;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.2;s:12:\"package_name\";s:14:\"google-captcha\";s:7:\"display\";b:1;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023),
('amad_diagnostic_centre_cache_LandingPage', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:5;s:6:\"module\";s:11:\"LandingPage\";s:4:\"name\";s:3:\"cms\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:12:\"landing-page\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-19 16:01:37\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:5;s:6:\"module\";s:11:\"LandingPage\";s:4:\"name\";s:3:\"cms\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:12:\"landing-page\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-19 16:01:37\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:11:\"LandingPage\";s:5:\"alias\";s:3:\"cms\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:82:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/LandingPage/favicon.png\";s:11:\"description\";s:0:\"\";s:8:\"priority\";i:0;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.2;s:12:\"package_name\";s:12:\"landing-page\";s:7:\"display\";b:0;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023),
('amad_diagnostic_centre_cache_laratrust_permissions_for_role_2', 'a:80:{i:0;a:10:{s:2:\"id\";i:1;s:4:\"name\";s:11:\"user manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T13:29:30.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T13:29:30.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:1;}}i:1;a:10:{s:2:\"id\";i:2;s:4:\"name\";s:11:\"user create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:2;}}i:2;a:10:{s:2:\"id\";i:3;s:4:\"name\";s:9:\"user edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:3;}}i:3;a:10:{s:2:\"id\";i:4;s:4:\"name\";s:11:\"user delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:4;}}i:4;a:10:{s:2:\"id\";i:5;s:4:\"name\";s:19:\"user profile manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:5;}}i:5;a:10:{s:2:\"id\";i:6;s:4:\"name\";s:19:\"user reset password\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:6;}}i:6;a:10:{s:2:\"id\";i:7;s:4:\"name\";s:17:\"user login manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:7;}}i:7;a:10:{s:2:\"id\";i:8;s:4:\"name\";s:17:\"user logs history\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:8;}}i:8;a:10:{s:2:\"id\";i:9;s:4:\"name\";s:14:\"setting manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:9;}}i:9;a:10:{s:2:\"id\";i:10;s:4:\"name\";s:22:\"setting storage manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:10;}}i:10;a:10:{s:2:\"id\";i:11;s:4:\"name\";s:13:\"coupon manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:09.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:11;}}i:11;a:10:{s:2:\"id\";i:12;s:4:\"name\";s:13:\"coupon create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:12;}}i:12;a:10:{s:2:\"id\";i:13;s:4:\"name\";s:11:\"coupon edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:13;}}i:13;a:10:{s:2:\"id\";i:14;s:4:\"name\";s:13:\"coupon delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:14;}}i:14;a:10:{s:2:\"id\";i:15;s:4:\"name\";s:11:\"plan manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:15;}}i:15;a:10:{s:2:\"id\";i:16;s:4:\"name\";s:11:\"plan create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:16;}}i:16;a:10:{s:2:\"id\";i:17;s:4:\"name\";s:9:\"plan edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:17;}}i:17;a:10:{s:2:\"id\";i:18;s:4:\"name\";s:11:\"plan delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:18;}}i:18;a:10:{s:2:\"id\";i:19;s:4:\"name\";s:11:\"plan orders\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:19;}}i:19;a:10:{s:2:\"id\";i:20;s:4:\"name\";s:13:\"module manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:20;}}i:20;a:10:{s:2:\"id\";i:21;s:4:\"name\";s:10:\"module add\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:21;}}i:21;a:10:{s:2:\"id\";i:22;s:4:\"name\";s:13:\"module remove\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:22;}}i:22;a:10:{s:2:\"id\";i:23;s:4:\"name\";s:11:\"module edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:23;}}i:23;a:10:{s:2:\"id\";i:24;s:4:\"name\";s:15:\"language manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:24;}}i:24;a:10:{s:2:\"id\";i:25;s:4:\"name\";s:15:\"language create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:25;}}i:25;a:10:{s:2:\"id\";i:26;s:4:\"name\";s:15:\"language delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:26;}}i:26;a:10:{s:2:\"id\";i:27;s:4:\"name\";s:21:\"email template manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:10.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:27;}}i:27;a:10:{s:2:\"id\";i:28;s:4:\"name\";s:28:\"notification template manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:28;}}i:28;a:10:{s:2:\"id\";i:29;s:4:\"name\";s:15:\"business manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:29;}}i:29;a:10:{s:2:\"id\";i:30;s:4:\"name\";s:15:\"business create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:30;}}i:30;a:10:{s:2:\"id\";i:31;s:4:\"name\";s:13:\"business edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:31;}}i:31;a:10:{s:2:\"id\";i:32;s:4:\"name\";s:15:\"business delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:32;}}i:32;a:10:{s:2:\"id\";i:33;s:4:\"name\";s:15:\"business update\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:33;}}i:33;a:10:{s:2:\"id\";i:34;s:4:\"name\";s:15:\"location create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:34;}}i:34;a:10:{s:2:\"id\";i:35;s:4:\"name\";s:13:\"location edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:35;}}i:35;a:10:{s:2:\"id\";i:36;s:4:\"name\";s:15:\"location delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:36;}}i:36;a:10:{s:2:\"id\";i:37;s:4:\"name\";s:14:\"service create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:37;}}i:37;a:10:{s:2:\"id\";i:38;s:4:\"name\";s:12:\"service edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:38;}}i:38;a:10:{s:2:\"id\";i:39;s:4:\"name\";s:14:\"service delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:39;}}i:39;a:10:{s:2:\"id\";i:40;s:4:\"name\";s:12:\"staff create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:40;}}i:40;a:10:{s:2:\"id\";i:41;s:4:\"name\";s:10:\"staff edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:41;}}i:41;a:10:{s:2:\"id\";i:42;s:4:\"name\";s:12:\"staff delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:42;}}i:42;a:10:{s:2:\"id\";i:43;s:4:\"name\";s:15:\"category create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:43;}}i:43;a:10:{s:2:\"id\";i:44;s:4:\"name\";s:13:\"category edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:44;}}i:44;a:10:{s:2:\"id\";i:45;s:4:\"name\";s:15:\"category delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:45;}}i:45;a:10:{s:2:\"id\";i:46;s:4:\"name\";s:14:\"holiday create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:11.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:46;}}i:46;a:10:{s:2:\"id\";i:47;s:4:\"name\";s:12:\"holiday edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:47;}}i:47;a:10:{s:2:\"id\";i:48;s:4:\"name\";s:14:\"holiday delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:48;}}i:48;a:10:{s:2:\"id\";i:49;s:4:\"name\";s:18:\"appointment manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:49;}}i:49;a:10:{s:2:\"id\";i:50;s:4:\"name\";s:18:\"appointment create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:50;}}i:50;a:10:{s:2:\"id\";i:51;s:4:\"name\";s:16:\"appointment edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:51;}}i:51;a:10:{s:2:\"id\";i:52;s:4:\"name\";s:18:\"appointment delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:52;}}i:52;a:10:{s:2:\"id\";i:53;s:4:\"name\";s:15:\"customer manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:53;}}i:53;a:10:{s:2:\"id\";i:54;s:4:\"name\";s:15:\"customer create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:54;}}i:54;a:10:{s:2:\"id\";i:55;s:4:\"name\";s:13:\"customer edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:55;}}i:55;a:10:{s:2:\"id\";i:56;s:4:\"name\";s:15:\"customer delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:56;}}i:56;a:10:{s:2:\"id\";i:57;s:4:\"name\";s:12:\"roles manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:57;}}i:57;a:10:{s:2:\"id\";i:58;s:4:\"name\";s:12:\"roles create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:58;}}i:58;a:10:{s:2:\"id\";i:59;s:4:\"name\";s:10:\"roles edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:12.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:59;}}i:59;a:10:{s:2:\"id\";i:60;s:4:\"name\";s:12:\"roles delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:60;}}i:60;a:10:{s:2:\"id\";i:61;s:4:\"name\";s:13:\"plan purchase\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:61;}}i:61;a:10:{s:2:\"id\";i:62;s:4:\"name\";s:14:\"plan subscribe\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:62;}}i:62;a:10:{s:2:\"id\";i:63;s:4:\"name\";s:13:\"status manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:63;}}i:63;a:10:{s:2:\"id\";i:64;s:4:\"name\";s:13:\"status create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:64;}}i:64;a:10:{s:2:\"id\";i:65;s:4:\"name\";s:13:\"status update\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:65;}}i:65;a:10:{s:2:\"id\";i:66;s:4:\"name\";s:13:\"status delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:66;}}i:66;a:10:{s:2:\"id\";i:67;s:4:\"name\";s:11:\"blog manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:67;}}i:67;a:10:{s:2:\"id\";i:68;s:4:\"name\";s:11:\"blog create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:68;}}i:68;a:10:{s:2:\"id\";i:69;s:4:\"name\";s:9:\"blog edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:69;}}i:69;a:10:{s:2:\"id\";i:70;s:4:\"name\";s:11:\"blog delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:70;}}i:70;a:10:{s:2:\"id\";i:71;s:4:\"name\";s:18:\"testimonial manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:71;}}i:71;a:10:{s:2:\"id\";i:72;s:4:\"name\";s:18:\"testimonial create\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:72;}}i:72;a:10:{s:2:\"id\";i:73;s:4:\"name\";s:16:\"testimonial edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:73;}}i:73;a:10:{s:2:\"id\";i:74;s:4:\"name\";s:18:\"testimonial delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:13.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:74;}}i:74;a:10:{s:2:\"id\";i:75;s:4:\"name\";s:14:\"contact manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:75;}}i:75;a:10:{s:2:\"id\";i:76;s:4:\"name\";s:14:\"contact delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:76;}}i:76;a:10:{s:2:\"id\";i:77;s:4:\"name\";s:17:\"subscriber manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:77;}}i:77;a:10:{s:2:\"id\";i:78;s:4:\"name\";s:17:\"subscriber delete\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:78;}}i:78;a:10:{s:2:\"id\";i:79;s:4:\"name\";s:12:\"theme manage\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:79;}}i:79;a:10:{s:2:\"id\";i:80;s:4:\"name\";s:10:\"theme edit\";s:12:\"display_name\";N;s:11:\"description\";N;s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:7:\"General\";s:10:\"created_by\";i:1;s:10:\"created_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T14:03:14.000000Z\";s:5:\"pivot\";a:2:{s:7:\"role_id\";i:2;s:13:\"permission_id\";i:80;}}}', 1767085853),
('amad_diagnostic_centre_cache_laratrust_permissions_for_users_3', 'a:0:{}', 1767085853),
('amad_diagnostic_centre_cache_laratrust_roles_for_users_3', 'a:1:{i:0;a:10:{s:2:\"id\";i:2;s:4:\"name\";s:7:\"company\";s:12:\"display_name\";s:7:\"Company\";s:11:\"description\";s:22:\"Company/Business Owner\";s:10:\"guard_name\";s:3:\"web\";s:6:\"module\";s:4:\"Base\";s:10:\"created_by\";i:0;s:10:\"created_at\";s:27:\"2025-12-26T13:58:30.000000Z\";s:10:\"updated_at\";s:27:\"2025-12-26T13:58:30.000000Z\";s:5:\"pivot\";a:3:{s:9:\"user_type\";s:15:\"App\\Models\\User\";s:7:\"user_id\";i:3;s:7:\"role_id\";i:2;}}}', 1767085853),
('amad_diagnostic_centre_cache_Paypal', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:7;s:6:\"module\";s:6:\"Paypal\";s:4:\"name\";s:6:\"Paypal\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:6:\"paypal\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:17\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:7;s:6:\"module\";s:6:\"Paypal\";s:4:\"name\";s:6:\"Paypal\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:6:\"paypal\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:17\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:6:\"Paypal\";s:5:\"alias\";s:6:\"Paypal\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:77:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/Paypal/favicon.png\";s:11:\"description\";s:21:\"Payment Gateway Addon\";s:8:\"priority\";i:0;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.3;s:12:\"package_name\";s:6:\"paypal\";s:7:\"display\";b:1;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023),
('amad_diagnostic_centre_cache_Photography', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:9;s:6:\"module\";s:11:\"Photography\";s:4:\"name\";s:11:\"Photography\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:11:\"photography\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:22\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:9;s:6:\"module\";s:11:\"Photography\";s:4:\"name\";s:11:\"Photography\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:11:\"photography\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:22\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:11:\"Photography\";s:5:\"alias\";s:11:\"Photography\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:82:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/Photography/favicon.png\";s:11:\"description\";s:11:\"Theme Addon\";s:8:\"priority\";i:40;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.7;s:12:\"package_name\";s:11:\"photography\";s:7:\"display\";b:1;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023);
INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('amad_diagnostic_centre_cache_sidebar_menu_3', 's:4232:\"<li class=\"dash-item dash-hasmenu\"><a href=\"#!\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-home\"></i></span>\n                        <span class=\"dash-mtext\">Dashboard</span><span class=\"dash-arrow\"> <i data-feather=\"chevron-right\"></i> </span> </a><ul class=\"dash-submenu\"><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/appointment-dashboard\" class=\"dash-link\">Appointment Dashboard</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/dashboard\" class=\"dash-link\">Overview Dashboard</span></a></li></ul></li><li class=\"dash-item dash-hasmenu\"><a href=\"#!\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-users\"></i></span>\n                        <span class=\"dash-mtext\">User Management</span><span class=\"dash-arrow\"> <i data-feather=\"chevron-right\"></i> </span> </a><ul class=\"dash-submenu\"><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/users\" class=\"dash-link\">User</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/roles\" class=\"dash-link\">Role</span></a></li></ul></li><li class=\"dash-item dash-hasmenu\"><a href=\"#!\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-credit-card\"></i></span>\n                        <span class=\"dash-mtext\">Business</span><span class=\"dash-arrow\"> <i data-feather=\"chevron-right\"></i> </span> </a><ul class=\"dash-submenu\"><li class=\"dash-item\"><a href=\"#!\" class=\"dash-link\">Create Business</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/manage/business\" class=\"dash-link\">Edit Business</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/business\" class=\"dash-link\">Businesses</span></a></li></ul></li><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/customer\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-user\"></i></span>\n                        <span class=\"dash-mtext\">Customers</span></a></li><li class=\"nav-main-title\"><h3> Appointments</h3><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/appointment\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-credit-card custom-icon appointments\"></i></span>\n                        <span class=\"dash-mtext\">Appointments</span></a></li><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/appointment-calendar\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-calendar custom-icon calender\"></i></span>\n                        <span class=\"dash-mtext\">Appointment Calendar</span></a></li><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/custom-status\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-tag\"></i></span>\n                        <span class=\"dash-mtext\">Custom Status</span></a></li><li class=\"nav-main-title\"><h3> Contacts & reports</h3><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/contacts\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-phone\"></i></span>\n                        <span class=\"dash-mtext\">Contacts</span></a></li><li class=\"dash-item dash-hasmenu\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/subscribes\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-mail\"></i></span>\n                        <span class=\"dash-mtext\">Subscribers</span></a></li><li class=\"nav-main-title\"><h3> Others</h3><li class=\"dash-item dash-hasmenu\"><a href=\"#!\" class=\"dash-link\"> <span class=\"dash-micon\"><i class=\"ti ti-settings\"></i></span>\n                        <span class=\"dash-mtext\">Settings</span><span class=\"dash-arrow\"> <i data-feather=\"chevron-right\"></i> </span> </a><ul class=\"dash-submenu\"><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/settings\" class=\"dash-link\">System Settings</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/plans\" class=\"dash-link\">Setup Subscription Plan</span></a></li><li class=\"dash-item\"><a href=\"https://portal.amaddiagnosticcentre.com.pk/plan/order\" class=\"dash-link\">Order</span></a></li></ul></li>\";', 2082436880),
('amad_diagnostic_centre_cache_Stripe', 'O:18:\"App\\Classes\\Module\":14:{s:8:\"\0*\0addon\";O:16:\"App\\Models\\AddOn\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:7:\"add_ons\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:10:{s:2:\"id\";i:11;s:6:\"module\";s:6:\"Stripe\";s:4:\"name\";s:6:\"Stripe\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:6:\"stripe\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:27\";}s:11:\"\0*\0original\";a:10:{s:2:\"id\";i:11;s:6:\"module\";s:6:\"Stripe\";s:4:\"name\";s:6:\"Stripe\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";N;s:9:\"is_enable\";i:0;s:12:\"package_name\";s:6:\"stripe\";s:10:\"created_at\";s:19:\"2025-12-19 05:46:36\";s:10:\"updated_at\";s:19:\"2025-12-27 01:35:27\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:6:\"module\";i:1;s:4:\"name\";i:2;s:13:\"monthly_price\";i:3;s:12:\"yearly_price\";i:4;s:5:\"image\";i:5;s:9:\"is_enable\";i:6;s:12:\"package_name\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:4:\"name\";s:6:\"Stripe\";s:5:\"alias\";s:6:\"Stripe\";s:13:\"monthly_price\";s:1:\"0\";s:12:\"yearly_price\";s:1:\"0\";s:5:\"image\";s:77:\"https://portal.amaddiagnosticcentre.com.pk/packages/workdo/Stripe/favicon.png\";s:11:\"description\";s:21:\"Payment Gateway Addon\";s:8:\"priority\";i:0;s:12:\"child_module\";a:0:{}s:13:\"parent_module\";a:0:{}s:7:\"version\";d:1.3;s:12:\"package_name\";s:6:\"stripe\";s:7:\"display\";b:1;s:13:\"\0*\0allEnabled\";a:0:{}}', 2082430023);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'X-ray', 2, 3, '2025-12-26 16:06:08', '2025-12-26 16:06:08'),
(2, 'Ultrasound', 2, 3, '2025-12-26 16:06:08', '2025-12-26 16:06:08'),
(3, 'CT Scan', 2, 3, '2025-12-26 16:06:08', '2025-12-26 16:06:08'),
(4, 'MRI Scan', 2, 3, '2025-12-26 16:06:08', '2025-12-26 16:06:08');

-- --------------------------------------------------------

--
-- Table structure for table `contact_us`
--

CREATE TABLE `contact_us` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `theme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_us`
--

INSERT INTO `contact_us` (`id`, `name`, `email`, `contact`, `subject`, `description`, `theme`, `business_id`, `created_at`, `updated_at`) VALUES
(1, 'Faisal Maqsood ANWAR', 'mindre23@gmail.com', '03214261950', 'Appointment Booking - Guest', 'Contact created from appointment booking', 'default', 2, '2025-12-29 10:07:23', '2025-12-29 10:07:23'),
(2, 'Super Admin', 'superadmin@example.com', '03214242421', 'Appointment Booking - Guest', 'Contact created from appointment booking', 'default', 2, '2025-12-30 06:44:30', '2025-12-30 06:44:30'),
(3, 'dr maryam', 'maryam@gmail.com', '03213131331', 'Appointment Booking - Guest', 'Contact created from appointment booking', 'default', 2, '2025-12-30 08:09:09', '2025-12-30 08:09:09');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount` double NOT NULL DEFAULT '0',
  `limit` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `symbol` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`name`, `code`, `symbol`) VALUES
('Leke', 'ALL', 'Lek'),
('Dollars', 'USD', '$'),
('Afghanis', 'AFN', '؋'),
('Pesos', 'ARS', '$'),
('Guilders', 'AWG', 'ƒ'),
('Dollars', 'AUD', '$'),
('New Manats', 'AZN', 'ман'),
('Dollars', 'BSD', '$'),
('Dollars', 'BBD', '$'),
('Rubles', 'BYR', 'p.'),
('Euro', 'EUR', '€'),
('Dollars', 'BZD', 'BZ$'),
('Dollars', 'BMD', '$'),
('Bolivianos', 'BOB', '$b'),
('Convertible Marka', 'BAM', 'KM'),
('Pula', 'BWP', 'P'),
('Leva', 'BGN', 'лв'),
('Reais', 'BRL', 'R$'),
('Pounds', 'GBP', '£'),
('Dollars', 'BND', '$'),
('Riels', 'KHR', '៛'),
('Dollars', 'CAD', '$'),
('Dollars', 'KYD', '$'),
('Pesos', 'CLP', '$'),
('Yuan Renminbi', 'CNY', '¥'),
('Pesos', 'COP', '$'),
('Colón', 'CRC', '₡'),
('Kuna', 'HRK', 'kn'),
('Pesos', 'CUP', '₱'),
('Koruny', 'CZK', 'Kč'),
('Kroner', 'DKK', 'kr'),
('Pesos', 'DOP', 'RD$'),
('Dollars', 'XCD', '$'),
('Pounds', 'EGP', '£'),
('Colones', 'SVC', '$'),
('Pounds', 'FKP', '£'),
('Dollars', 'FJD', '$'),
('Cedis', 'GHC', '¢'),
('Pounds', 'GIP', '£'),
('Quetzales', 'GTQ', 'Q'),
('Pounds', 'GGP', '£'),
('Dollars', 'GYD', '$'),
('Lempiras', 'HNL', 'L'),
('Dollars', 'HKD', '$'),
('Forint', 'HUF', 'Ft'),
('Kronur', 'ISK', 'kr'),
('Rupees', 'INR', 'Rp'),
('Rupiahs', 'IDR', 'Rp'),
('Rials', 'IRR', '﷼'),
('Pounds', 'IMP', '£'),
('New Shekels', 'ILS', '₪'),
('Dollars', 'JMD', 'J$'),
('Yen', 'JPY', '¥'),
('Pounds', 'JEP', '£'),
('Tenge', 'KZT', 'лв'),
('Won', 'KPW', '₩'),
('Won', 'KRW', '₩'),
('Soms', 'KGS', 'лв'),
('Kips', 'LAK', '₭'),
('Lati', 'LVL', 'Ls'),
('Pounds', 'LBP', '£'),
('Dollars', 'LRD', '$'),
('Switzerland Francs', 'CHF', 'CHF'),
('Litai', 'LTL', 'Lt'),
('Denars', 'MKD', 'ден'),
('Ringgits', 'MYR', 'RM'),
('Rupees', 'MUR', '₨'),
('Pesos', 'MXN', '$'),
('Tugriks', 'MNT', '₮'),
('Meticais', 'MZN', 'MT'),
('Dollars', 'NAD', '$'),
('Rupees', 'NPR', '₨'),
('Guilders', 'ANG', 'ƒ'),
('Dollars', 'NZD', '$'),
('Cordobas', 'NIO', 'C$'),
('Nairas', 'NGN', '₦'),
('Krone', 'NOK', 'kr'),
('Rials', 'OMR', '﷼'),
('Rupees', 'PKR', '₨'),
('Balboa', 'PAB', 'B/.'),
('Guarani', 'PYG', 'Gs'),
('Nuevos Soles', 'PEN', 'S/.'),
('Pesos', 'PHP', 'Php'),
('Zlotych', 'PLN', 'zł'),
('Rials', 'QAR', '﷼'),
('New Lei', 'RON', 'lei'),
('Rubles', 'RUB', '₽'),
('Pounds', 'SHP', '£'),
('Riyals', 'SAR', '﷼'),
('Dinars', 'RSD', 'Дин.'),
('Rupees', 'SCR', '₨'),
('Dollars', 'SGD', '$'),
('Dollars', 'SBD', '$'),
('Shillings', 'SOS', 'S'),
('Rand', 'ZAR', 'R'),
('Rupees', 'LKR', '₨'),
('Kronor', 'SEK', 'kr'),
('Dollars', 'SRD', '$'),
('Pounds', 'SYP', '£'),
('New Dollars', 'TWD', 'NT$'),
('Baht', 'THB', '฿'),
('Dollars', 'TTD', 'TT$'),
('Lira', 'TRY', '₺'),
('Liras', 'TRL', '£'),
('Dollars', 'TVD', '$'),
('Hryvnia', 'UAH', '₴'),
('Pesos', 'UYU', '$U'),
('Sums', 'UZS', 'лв'),
('Bolivares Fuertes', 'VEF', 'Bs'),
('Dong', 'VND', '₫'),
('Rials', 'YER', '﷼'),
('Zimbabwe Dollars', 'ZWD', 'Z$'),
('Bahraini Dinar', 'BHD', '$'),
('Turkish lira', 'TL', '₺'),
('United Arab Emirates Dirham', 'AED', 'د.إ'),
('West African CFA franc', 'XOF', 'CFA'),
('Bangladeshi taka', 'BDT', '৳');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` bigint UNSIGNED NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `option` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `custom_fields`
--

INSERT INTO `custom_fields` (`id`, `label`, `value`, `type`, `option`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Describe Patient Sign / Symptoms Here :', NULL, 'textfield', NULL, 2, 3, '2025-12-26 14:33:15', '2025-12-26 14:33:15');

-- --------------------------------------------------------

--
-- Table structure for table `custom_statuses`
--

CREATE TABLE `custom_statuses` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `custom_statuses`
--

INSERT INTO `custom_statuses` (`id`, `title`, `status_color`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Paid', '00CF06', 2, 3, '2025-12-27 15:04:27', '2025-12-28 11:33:55'),
(3, 'Cancelled', 'FF3A6E', 2, 3, '2025-12-27 15:04:45', '2025-12-28 11:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL DEFAULT '0',
  `business_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `from`, `module_name`, `created_by`, `business_id`, `created_at`, `updated_at`) VALUES
(1, 'New User', 'BookingGo', 'general', 1, 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(2, 'Create Appointment', 'BookingGo', 'general', 1, 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(3, 'Appointment Status Change', 'BookingGo', 'general', 1, 0, '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(4, 'Appointment Reminder', 'BookingGo', 'general', 1, 0, '2025-12-26 13:29:30', '2025-12-26 13:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `email_template_langs`
--

CREATE TABLE `email_template_langs` (
  `id` bigint UNSIGNED NOT NULL,
  `parent_id` int NOT NULL DEFAULT '0',
  `lang` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_template_langs`
--

INSERT INTO `email_template_langs` (`id`, `parent_id`, `lang`, `subject`, `content`, `module_name`, `variables`, `created_at`, `updated_at`) VALUES
(1, 1, 'ar', 'Login Detail', '<p>مرحبا،&nbsp;<br>مرحبا بك في {app_name}.</p><p><b>البريد الإلكتروني </b>: {email}<br><b>كلمه السر</b> : {password}</p><p>{app_url}</p><p>شكر،<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(2, 1, 'da', 'Login Detail', '<p>Hej,&nbsp;<br>Velkommen til {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Adgangskode</b> : {password}</p><p>{app_url}</p><p>Tak,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(3, 1, 'de', 'Login Detail', '<p>Hallo,&nbsp;<br>Willkommen zu {app_name}.</p><p><b>Email </b>: {email}<br><b>Passwort</b> : {password}</p><p>{app_url}</p><p>Vielen Dank,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(4, 1, 'en', 'Login Detail', '<p>Hello,&nbsp;<br />Welcome to {app_name}</p>\n                    <p><strong>Email </strong>: {email}<br /><strong>Password</strong> : {password}</p>\n                    <p>{app_url}</p>\n                    <p>Thanks,<br />{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(5, 1, 'es', 'Login Detail', '<p>Hola,&nbsp;<br>Bienvenido a {app_name}.</p><p><b>Correo electrónico </b>: {email}<br><b>Contraseña</b> : {password}</p><p>{app_url}</p><p>Gracias,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(6, 1, 'fr', 'Login Detail', '<p>Bonjour,&nbsp;<br>Bienvenue à {app_name}.</p><p><b>Email </b>: {email}<br><b>Mot de passe</b> : {password}</p><p>{app_url}</p><p>Merci,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(7, 1, 'it', 'Login Detail', '<p>Ciao,&nbsp;<br>Benvenuto a {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Parola d\'ordine</b> : {password}</p><p>{app_url}</p><p>Grazie,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(8, 1, 'ja', 'Login Detail', '<p>こんにちは、&nbsp;<br>へようこそ {app_name}.</p><p><b>Eメール </b>: {email}<br><b>パスワード</b> : {password}</p><p>{app_url}</p><p>おかげで、<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(9, 1, 'nl', 'Login Detail', '<p>Hallo,&nbsp;<br>Welkom bij {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Wachtwoord</b> : {password}</p><p>{app_url}</p><p>Bedankt,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(10, 1, 'pl', 'Login Detail', '<p>Witaj,&nbsp;<br>Witamy w {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Hasło</b> : {password}</p><p>{app_url}</p><p>Dzięki,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(11, 1, 'ru', 'Login Detail', '<p>Привет,&nbsp;<br>Добро пожаловать в {app_name}.</p><p><b>Электронное письмо </b>: {email}<br><b>пароль</b> : {password}</p><p>{app_url}</p><p>Спасибо,<br>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(12, 1, 'pt', 'Login Detail', '<p>Ol&aacute;, Bem-vindo a {app_name}.</p>\n                    <p>E-mail: {email}</p>\n                    <p>Senha: {password}</p>\n                    <p>{app_url}</p>\n                    <p>&nbsp;</p>\n                    <p>Obrigado,</p>\n                    <p>{app_name}</p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(13, 1, 'tr', 'Login Detail', '<p>Merhaba,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}a hoş geldiniz</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">E-posta: {email}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Şifre : {şifre}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_url}</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Teşekkürler,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{uygulama ismi}</span></p>', NULL, '{\"Email\": \"email\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Password\": \"password\", \"Company Name\": \"company_name\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(14, 2, 'ar', 'Appointment Details', '<p>مرحبًا،</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">نشكرك على حجز موعد معنا في {app_name}. يسعدنا تأكيد تفاصيل موعدك:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">رقم الموعد: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">تاريخ الموعد: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">وقت الموعد: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">الخدمة: {خدمة}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">الموقع: {الموقع}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">طاقم العمل: {طاقم العمل}</span></p><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">تتبع الموعد</a></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">لقد تم جدولة موعدك بنجاح. إذا كنت بحاجة إلى إجراء أي تغييرات أو كانت لديك أي أسئلة، فلا تتردد في التواصل معنا.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">نحن نتطلع إلى مقابلتك وتقديم خدمة ممتازة لك. نشكرك على اختيار {app_name} لاحتياجات موعدك.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">شكرًا،<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم الشركة}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم التطبيق}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(15, 2, 'da', 'Appointment Details', '<p>Hej,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Tak, fordi du reserverede en tid hos os på {app_name}. Vi er glade for at kunne bekræfte dine aftaledetaljer:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftalenummer: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaledato: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaletidspunkt: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service: {service}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Placering: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personale: {staff}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Spor aftale</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Din aftale er blevet planlagt. Hvis du har brug for at foretage ændringer eller har spørgsmål, så tøv ikke med at kontakte os.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Vi glæder os til at møde dig og give dig en fremragende service. Tak, fordi du valgte {app_name} til dine aftalebehov.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Tak,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{firmanavn}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(16, 2, 'de', 'Appointment Details', '<p>Hallo,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Vielen Dank, dass Sie einen Termin bei uns unter {app_name} gebucht haben. Wir freuen uns, Ihre Termindetails zu bestätigen:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Terminnummer: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {Dienst}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Standort: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Mitarbeiter: {Mitarbeiter}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Termin verfolgen</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ihr Termin wurde erfolgreich vereinbart. Wenn Sie Änderungen vornehmen müssen oder Fragen haben, zögern Sie bitte nicht, uns zu kontaktieren.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Wir freuen uns darauf, Sie kennenzulernen und Ihnen einen exzellenten Service zu bieten. Vielen Dank, dass Sie sich für {app_name} für Ihren Terminbedarf entschieden haben.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Danke,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Name der Firma}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{App Name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(17, 2, 'en', 'Appointment Details', '<p><font color=\"#1d1c1d\" face=\"Slack-Lato, Slack-Fractions, appleLogo, sans-serif\"><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures;\">Hello,</span></font></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Thank you for booking an appointment with us at {app_name}. Were excited to confirm your appointment details:<br></span><span style=\"background-color: var(--bs-card-bg); color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\"><br>Appointment Number: {appointment_number}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Date: {appointment_date}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Time: {appointment_time}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service: {service}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Location: {location}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Staff: {staff}</span></p><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Track Appointment</a></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Your appointment has been successfully scheduled. If you need to make any changes or have any questions, please dont hesitate to reach out to us.<br></span><span style=\"background-color: var(--bs-card-bg); color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Were looking forward to meeting you and providing you with excellent service. Thank you for choosing {app_name} for your appointment needs.</span></p><p><span style=\"background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align); font-size: 15px; font-variant-ligatures: common-ligatures;\"><font color=\"#1d1c1d\" face=\"Slack-Lato, Slack-Fractions, appleLogo, sans-serif\">Thanks</font></span><span style=\"background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align);\"><font color=\"#1d1c1d\" face=\"Slack-Lato, Slack-Fractions, appleLogo, sans-serif\"><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight);\">,</span></font><br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{company_name}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(18, 2, 'es', 'Appointment Details', '<p>Hola,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Gracias por reservar una cita con nosotros en {app_name}. Nos complace confirmar los detalles de su cita:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Número de cita: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Fecha de la cita: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hora de la cita: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servicio: {servicio}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ubicación: {ubicación}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personal: {personal}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Seguimiento de cita</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Su cita ha sido programada exitosamente. Si necesita realizar algún cambio o tiene alguna pregunta, no dude en comunicarse con nosotros.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Esperamos conocerlo y brindarle un excelente servicio. Gracias por elegir {app_name} para sus necesidades de citas.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Gracias,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de la aplicación}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(19, 2, 'fr', 'Appointment Details', '<p>Bonjour,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Merci davoir pris rendez-vous avec nous à {app_name}. Nous sommes ravis de confirmer les détails de votre rendez-vous :</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numéro de rendez-vous : {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Date de rendez-vous : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Heure du rendez-vous : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service : {service}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Emplacement : {emplacement}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personnel : {personnel}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Suivre un rendez-vous</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Votre rendez-vous a été planifié avec succès. Si vous devez apporter des modifications ou si vous avez des questions, nhésitez pas à nous contacter.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Nous avons hâte de vous rencontrer et de vous offrir un excellent service. Merci davoir choisi {app_name} pour vos besoins en matière de rendez-vous.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Merci,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nom de lentreprise}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nom de lapplication}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(20, 2, 'it', 'Appointment Details', '<p>Ciao,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Grazie per aver prenotato un appuntamento con noi presso {app_name}. Siamo entusiasti di confermare i dettagli del tuo appuntamento:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numero dellappuntamento: {appuntamento_numero}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data dellappuntamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Orario dellappuntamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servizio: {servizio}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Posizione: {posizione}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personale: {personale}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Tieni traccia dell\'appuntamento</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Il tuo appuntamento è stato pianificato con successo. Se hai bisogno di apportare modifiche o hai domande, non esitare a contattarci.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Non vediamo lora di incontrarvi e di fornirvi un servizio eccellente. Grazie per aver scelto {app_name} per le tue esigenze di appuntamento.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Grazie,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nome della ditta}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome dellapplicazione}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(21, 2, 'ja', 'Appointment Details', '<p>こんにちは、</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name} でのご予約をいただきありがとうございます。ご予約の詳細を確認させていただきます。</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約番号: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約日: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約時間: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">サービス: {サービス}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">場所: {場所}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">スタッフ: {スタッフ}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">予約を追跡する</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予定が正常に設定されました。変更が必要な場合やご質問がある場合は、お気軽にお問い合わせください。<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">皆様にお会いし、優れたサービスを提供できることを楽しみにしています。ご予約のニーズに {app_name} をお選びいただきありがとうございます。</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">ありがとう、<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{会社名}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{アプリ名}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(22, 2, 'nl', 'Appointment Details', '<p>Hallo,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Bedankt dat u een afspraak bij ons heeft geboekt op {app_name}. We zijn verheugd om uw afspraakgegevens te bevestigen:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraaknummer: {afspraaknummer}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraakdatum: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraaktijd: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {dienst}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Locatie: {locatie}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personeel: {personeel}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Afspraak bijhouden</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Uw afspraak is succesvol ingepland. Als u wijzigingen wilt aanbrengen of vragen heeft, aarzel dan niet om contact met ons op te nemen.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Wij kijken ernaar uit u te ontmoeten en u een uitstekende service te bieden. Bedankt dat u {app_name} heeft gekozen voor uw afspraakbehoeften.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Bedankt,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Bedrijfsnaam}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{applicatie naam}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(23, 2, 'pl', 'Appointment Details', '<p>Cześć,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dziękujemy za rezerwację spotkania z nami w {app_name}. Z przyjemnością potwierdzimy szczegóły Twojej wizyty:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numer spotkania: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data spotkania: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Godzina spotkania: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Usługa: {usługa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Lokalizacja: {lokalizacja}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personel: {personel}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Śledź spotkanie</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Twoja wizyta została pomyślnie zaplanowana. Jeśli chcesz wprowadzić jakieś zmiany lub masz jakieś pytania, nie wahaj się z nami skontaktować.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Nie możemy się doczekać spotkania z Tobą i zapewnienia doskonałej obsługi. Dziękujemy, że wybrałeś aplikację {app_name} do celów związanych z spotkaniami.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dzięki,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa firmy}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa aplikacji}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(24, 2, 'ru', 'Appointment Details', '<p>Привет,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Благодарим вас за запись к нам на встречу в {app_name}. Мы рады подтвердить детали вашей встречи:</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Номер встречи: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Дата встречи: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Время встречи: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Сервис: {сервис}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Местоположение: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Персонал: {сотрудник}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Отследить встречу</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ваша встреча успешно назначена. Если вам нужно внести какие-либо изменения или у вас есть какие-либо вопросы, пожалуйста, не стесняйтесь обращаться к нам.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Мы с нетерпением ждем встречи с вами и предоставления вам отличного сервиса. Благодарим вас за выбор {app_name} для назначения встреч.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Спасибо,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Название компании}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Имя приложения}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(25, 2, 'pt', 'Appointment Details', '<p>Olá,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Obrigado por marcar uma consulta conosco em {app_name}. Temos o prazer de confirmar os detalhes do seu agendamento:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Número do agendamento: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data do agendamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Horário do agendamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Serviço: {serviço}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Localização: {local}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Equipe: {equipe}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Acompanhar compromisso</a></p></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Seu compromisso foi agendado com sucesso. Se você precisar fazer alguma alteração ou tiver alguma dúvida, não hesite em nos contatar.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Estamos ansiosos para conhecê-lo e oferecer-lhe um excelente serviço. Obrigado por escolher o {app_name} para suas necessidades de agendamento.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Obrigado,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome da empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome do aplicativo}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(26, 2, 'tr', 'Appointment Details', '<p><font color=\"#1d1c1d\" face=\"Slack-Lato, Slack-Fractions, appleLogo, sans-serif\"><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures;\">Merhaba,</span></font></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Bizimle {app_name} üzerinden randevu aldığınız için teşekkür ederiz. Randevu ayrıntılarınızı onaylamaktan heyecan duyuyoruz:</span><br></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Numarası: {appointment_number}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Tarihi: {appointment_date}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Saati: {appointment_time}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hizmet: {hizmet}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Konum: {konum}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personel: {personel}</span><p><a href=\"{tracking_url}\" style=\"background-color: #2d3748; color: white; padding: 10px 20px; text-align: center; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Randevuyu Takip Et</a></p></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevunuz başarıyla planlandı. Herhangi bir değişiklik yapmanız gerekiyorsa veya herhangi bir sorunuz varsa lütfen bizimle iletişime geçmekten çekinmeyin.<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Sizinle tanışmak ve size mükemmel hizmet sunmak için sabırsızlanıyoruz. Randevu ihtiyaçlarınız için {app_name} uygulamasını seçtiğiniz için teşekkür ederiz.</span></p><p><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Teşekkürler,<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Firma Adı}<br></span><span style=\"font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{uygulama ismi}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Tracking URL\": \"tracking_url\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30');
INSERT INTO `email_template_langs` (`id`, `parent_id`, `lang`, `subject`, `content`, `module_name`, `variables`, `created_at`, `updated_at`) VALUES
(27, 3, 'ar', 'Appointment Status Change', '<p>مرحبًا،</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">أردنا إبلاغك بالتحديث الأخير بخصوص موعدك معنا في {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">فيما يلي التفاصيل المحدثة:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">رقم الموعد : {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">تاريخ الموعد : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">وقت الموعد : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">الخدمة : {خدمة}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">تم تحديث حالة موعدك. يرجى مراجعة التغييرات وإعلامنا إذا كان لديك أي أسئلة أو استفسارات.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">نشكرك على اختيار {app_name} لاحتياجات موعدك. نحن نقدر ثقتكم بنا ونتطلع لخدمتكم.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">شكرًا،<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم الشركة}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم التطبيق}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(28, 3, 'da', 'Appointment Status Change', '<p>Hej,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Vi vil gerne informere dig om en nylig opdatering vedrørende din aftale med os på {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Her er de opdaterede detaljer:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftalenummer: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaledato: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaletidspunkt: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service: {service}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Din aftalestatus er blevet opdateret. Gennemgå ændringerne, og lad os vide, hvis du har spørgsmål eller bekymringer.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Tak, fordi du valgte {app_name} til dine aftalebehov. Vi værdsætter din tillid til os og ser frem til at tjene dig.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Tak,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{firmanavn}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(29, 3, 'de', 'Appointment Status Change', '<p>Hallo,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Wir wollten Sie über ein aktuelles Update bezüglich Ihres Termins bei uns bei {app_name} informieren.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hier sind die aktualisierten Details:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Terminnummer: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {Dienst}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ihr Terminstatus wurde aktualisiert. Bitte überprüfen Sie die Änderungen und teilen Sie uns mit, wenn Sie Fragen oder Bedenken haben.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Vielen Dank, dass Sie sich für {app_name} für Ihren Terminbedarf entschieden haben. Wir wissen Ihr Vertrauen zu schätzen und freuen uns darauf, Sie zu betreuen.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Danke,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Name der Firma}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{App Name}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(30, 3, 'en', 'Appointment Status Change', '<p>Hello,&nbsp;</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">We wanted to inform you about a recent update regarding your appointment with us at {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Here are the updated details:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Number : {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Date : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Time : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service : {service}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Your appointment status has been updated. Please review the changes and let us know if you have any questions or concerns.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Thank you for choosing {app_name} for your appointment needs. We value your trust in us and look forward to serving you.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Thanks,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{company_name}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(31, 3, 'es', 'Appointment Status Change', '<p>Hola,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Queríamos informarle sobre una actualización reciente sobre su cita con nosotros en {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aquí están los detalles actualizados:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Número de cita: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Fecha de la cita: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hora de la cita: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servicio: {servicio}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">El estado de su cita ha sido actualizado. Revise los cambios y avísenos si tiene alguna pregunta o inquietud.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Gracias por elegir {app_name} para sus necesidades de citas. Valoramos su confianza en nosotros y esperamos poder servirle.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Gracias,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de la aplicación}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(32, 3, 'fr', 'Appointment Status Change', '<p>Bonjour,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Nous souhaitions vous informer dune mise à jour récente concernant votre rendez-vous avec nous à {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Voici les détails mis à jour :</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numéro de rendez-vous : {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Date de rendez-vous : {appointment_date}|<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Heure du rendez-vous : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service : {service}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Le statut de votre rendez-vous a été mis à jour. Veuillez examiner les modifications et faites-nous savoir si vous avez des questions ou des préoccupations.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Merci davoir choisi {app_name} pour vos besoins en matière de rendez-vous. Nous apprécions votre confiance en nous et sommes impatients de vous servir.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Merci,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nom de lentreprise}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nom de lapplication}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(33, 3, 'it', 'Appointment Status Change', '<p>Ciao,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Volevamo informarti di un recente aggiornamento riguardante il tuo appuntamento con noi presso {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ecco i dettagli aggiornati:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numero dellappuntamento: {appuntamento_numero}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data dellappuntamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Orario dellappuntamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servizio: {servizio}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Lo stato del tuo appuntamento è stato aggiornato. Ti invitiamo a rivedere le modifiche e a farci sapere se hai domande o dubbi.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Grazie per aver scelto {app_name} per le tue esigenze di appuntamento. Apprezziamo la tua fiducia in noi e non vediamo lora di servirti.</span></p><p>Grazie,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nome della ditta}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome dellapplicazione}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(34, 3, 'ja', 'Appointment Status Change', '<p>こんにちは、</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name} でのご予約に関する最新情報をお知らせいたします。<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">更新された詳細は次のとおりです。</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約番号 : {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約日 : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約時間 : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">サービス : {サービス}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約状況が更新されました。変更内容をご確認いただき、ご質問やご不明な点がございましたらお知らせください。<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">ご予約のニーズに {app_name} をお選びいただきありがとうございます。私たちはお客様の信頼を大切にし、お客様のお役にたてることを楽しみにしています。</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">ありがとう、<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{会社名}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{アプリ名}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(35, 3, 'nl', 'Appointment Status Change', '<p>Hallo,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">We willen u informeren over een recente update over uw afspraak bij ons op {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hier zijn de bijgewerkte details:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraaknummer: {afspraaknummer}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraakdatum: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraaktijd: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {dienst}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Uw afspraakstatus is bijgewerkt. Controleer de wijzigingen en laat het ons weten als u vragen of opmerkingen heeft.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Bedankt dat u {app_name} heeft gekozen voor uw afspraakbehoeften. Wij waarderen uw vertrouwen in ons en kijken ernaar uit u van dienst te zijn.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Bedankt,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Bedrijfsnaam}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{applicatie naam}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(36, 3, 'pl', 'Appointment Status Change', '<p>Cześć,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Chcieliśmy poinformować Cię o ostatniej aktualizacji dotyczącej Twojej wizyty w firmie {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Oto zaktualizowane szczegóły:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Numer spotkania: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data spotkania: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Godzina spotkania: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Usługa: {usługa}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Status Twojego spotkania został zaktualizowany. Zapoznaj się ze zmianami i daj nam znać, jeśli masz jakiekolwiek pytania lub wątpliwości.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dziękujemy, że wybrałeś aplikację {app_name} do celów związanych z spotkaniami. Cenimy Twoje zaufanie i cieszymy się, że możemy Ci służyć.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dzięki,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa firmy}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa aplikacji}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(37, 3, 'ru', 'Appointment Status Change', '<p>Привет,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Мы хотели сообщить вам о недавней новости о вашей встрече с нами в {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Вот обновленные подробности:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Номер встречи: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Дата встречи: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Время встречи: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Сервис: {сервис}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Статус вашей встречи обновлен. Пожалуйста, ознакомьтесь с изменениями и сообщите нам, если у вас возникнут какие-либо вопросы или замечания.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Благодарим вас за выбор {app_name} для назначения встреч. Мы ценим ваше доверие к нам и с нетерпением ждем возможности служить вам.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Спасибо,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Название компании}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Имя приложения}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(38, 3, 'pt', 'Appointment Status Change', '<p>Olá,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Gostaríamos de informá-lo sobre uma atualização recente sobre seu compromisso conosco em {app_name}.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aqui estão os detalhes atualizados:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Número do agendamento: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data do agendamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Horário do agendamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Serviço: {serviço}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">O status do seu agendamento foi atualizado. Revise as alterações e informe-nos se tiver alguma dúvida ou preocupação.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Obrigado por escolher o {app_name} para suas necessidades de agendamento. Valorizamos sua confiança em nós e estamos ansiosos para atendê-lo.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Obrigado,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome da empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome do aplicativo}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(39, 3, 'tr', 'Appointment Status Change', '<p>Merhaba,</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name} uygulamasında bizimle randevunuzla ilgili son güncelleme hakkında sizi bilgilendirmek istedik.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">İşte güncellenen ayrıntılar:</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Numarası: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Tarihi : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Saati : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hizmet: {hizmet}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu durumunuz güncellendi. Lütfen değişiklikleri inceleyin ve herhangi bir sorunuz veya endişeniz varsa bize bildirin.<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu ihtiyaçlarınız için {app_name} uygulamasını seçtiğiniz için teşekkür ederiz. Bize olan güveninize değer veriyoruz ve size hizmet etmek için sabırsızlanıyoruz.</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Teşekkürler,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Firma Adı}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{uygulama ismi}</span></p>', NULL, '{\"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(40, 4, 'ar', 'Appointment Reminder', '<p>مرحبًا {العميل}،</p><p>نأمل أن تجدك هذه الرسالة بخير.</p><p>هذا تذكير ودي بموعدك القادم مع {app_name}. التفاصيل هنا:</p><p>رقم الموعد: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">تاريخ الموعد: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">وقت الموعد: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">الخدمة: {خدمة}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">الموقع: {الموقع}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">طاقم العمل: {طاقم العمل}</span></p><p>نحن نتطلع إلى الترحيب بك في {appointment_date} في {appointment_time}. إذا كنت بحاجة إلى إجراء أي تغييرات أو كانت لديك أي أسئلة، فلا تتردد في الاتصال بنا.</p><p>شكرًا،<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم الشركة}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{اسم التطبيق}</span></p><div><br></div>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(41, 4, 'da', 'Appointment Reminder', '<p>Hej {kunde}</p><p>Vi håber, at denne besked finder dig godt.</p><p>Dette er en venlig påmindelse om din kommende aftale med {app_name}. Her er detaljerne:</p><p>Aftalenummer: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaledato: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Aftaletidspunkt: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service: {service}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Placering: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personale: {staff}</span></p><p>Vi ser frem til at byde dig velkommen den {appointment_date} kl. {appointment_time}. Hvis du har brug for at foretage ændringer eller har spørgsmål, så tøv ikke med at kontakte os.</p><p>Tak,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{firmanavn}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(42, 4, 'de', 'Appointment Reminder', '<p>Hallo {Kunde},</p><p>Wir hoffen, dass diese Nachricht Sie gut findet.</p><p>Dies ist eine freundliche Erinnerung an Ihren bevorstehenden Termin mit {app_name}. Hier sind die Details:</p><p>Terminnummer: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Termin: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {Dienst}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Standort: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Mitarbeiter: {Mitarbeiter}</span></p><p>Wir freuen uns, Sie am {appointment_date} um {appointment_time} begrüßen zu dürfen. Wenn Sie Änderungen vornehmen müssen oder Fragen haben, zögern Sie bitte nicht, mit uns Kontakt aufzunehmen.</p><p>Danke,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Name der Firma}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{App Name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(43, 4, 'en', 'Appointment Reminder', '<p>Hello {customer},&nbsp;</p><p>We hope this message finds you well.</p><p>This is a friendly reminder of your upcoming appointment with {app_name}. Here are the details:</p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Number: {appointment_number}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Date: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Appointment Time: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service: {service}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Location: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Staff: {staff}</span></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">We look forward to welcoming you on {appointment_date} at {appointment_time}. If you need to make any changes or have any questions, please dont hesitate to get in touch with us.</span><br></p><p><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Thanks,<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{company_name}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{app_name}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(44, 4, 'es', 'Appointment Reminder', '<p>Hola {cliente},</p><p>Esperamos que este mensaje te encuentre bien.</p><p>Este es un recordatorio amistoso de su próxima cita con {app_name}. Aquí están los detalles:</p><p>Número de cita: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Fecha de la cita: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hora de la cita: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servicio: {servicio}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Ubicación: {ubicación}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personal: {personal}</span></p><p>Esperamos darle la bienvenida el {appointment_date} a las {appointment_time}. Si necesita realizar algún cambio o tiene alguna pregunta, no dude en ponerse en contacto con nosotros.</p><p>Gracias,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nombre de la aplicación}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(45, 4, 'fr', 'Appointment Reminder', '<p>Bonjour {client},</p><p>Nous espérons que ce message vous trouvera bien.</p><p>Ceci est un rappel amical de votre prochain rendez-vous avec {app_name}. Voici les détails:</p><p>Numéro de rendez-vous : {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Date de rendez-vous : {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Heure du rendez-vous : {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Service : {service}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Emplacement : {emplacement}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personnel : {personnel}</span></p><p>Nous sommes impatients de vous accueillir le {appointment_date} à {appointment_time}. Si vous devez apporter des modifications ou si vous avez des questions, nhésitez pas à nous contacter.</p><p>Merci,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nom de lentreprise}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nom de lapplication}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(46, 4, 'it', 'Appointment Reminder', '<p>Ciao {cliente},</p><p>Ci auguriamo che questo messaggio ti trovi bene.</p><p>Questo è un promemoria amichevole del tuo prossimo appuntamento con {app_name}. Ecco i dettagli:</p><p>Numero dellappuntamento: {appuntamento_numero}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data dellappuntamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Orario dellappuntamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Servizio: {servizio}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Posizione: {posizione}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personale: {personale}</span></p><p>Non vediamo lora di darti il ​​benvenuto il {appointment_date} alle {appointment_time}. Se hai bisogno di apportare modifiche o hai domande, non esitare a contattarci.</p><p>Grazie,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nome della ditta}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome dellapplicazione}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(47, 4, 'ja', 'Appointment Reminder', '<p>こんにちは、{顧客} 様</p><p>このメッセージがあなたに元気を与えてくれることを願っています。</p><p>これは、{app_name} との今後の予定についてのフレンドリーなリマインダーです。詳細は次のとおりです。</p><p>予約番号: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約日: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">予約時間: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">サービス: {サービス}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">場所: {場所}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">スタッフ: {スタッフ}</span></p><p>{appointment_date} の {appointment_time} にお会いできることを楽しみにしています。変更が必要な場合やご質問がある場合は、お気軽にお問い合わせください。</p><p>ありがとう、<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{会社名}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{アプリ名}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30');
INSERT INTO `email_template_langs` (`id`, `parent_id`, `lang`, `subject`, `content`, `module_name`, `variables`, `created_at`, `updated_at`) VALUES
(48, 4, 'nl', 'Appointment Reminder', '<p>Hallo {klant},</p><p>Wij hopen dat dit bericht u goed treft.</p><p>Dit is een vriendelijke herinnering aan uw aanstaande afspraak met {app_name}. Hier zijn de details:</p><p>Afspraaknummer: {afspraaknummer}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraakdatum: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Afspraaktijd: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Dienst: {dienst}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Locatie: {locatie}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personeel: {personeel}</span></p><p>We kijken ernaar uit u te verwelkomen op {appointment_date} om {appointment_time}. Als u wijzigingen wilt aanbrengen of vragen heeft, aarzel dan niet om contact met ons op te nemen.</p><p>Bedankt,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Bedrijfsnaam}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{applicatie naam}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(49, 4, 'pl', 'Appointment Reminder', '<p>Witaj {kliencie},</p><p>Mamy nadzieję, że ta wiadomość zastanie Cię w dobrym zdrowiu.</p><p>To przyjazne przypomnienie o zbliżającym się spotkaniu w aplikacji {app_name}. Oto szczegóły:</p><p>Numer spotkania: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data spotkania: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Godzina spotkania: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Usługa: {usługa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Lokalizacja: {lokalizacja}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personel: {personel}</span></p><p>Czekamy na Ciebie w dniu {appointment_date} o godzinie {appointment_time}. Jeśli chcesz wprowadzić jakieś zmiany lub masz jakieś pytania, nie wahaj się z nami skontaktować.</p><p>Dzięki,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa firmy}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Nazwa aplikacji}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(50, 4, 'ru', 'Appointment Reminder', '<p>Здравствуйте, {customer}!</p><p>Мы надеемся, что это сообщение застанет вас в добром здравии.</p><p>Это дружеское напоминание о вашей предстоящей встрече с {app_name}. Вот подробности:</p><p>Номер встречи: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Дата встречи: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Время встречи: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Сервис: {сервис}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Местоположение: {location}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Персонал: {сотрудник}</span></p><p>Мы с нетерпением ждем встречи с вами {appointment_date} в {appointment_time}. Если вам нужно внести какие-либо изменения или у вас есть какие-либо вопросы, пожалуйста, не стесняйтесь обращаться к нам.</p><p>Спасибо,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Название компании}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Имя приложения}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(51, 4, 'pt', 'Appointment Reminder', '<p>Olá {cliente},</p><p>Esperamos que esta mensagem o encontre bem.</p><p>Este é um lembrete amigável do seu próximo compromisso com {app_name}. Aqui estão os detalhes:</p><p>Número do agendamento: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Data do agendamento: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Horário do agendamento: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Serviço: {serviço}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Localização: {local}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Equipe: {equipe}</span></p><p>Esperamos recebê-lo em {appointment_date} às {appointment_time}. Se precisar fazer alguma alteração ou tiver alguma dúvida, não hesite em entrar em contato conosco.</p><p>Obrigado,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome da empresa}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{nome do aplicativo}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(52, 4, 'tr', 'Appointment Reminder', '<p>Merhaba {müşteri},</p><p>Bu mesajın sizi iyi bulacağını umuyoruz.</p><p>Bu, {app_name} ile yaklaşan randevunuzla ilgili dostça bir hatırlatmadır. Detaylar burada:</p><p>Randevu Numarası: {appointment_number}<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Tarihi: {appointment_date}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Randevu Saati: {appointment_time}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Hizmet: {hizmet}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Konum: {konum}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">Personel: {personel}</span></p><p>Sizi {appointment_date} tarihinde, {appointment_time} saatinde karşılamayı sabırsızlıkla bekliyoruz. Herhangi bir değişiklik yapmanız gerekiyorsa veya herhangi bir sorunuz varsa, lütfen bizimle iletişime geçmekten çekinmeyin.</p><p>Teşekkürler,<br><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{Firma Adı}<br></span><span style=\"background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);\">{uygulama ismi}</span></p>', NULL, '{\"Staff\": \"staff\", \"App Url\": \"app_url\", \"App Name\": \"app_name\", \"Service \": \"service\", \"customer\": \"customer\", \"Location \": \"location\", \"Company Name\": \"company_name\", \"Appointment Date\": \"appointment_date\", \"Appointment Time\": \"appointment_time\", \"Appointment Number\": \"appointment_number\"}', '2025-12-26 13:29:30', '2025-12-26 13:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `key`, `label`, `value`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'is_enable', 'Patient Prescription ( Upload Here )', 'on', 2, 3, '2025-12-26 14:32:42', '2025-12-26 14:32:42');

-- --------------------------------------------------------

--
-- Table structure for table `join_us`
--

CREATE TABLE `join_us` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `landingpage_pixels`
--

CREATE TABLE `landingpage_pixels` (
  `id` bigint UNSIGNED NOT NULL,
  `platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pixel_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `landing_page_settings`
--

CREATE TABLE `landing_page_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `code`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ar', 'Arabic', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(2, 'da', 'Danish', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(3, 'de', 'German', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(4, 'en', 'English', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(5, 'es', 'Spanish', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(6, 'fr', 'French', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(7, 'it', 'Italian', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(8, 'ja', 'Japanese', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(9, 'nl', 'Dutch', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(10, 'pl', 'Polish', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(11, 'pt', 'Portuguese', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(12, 'ru', 'Russian', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29'),
(13, 'tr', 'Turkish', '1', '2025-12-26 10:43:29', '2025-12-26 10:43:29');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `image`, `phone`, `address`, `description`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'AMAD Diagnostic Centre', NULL, NULL, 'House 3, near Civil Line Police Station Rd, Erigation Rd Civil Lines, Gujranwala', NULL, 2, 3, '2025-12-26 12:44:50', '2025-12-26 12:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `login_details`
--

CREATE TABLE `login_details` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `business` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_details`
--

INSERT INTO `login_details` (`id`, `user_id`, `ip`, `date`, `details`, `type`, `created_by`, `business`, `created_at`, `updated_at`) VALUES
(1, '3', '172.68.242.68', '2025-12-29 07:53:20', '{\"status\":\"success\",\"country\":\"Singapore\",\"countryCode\":\"SG\",\"region\":\"03\",\"regionName\":\"North West\",\"city\":\"Singapore\",\"zip\":\"858877\",\"lat\":1.352,\"lon\":103.8198,\"timezone\":\"Asia\\/Singapore\",\"isp\":\"Cloudflare, Inc.\",\"org\":\"Cloudflare WARP\",\"as\":\"AS13335 Cloudflare, Inc.\",\"query\":\"172.68.242.68\",\"browser_name\":\"Chrome\",\"os_name\":\"Windows\",\"browser_language\":\"en\",\"device_type\":\"desktop\",\"referrer_host\":true,\"referrer_path\":true}', 'company', 3, 2, '2025-12-29 07:53:20', '2025-12-29 07:53:20'),
(2, '3', '172.71.152.89', '2025-12-29 10:08:19', '{\"status\":\"success\",\"country\":\"Singapore\",\"countryCode\":\"SG\",\"region\":\"03\",\"regionName\":\"North West\",\"city\":\"Singapore\",\"zip\":\"858877\",\"lat\":1.352,\"lon\":103.8198,\"timezone\":\"Asia\\/Singapore\",\"isp\":\"Cloudflare, Inc.\",\"org\":\"Cloudflare WARP\",\"as\":\"AS13335 Cloudflare, Inc.\",\"query\":\"172.71.152.89\",\"browser_name\":\"Chrome\",\"os_name\":\"Windows\",\"browser_language\":\"en\",\"device_type\":\"desktop\",\"referrer_host\":true,\"referrer_path\":true}', 'company', 3, 2, '2025-12-29 10:08:19', '2025-12-29 10:08:19'),
(3, '3', '172.71.124.118', '2025-12-30 04:28:30', '{\"status\":\"success\",\"country\":\"Singapore\",\"countryCode\":\"SG\",\"region\":\"03\",\"regionName\":\"North West\",\"city\":\"Singapore\",\"zip\":\"858877\",\"lat\":1.352,\"lon\":103.8198,\"timezone\":\"Asia\\/Singapore\",\"isp\":\"Cloudflare, Inc.\",\"org\":\"Cloudflare WARP\",\"as\":\"AS13335 Cloudflare, Inc.\",\"query\":\"172.71.124.118\",\"browser_name\":\"Chrome\",\"os_name\":\"Windows\",\"browser_language\":\"en\",\"device_type\":\"desktop\",\"referrer_host\":true,\"referrer_path\":true}', 'company', 3, 2, '2025-12-30 04:28:30', '2025-12-30 04:28:30'),
(4, '3', '172.71.152.89', '2025-12-30 06:45:13', '{\"status\":\"success\",\"country\":\"Singapore\",\"countryCode\":\"SG\",\"region\":\"03\",\"regionName\":\"North West\",\"city\":\"Singapore\",\"zip\":\"858877\",\"lat\":1.352,\"lon\":103.8198,\"timezone\":\"Asia\\/Singapore\",\"isp\":\"Cloudflare, Inc.\",\"org\":\"Cloudflare WARP\",\"as\":\"AS13335 Cloudflare, Inc.\",\"query\":\"172.71.152.89\",\"browser_name\":\"Chrome\",\"os_name\":\"Windows\",\"browser_language\":\"en\",\"device_type\":\"desktop\",\"referrer_host\":true,\"referrer_path\":true}', 'company', 3, 2, '2025-12-30 06:45:13', '2025-12-30 06:45:13'),
(5, '3', '172.71.152.90', '2025-12-30 08:10:52', '{\"status\":\"success\",\"country\":\"Singapore\",\"countryCode\":\"SG\",\"region\":\"03\",\"regionName\":\"North West\",\"city\":\"Singapore\",\"zip\":\"858877\",\"lat\":1.352,\"lon\":103.8198,\"timezone\":\"Asia\\/Singapore\",\"isp\":\"Cloudflare, Inc.\",\"org\":\"Cloudflare WARP\",\"as\":\"AS13335 Cloudflare, Inc.\",\"query\":\"172.71.152.90\",\"browser_name\":\"Chrome\",\"os_name\":\"Windows\",\"browser_language\":\"en\",\"device_type\":\"desktop\",\"referrer_host\":true,\"referrer_path\":true}', 'company', 3, 2, '2025-12-30 08:10:52', '2025-12-30 08:10:52');

-- --------------------------------------------------------

--
-- Table structure for table `marketplace_page_settings`
--

CREATE TABLE `marketplace_page_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '2014_10_12_000000_create_users_table', 1),
(3, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(4, '2014_10_12_100000_create_password_resets_table', 1),
(5, '2019_05_03_000002_create_subscriptions_table', 1),
(6, '2019_05_03_000003_create_receipts_table', 1),
(7, '2019_05_03_000003_create_subscription_items_table', 1),
(8, '2019_05_03_000004_create_transactions_table', 1),
(9, '2019_08_19_000000_create_failed_jobs_table', 1),
(10, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(11, '2023_10_18_110722_laratrust_setup_tables', 1),
(12, '2023_10_26_070645_create_settings_table', 1),
(13, '2023_10_26_070942_create_user_active_modules_table', 1),
(14, '2023_10_26_115816_create_add_ons_table', 1),
(15, '2023_10_27_101918_create_currencies_table', 1),
(16, '2023_10_27_123013_create_plans_table', 1),
(17, '2023_10_30_064000_create_coupons_table', 1),
(18, '2023_10_30_070428_create_user_coupons_table', 1),
(19, '2023_10_30_095152_create_orders_table', 1),
(20, '2023_10_30_105927_create_languages_table', 1),
(21, '2023_10_31_041456_create_bank_transfer_payments_table', 1),
(22, '2024_01_03_111111_create_businesses_table', 1),
(23, '2024_01_03_120907_create_login_details_table', 1),
(24, '2024_01_05_040107_create_locations_table', 1),
(25, '2024_01_05_054533_create_categories_table', 1),
(26, '2024_01_05_063646_create_services_table', 1),
(27, '2024_01_05_091920_create_staff_table', 1),
(28, '2024_01_08_091123_create_customers_table', 1),
(29, '2024_01_09_055753_create_business_hours_table', 1),
(30, '2024_01_11_031244_create_business_holidays_table', 1),
(32, '2024_01_23_043343_create_appointment_payments_table', 1),
(33, '2024_02_08_120809_add_is_enabled_to_plans_table', 1),
(34, '2024_02_09_120525_create_custom_statuses_table', 1),
(35, '2024_02_14_065118_create_notifications_table', 1),
(36, '2024_02_14_065925_create_email_templates_table', 1),
(37, '2024_02_14_070400_create_email_template_langs_table', 1),
(38, '2024_02_19_035940_create_files_table', 1),
(39, '2024_02_19_090509_add_attachment_to_appointments_table', 1),
(40, '2024_02_20_043459_create_custom_fields_table', 1),
(41, '2024_02_20_071421_add_custom_field_to_appointments_table', 1),
(42, '2024_02_22_061754_add_form_type_to_businesses_table', 1),
(43, '2024_03_12_121509_create_theme_settings_table', 1),
(44, '2024_03_18_035835_create_contact_us_table', 1),
(45, '2024_03_18_044744_create_blogs_table', 1),
(46, '2024_03_18_062406_create_testimonials_table', 1),
(47, '2024_03_19_064432_add_theme_color_to_businesses_table', 1),
(48, '2024_03_21_052938_change_business_field_nullable_in_login_details', 1),
(49, '2024_03_21_072203_create_subscribes_table', 1),
(50, '2024_03_21_104511_add_status_color_to_custom_statuses_table', 1),
(51, '2024_04_02_095524_create_notification_template_langs_table', 1),
(52, '2024_04_02_115823_create_landing_page_settings_table', 1),
(53, '2024_04_03_111218_create_join_us_table', 1),
(54, '2024_04_03_121928_create_landingpage_pixels_table', 1),
(55, '2024_04_04_102134_create_marketplace_page_settings_table', 1),
(56, '2024_04_10_094813_rename_workspace_in_bank_transfer_payments', 1),
(57, '2024_08_21_051103_add_is_free_to_services_table', 1),
(58, '2024_08_21_052359_change_price_field_nullable_in_services', 1),
(59, '2024_09_02_120706_add_image_to_add_ons_table', 1),
(60, '2024_10_03_104046_alter_table_locations_change_description', 1),
(61, '2024_10_03_111108_alter_table_services_change_description', 1),
(62, '2024_10_03_111408_alter_table_staff_change_description', 1),
(63, '2024_10_09_044307_change_appointment_id_field_in_appointment_payments', 1),
(64, '2024_10_17_094039_add_option_column_custom_field_table', 1),
(65, '2024_10_17_103134_change_notes_column_appointment_table', 1),
(66, '2024_10_17_111408_alter_table_customer_change_description', 1),
(67, '2024_10_17_115829_change_description_column_contact_us_table', 1),
(68, '2024_10_17_121149_change_label_column_files_table', 1),
(69, '2024_10_17_125707_change_description_column_testimonials_table', 1),
(70, '2024_10_17_130500_change_description_column_blogs_table', 1),
(71, '2025_03_18_093647_update_tables_for_changes', 1),
(72, '2025_05_08_102305_trigger_updater', 1),
(73, '2024_01_17_035519_create_appointments_table', 2),
(74, '2025_12_27_120900_fix_missing_appointment_columns', 3);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(188) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `module`, `type`, `action`, `status`, `permissions`, `business_id`, `created_at`, `updated_at`) VALUES
(1, 'general', 'mail', 'Create User', 'on', 'user manage', 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(2, 'general', 'mail', 'Create Appointment', 'on', 'appointment manage', 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(3, 'general', 'mail', 'Appointment Status Change', 'on', 'appointment manage', 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29'),
(4, 'general', 'mail', 'Appointment Reminder', 'on', 'appointment manage', 0, '2025-12-26 13:29:29', '2025-12-26 13:29:29');

-- --------------------------------------------------------

--
-- Table structure for table `notification_template_langs`
--

CREATE TABLE `notification_template_langs` (
  `id` bigint UNSIGNED NOT NULL,
  `parent_id` int NOT NULL DEFAULT '0',
  `lang` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `variables` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_exp_month` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_exp_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_id` int NOT NULL,
  `price` double NOT NULL,
  `price_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `txn_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Base',
  `created_by` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `display_name`, `description`, `guard_name`, `module`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'user manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(2, 'user create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(3, 'user edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(4, 'user delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(5, 'user profile manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(6, 'user reset password', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(7, 'user login manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(8, 'user logs history', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(9, 'setting manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(10, 'setting storage manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(11, 'coupon manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:09', '2025-12-26 14:03:09'),
(12, 'coupon create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(13, 'coupon edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(14, 'coupon delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(15, 'plan manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(16, 'plan create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(17, 'plan edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(18, 'plan delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(19, 'plan orders', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(20, 'module manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(21, 'module add', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(22, 'module remove', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(23, 'module edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(24, 'language manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(25, 'language create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(26, 'language delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(27, 'email template manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:10', '2025-12-26 14:03:10'),
(28, 'notification template manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(29, 'business manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(30, 'business create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(31, 'business edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(32, 'business delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(33, 'business update', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(34, 'location create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(35, 'location edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(36, 'location delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(37, 'service create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(38, 'service edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(39, 'service delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(40, 'staff create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(41, 'staff edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(42, 'staff delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(43, 'category create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(44, 'category edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(45, 'category delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(46, 'holiday create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:11', '2025-12-26 14:03:11'),
(47, 'holiday edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(48, 'holiday delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(49, 'appointment manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(50, 'appointment create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(51, 'appointment edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(52, 'appointment delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(53, 'customer manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(54, 'customer create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(55, 'customer edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(56, 'customer delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(57, 'roles manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(58, 'roles create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(59, 'roles edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:12', '2025-12-26 14:03:12'),
(60, 'roles delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(61, 'plan purchase', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(62, 'plan subscribe', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(63, 'status manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(64, 'status create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(65, 'status update', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(66, 'status delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(67, 'blog manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(68, 'blog create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(69, 'blog edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(70, 'blog delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(71, 'testimonial manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(72, 'testimonial create', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(73, 'testimonial edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(74, 'testimonial delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:13', '2025-12-26 14:03:13'),
(75, 'contact manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14'),
(76, 'contact delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14'),
(77, 'subscriber manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14'),
(78, 'subscriber delete', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14'),
(79, 'theme manage', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14'),
(80, 'theme edit', NULL, NULL, 'web', 'General', 1, '2025-12-26 14:03:14', '2025-12-26 14:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `permission_role`
--

CREATE TABLE `permission_role` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permission_role`
--

INSERT INTO `permission_role` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(27, 1),
(28, 1),
(29, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(43, 1),
(44, 1),
(45, 1),
(46, 1),
(47, 1),
(48, 1),
(49, 1),
(50, 1),
(51, 1),
(52, 1),
(53, 1),
(54, 1),
(55, 1),
(56, 1),
(57, 1),
(58, 1),
(59, 1),
(60, 1),
(61, 1),
(62, 1),
(63, 1),
(64, 1),
(65, 1),
(66, 1),
(67, 1),
(68, 1),
(69, 1),
(70, 1),
(71, 1),
(72, 1),
(73, 1),
(74, 1),
(75, 1),
(76, 1),
(77, 1),
(78, 1),
(79, 1),
(80, 1),
(1, 2),
(2, 2),
(3, 2),
(4, 2),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(16, 2),
(17, 2),
(18, 2),
(19, 2),
(20, 2),
(21, 2),
(22, 2),
(23, 2),
(24, 2),
(25, 2),
(26, 2),
(27, 2),
(28, 2),
(29, 2),
(30, 2),
(31, 2),
(32, 2),
(33, 2),
(34, 2),
(35, 2),
(36, 2),
(37, 2),
(38, 2),
(39, 2),
(40, 2),
(41, 2),
(42, 2),
(43, 2),
(44, 2),
(45, 2),
(46, 2),
(47, 2),
(48, 2),
(49, 2),
(50, 2),
(51, 2),
(52, 2),
(53, 2),
(54, 2),
(55, 2),
(56, 2),
(57, 2),
(58, 2),
(59, 2),
(60, 2),
(61, 2),
(62, 2),
(63, 2),
(64, 2),
(65, 2),
(66, 2),
(67, 2),
(68, 2),
(69, 2),
(70, 2),
(71, 2),
(72, 2),
(73, 2),
(74, 2),
(75, 2),
(76, 2),
(77, 2),
(78, 2),
(79, 2),
(80, 2),
(1, 5),
(2, 5),
(3, 5),
(4, 5),
(5, 5),
(6, 5),
(7, 5),
(8, 5),
(11, 5),
(12, 5),
(13, 5),
(14, 5),
(15, 5),
(16, 5),
(17, 5),
(18, 5),
(19, 5),
(37, 5),
(38, 5),
(39, 5),
(61, 5),
(62, 5),
(1, 6),
(2, 6),
(3, 6),
(5, 6),
(6, 6),
(7, 6),
(8, 6),
(11, 6),
(12, 6),
(13, 6),
(15, 6),
(16, 6),
(17, 6),
(19, 6),
(34, 6),
(35, 6),
(49, 6),
(50, 6),
(51, 6),
(53, 6),
(54, 6),
(55, 6),
(61, 6),
(62, 6),
(63, 6),
(64, 6),
(65, 6);

-- --------------------------------------------------------

--
-- Table structure for table `permission_user`
--

CREATE TABLE `permission_user` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_of_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_plan` int NOT NULL DEFAULT '0',
  `active` int NOT NULL DEFAULT '1',
  `is_free_plan` int NOT NULL DEFAULT '0',
  `package_price_monthly` double NOT NULL DEFAULT '0',
  `package_price_yearly` double NOT NULL DEFAULT '0',
  `price_per_user_monthly` double NOT NULL DEFAULT '0',
  `price_per_user_yearly` double NOT NULL DEFAULT '0',
  `price_per_business_monthly` int NOT NULL DEFAULT '0',
  `price_per_business_yearly` int NOT NULL DEFAULT '0',
  `modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `trial` int NOT NULL DEFAULT '0',
  `trial_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` int NOT NULL DEFAULT '1',
  `number_of_business` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `number_of_user`, `custom_plan`, `active`, `is_free_plan`, `package_price_monthly`, `package_price_yearly`, `price_per_user_monthly`, `price_per_user_yearly`, `price_per_business_monthly`, `price_per_business_yearly`, `modules`, `trial`, `trial_days`, `is_enabled`, `number_of_business`, `created_at`, `updated_at`) VALUES
(1, NULL, '5', 1, 1, 0, 0, 0, 0, 0, 0, 0, NULL, 0, NULL, 1, '5', '2025-12-26 13:29:30', '2025-12-26 13:29:30'),
(2, 'Basic', '5', 0, 1, 1, 0, 0, 0, 0, 0, 0, 'Stripe,Paypal,GoogleCaptcha,Photography,CarService', 0, NULL, 1, '5', '2025-12-26 13:29:30', '2025-12-26 13:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` bigint UNSIGNED NOT NULL,
  `billable_id` bigint UNSIGNED NOT NULL,
  `billable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paddle_subscription_id` bigint UNSIGNED DEFAULT NULL,
  `checkout_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `receipt_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Base',
  `created_by` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `guard_name`, `module`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'super admin', 'Super Admin', 'Super Administrator with full access', 'web', 'Base', 0, '2025-12-26 13:39:47', '2025-12-26 13:39:47'),
(2, 'company', 'Company', 'Company/Business Owner', 'web', 'Base', 0, '2025-12-26 13:58:30', '2025-12-26 13:58:30'),
(3, 'staff', NULL, NULL, 'web', 'Base', 0, '2025-12-27 11:23:16', '2025-12-27 11:23:16'),
(4, 'staff', NULL, NULL, 'web', 'Base', 0, '2025-12-27 11:23:33', '2025-12-27 11:23:33'),
(5, 'Staff', NULL, NULL, 'web', 'Base', 3, '2025-12-27 11:28:19', '2025-12-27 15:12:15'),
(6, 'Receptionist', NULL, NULL, 'web', 'Base', 3, '2025-12-27 11:34:08', '2025-12-27 11:34:08'),
(7, 'customer', 'Customer', 'Customer role for clients booking appointments', 'web', 'General', 3, '2025-12-29 17:50:22', '2025-12-29 17:50:22');

-- --------------------------------------------------------

--
-- Table structure for table `role_user`
--

CREATE TABLE `role_user` (
  `role_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_user`
--

INSERT INTO `role_user` (`role_id`, `user_id`, `user_type`) VALUES
(1, 1, 'App\\Models\\User'),
(2, 3, 'App\\Models\\User'),
(3, 8, 'App\\Models\\User'),
(4, 9, 'App\\Models\\User'),
(5, 11, 'App\\Models\\User');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` bigint UNSIGNED NOT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_free` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '0 => paid, 1 => free',
  `duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `image`, `category_id`, `price`, `is_free`, `duration`, `description`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Chest X-ray (PA View)', NULL, 1, '800', NULL, '15', 'Posterior-Anterior chest radiograph for lung, heart, and thoracic evaluation', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(2, 'Chest X-ray (PA + Lateral)', NULL, 1, '1200', NULL, '20', 'Two-view chest X-ray including PA and lateral views', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(3, 'Abdomen X-ray (AP View)', NULL, 1, '900', NULL, '15', 'Anterior-Posterior abdominal radiograph', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(4, 'Abdomen X-ray (AP + Erect)', NULL, 1, '1500', NULL, '20', 'Two-view abdominal X-ray (supine and erect) for obstruction evaluation', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(5, 'KUB X-ray (Kidney, Ureter, Bladder)', NULL, 1, '1000', NULL, '15', 'X-ray of kidneys, ureters, and bladder for stones and calcification', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(6, 'Pelvis X-ray (AP View)', NULL, 1, '900', NULL, '15', 'Pelvic bones and hip joint radiograph', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(7, 'Spine X-ray (Cervical - AP + Lateral)', NULL, 1, '1800', NULL, '25', 'Neck spine X-ray (C1-C7 vertebrae)', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(8, 'Spine X-ray (Thoracic - AP + Lateral)', NULL, 1, '1800', NULL, '25', 'Mid-back spine X-ray (T1-T12 vertebrae)', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(9, 'Spine X-ray (Lumbar - AP + Lateral)', NULL, 1, '1800', NULL, '25', 'Lower back spine X-ray (L1-L5 vertebrae)', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(10, 'Skull X-ray (AP + Lateral)', NULL, 1, '1500', NULL, '20', 'Cranial bone X-ray for fracture or abnormality', 2, 3, '2025-12-26 12:33:10', '2025-12-26 12:33:10'),
(11, 'PNS X-ray (Paranasal Sinuses)', NULL, 1, '1200', NULL, '15', 'Sinus X-ray for infection or inflammation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(12, 'Shoulder X-ray (AP View)', NULL, 1, '1000', NULL, '15', 'Shoulder joint and surrounding bones', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(13, 'Elbow X-ray (AP + Lateral)', NULL, 1, '1200', NULL, '20', 'Elbow joint radiograph', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(14, 'Wrist X-ray (PA + Lateral)', NULL, 1, '1000', NULL, '15', 'Wrist bones and joint X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(15, 'Hand X-ray (PA View)', NULL, 1, '900', NULL, '15', 'Complete hand bones radiograph', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(16, 'Hip Joint X-ray (AP + Lateral)', NULL, 1, '1500', NULL, '20', 'Hip joint and femoral head X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(17, 'Knee X-ray (AP + Lateral)', NULL, 1, '1200', NULL, '20', 'Knee joint radiograph for injury or arthritis', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(18, 'Ankle X-ray (AP + Lateral)', NULL, 1, '1000', NULL, '15', 'Ankle joint and bones X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(19, 'Foot X-ray (AP + Lateral + Oblique)', NULL, 1, '1200', NULL, '20', 'Complete foot bones radiograph (3 views)', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(20, 'Femur X-ray (AP + Lateral)', NULL, 1, '1500', NULL, '20', 'Thigh bone (femur) X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(21, 'Tibia/Fibula X-ray (AP + Lateral)', NULL, 1, '1200', NULL, '20', 'Lower leg bones X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(22, 'Clavicle X-ray (AP View)', NULL, 1, '900', NULL, '15', 'Collar bone X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(23, 'Ribs X-ray (Bilateral)', NULL, 1, '1500', NULL, '20', 'Both sides rib cage X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(24, 'Sacrum/Coccyx X-ray', NULL, 1, '1200', NULL, '15', 'Tailbone and sacral bone X-ray', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(25, 'Abdominal Ultrasound (Complete)', NULL, 2, '2500', NULL, '30', 'Liver, gallbladder, pancreas, spleen, kidneys ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(26, 'Pelvic Ultrasound (Female)', NULL, 2, '2200', NULL, '25', 'Uterus, ovaries, and bladder ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(27, 'Obstetric Ultrasound (Pregnancy)', NULL, 2, '2800', NULL, '30', 'Fetal development and pregnancy monitoring', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(28, 'KUB Ultrasound (Kidney, Ureter, Bladder)', NULL, 2, '2000', NULL, '25', 'Urinary system ultrasound for stones/infection', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(29, 'Renal Ultrasound (Both Kidneys)', NULL, 2, '1800', NULL, '20', 'Kidney size, structure, and stones evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(30, 'Thyroid Ultrasound', NULL, 2, '2000', NULL, '20', 'Thyroid gland size, nodules, and abnormalities', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(31, 'Breast Ultrasound (Bilateral)', NULL, 2, '2500', NULL, '25', 'Breast tissue evaluation for lumps or masses', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(32, 'Scrotal/Testicular Ultrasound', NULL, 2, '2200', NULL, '20', 'Male reproductive organ ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(33, 'Prostate Ultrasound (Transabdominal)', NULL, 2, '2000', NULL, '20', 'Prostate gland size and structure evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(34, 'Carotid Doppler Ultrasound', NULL, 2, '3500', NULL, '35', 'Neck blood vessels ultrasound for blockage', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(35, 'Lower Limb Venous Doppler', NULL, 2, '3500', NULL, '40', 'Leg veins ultrasound for DVT (blood clots)', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(36, 'Upper Limb Arterial Doppler', NULL, 2, '3200', NULL, '35', 'Arm arteries blood flow ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(37, 'Liver Ultrasound', NULL, 2, '1800', NULL, '20', 'Liver size, fatty liver, cirrhosis evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(38, 'Gallbladder Ultrasound', NULL, 2, '1500', NULL, '15', 'Gallbladder stones and inflammation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(39, 'Spleen Ultrasound', NULL, 2, '1500', NULL, '15', 'Spleen size and abnormalities', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(40, 'Neck Ultrasound (Soft Tissue)', NULL, 2, '2000', NULL, '20', 'Neck lumps, lymph nodes, and masses', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(41, 'Chest Ultrasound (Thoracic)', NULL, 2, '2200', NULL, '25', 'Pleural effusion and lung surface evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(42, 'Musculoskeletal Ultrasound (Joint)', NULL, 2, '2500', NULL, '25', 'Soft tissue, tendons, ligaments ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(43, 'Pediatric Hip Ultrasound', NULL, 2, '2000', NULL, '20', 'Infant hip joint development screening', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(44, 'Appendix Ultrasound', NULL, 2, '2200', NULL, '20', 'Appendicitis diagnosis ultrasound', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(45, 'CT Brain (Plain)', NULL, 3, '6000', NULL, '20', 'Non-contrast CT scan of brain for stroke, bleeding, tumor', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(46, 'CT Brain (With Contrast)', NULL, 3, '8500', NULL, '30', 'Contrast-enhanced CT brain for detailed evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(47, 'CT Chest (Plain)', NULL, 3, '7000', NULL, '25', 'Lung, heart, and chest structures CT scan', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(48, 'CT Chest (High Resolution)', NULL, 3, '9000', NULL, '30', 'HRCT for interstitial lung disease, pneumonia', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(49, 'CT Chest + Abdomen + Pelvis', NULL, 3, '15000', NULL, '45', 'Complete torso CT scan for cancer staging', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(50, 'CT Abdomen (Plain)', NULL, 3, '7000', NULL, '25', 'Abdominal organs CT without contrast', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(51, 'CT Abdomen + Pelvis (With Contrast)', NULL, 3, '10000', NULL, '35', 'Contrast CT for detailed organ evaluation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(52, 'CT KUB (Kidney, Ureter, Bladder)', NULL, 3, '6500', NULL, '20', 'CT urography for kidney stones detection', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(53, 'CT Spine (Cervical)', NULL, 3, '7000', NULL, '25', 'Neck spine CT for fracture or disc herniation', 2, 3, '2025-12-26 12:33:11', '2025-12-26 12:33:11'),
(54, 'CT Spine (Lumbar)', NULL, 3, '7000', NULL, '25', 'Lower back spine CT scan', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(55, 'CT Paranasal Sinuses (PNS)', NULL, 3, '6000', NULL, '20', 'Sinus CT for chronic sinusitis or polyps', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(56, 'CT Angiography (Brain)', NULL, 3, '12000', NULL, '35', 'Brain blood vessels CT for aneurysm', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(57, 'CT Angiography (Coronary)', NULL, 3, '18000', NULL, '40', 'Heart arteries CT for blockage detection', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(58, 'CT Angiography (Pulmonary)', NULL, 3, '12000', NULL, '30', 'Lung blood vessels CT for embolism', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(59, 'CT Pelvis (Plain)', NULL, 3, '6500', NULL, '20', 'Pelvic bones and organs CT scan', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(60, 'CT Neck (With Contrast)', NULL, 3, '8000', NULL, '25', 'Neck soft tissue and thyroid CT', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(61, 'CT Temporal Bone', NULL, 3, '7500', NULL, '25', 'Ear bones CT for hearing problems', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(62, 'CT Facial Bones', NULL, 3, '6500', NULL, '20', 'Facial fracture or sinus CT', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(63, 'CT Whole Abdomen (Triple Phase)', NULL, 3, '12000', NULL, '40', 'Multi-phase CT for liver/kidney lesions', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(64, 'CT Chest (With Contrast)', NULL, 3, '9500', NULL, '30', 'Contrast-enhanced chest CT for masses', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(65, 'MRI Brain (Plain)', NULL, 4, '12000', NULL, '45', 'Non-contrast brain MRI for detailed soft tissue imaging', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(66, 'MRI Brain (With Contrast)', NULL, 4, '16000', NULL, '60', 'Contrast-enhanced brain MRI for tumor/MS evaluation', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(67, 'MRI Spine (Cervical)', NULL, 4, '13000', NULL, '45', 'Neck spine MRI for disc herniation, cord compression', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(68, 'MRI Spine (Thoracic)', NULL, 4, '13000', NULL, '45', 'Mid-back spine MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(69, 'MRI Spine (Lumbar)', NULL, 4, '13000', NULL, '45', 'Lower back MRI for sciatica, disc problems', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(70, 'MRI Whole Spine (Cervical + Thoracic + Lumbar)', NULL, 4, '28000', NULL, '90', 'Complete spinal cord MRI evaluation', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(71, 'MRI Knee Joint', NULL, 4, '11000', NULL, '40', 'Knee ligaments, cartilage, meniscus MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(72, 'MRI Shoulder Joint', NULL, 4, '11000', NULL, '40', 'Rotator cuff, shoulder tendons MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(73, 'MRI Hip Joint (Bilateral)', NULL, 4, '14000', NULL, '50', 'Both hip joints MRI for AVN, arthritis', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(74, 'MRI Ankle Joint', NULL, 4, '10000', NULL, '35', 'Ankle ligaments and bones MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(75, 'MRI Wrist Joint', NULL, 4, '10000', NULL, '35', 'Wrist bones, tendons, carpal tunnel MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(76, 'MRI Elbow Joint', NULL, 4, '10000', NULL, '35', 'Elbow bones and ligaments MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(77, 'MRI Abdomen (Plain)', NULL, 4, '14000', NULL, '50', 'Abdominal organs MRI without contrast', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(78, 'MRI Abdomen (With Contrast)', NULL, 4, '18000', NULL, '60', 'Liver, pancreas, kidneys detailed MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(79, 'MRI Pelvis (Female)', NULL, 4, '13000', NULL, '45', 'Uterus, ovaries MRI for fibroids, endometriosis', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(80, 'MRI Pelvis (Male)', NULL, 4, '13000', NULL, '45', 'Prostate and pelvic organs MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(81, 'MRCP (MR Cholangiopancreatography)', NULL, 4, '15000', NULL, '50', 'Bile ducts and pancreatic ducts MRI for stones', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(82, 'MR Angiography (Brain)', NULL, 4, '16000', NULL, '55', 'Brain blood vessels MRI for aneurysm', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(83, 'MR Angiography (Neck)', NULL, 4, '15000', NULL, '50', 'Carotid arteries MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(84, 'MRI Breast (Bilateral)', NULL, 4, '18000', NULL, '60', 'Breast cancer screening and evaluation MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(85, 'MRI Cardiac (Heart)', NULL, 4, '20000', NULL, '60', 'Heart structure and function MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(86, 'MRI Foot (Complete)', NULL, 4, '10000', NULL, '35', 'Foot bones, tendons, plantar fasciitis MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(87, 'MRI Orbits (Eye)', NULL, 4, '12000', NULL, '40', 'Eye socket and optic nerve MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(88, 'MRI Temporal Bones (Ear)', NULL, 4, '12000', NULL, '40', 'Inner ear and acoustic nerve MRI', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12'),
(89, 'MRI Whole Body Screening', NULL, 4, '45000', NULL, '120', 'Complete body MRI for cancer screening', 2, 3, '2025-12-26 12:33:12', '2025-12-26 12:33:12');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business` int NOT NULL DEFAULT '0',
  `created_by` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `business`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'currency', 'PKR', 2, 3, '2025-12-26 13:04:02', '2025-12-26 13:04:02'),
(2, 'currency_symbol', 'PKR', 2, 3, '2025-12-26 13:04:02', '2025-12-26 13:04:02'),
(3, 'title_text', 'Amad Diagnostic Centre', 2, 3, NULL, NULL),
(4, 'footer_text', 'Copyright © 2025 All rights reserved. Powered by PolytronX - Business Digitalized', 2, 3, NULL, NULL),
(5, 'color', 'theme-3', 2, 3, NULL, NULL),
(6, 'color_flag', 'false', 2, 3, NULL, NULL),
(7, 'site_rtl', 'off', 2, 3, NULL, NULL),
(8, 'site_transparent', 'off', 2, 3, NULL, NULL),
(9, 'cust_darklayout', 'off', 2, 3, NULL, NULL),
(10, 'defult_timezone', 'Asia/Karachi', 2, 3, NULL, NULL),
(11, 'defult_language', 'en', 2, 3, NULL, NULL),
(12, 'site_date_format', 'd-m-Y', 2, 3, NULL, NULL),
(13, 'site_time_format', 'g:i A', 2, 3, NULL, NULL),
(14, 'appointment_prefix', '#ADC00000', 2, 3, NULL, NULL),
(15, 'week_start_day', '1', 2, 3, NULL, NULL),
(16, 'booking_mode', '1,2', 2, 3, NULL, NULL),
(17, 'default_status', 'Pending', 2, 3, NULL, NULL),
(18, 'bank_transfer_payment_is_on', 'on', 2, 3, NULL, NULL),
(19, 'bank_number', 'Amad Diagnostic Centre\r\nAllied Bank Limited\r\nIBAN # PK77ABPL098493893295', 2, 3, NULL, NULL),
(20, 'maximum_slot', '150', 2, 3, NULL, NULL),
(21, 'custom_field_enable', 'on', 2, 3, NULL, NULL),
(22, 'title_text', 'Amad Diagnostic Centre - Gujranwala', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(23, 'footer_text', 'Copyright © 2025 All rights reserved. Powered by PolytronX - Business Digitalized', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(24, 'color', 'theme-3', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(25, 'color_flag', 'false', 0, 1, '2025-12-27 01:23:53', '2025-12-27 14:59:04'),
(26, 'landing_page', 'off', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(27, 'site_rtl', 'off', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(28, 'signup', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(29, 'email_verification', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(30, 'site_transparent', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(31, 'cust_darklayout', 'off', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:59:04'),
(32, 'storage_setting', 'local', 0, 1, '2025-12-27 14:55:05', '2025-12-27 15:00:15'),
(33, 'local_storage_validation', 'gif,jpeg,jpg,pdf,png,svg', 0, 1, '2025-12-27 14:55:05', '2025-12-27 15:00:15'),
(34, 'local_storage_max_upload_size', '2025', 0, 1, '2025-12-27 14:55:05', '2025-12-27 15:00:15'),
(35, 's3_key', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:15'),
(36, 's3_secret', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:15'),
(37, 's3_region', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:15'),
(38, 's3_bucket', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(39, 's3_url', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(40, 's3_endpoint', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(41, 's3_max_upload_size', '2024', 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(42, 'wasabi_key', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(43, 'wasabi_secret', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(44, 'wasabi_region', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(45, 'wasabi_bucket', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(46, 'wasabi_url', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(47, 'wasabi_root', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(48, 'wasabi_max_upload_size', '2024', 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(49, 's3_storage_validation', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(50, 'wasabi_storage_validation', NULL, 0, 1, '2025-12-27 01:30:40', '2025-12-27 15:00:16'),
(51, 'meta_title', 'Amad Diagnostic Centre - Gujranwala', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:58:47'),
(52, 'meta_keywords', 'Amad Diagnostic Centre - Gujranwala', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:58:47'),
(53, 'meta_description', 'Amad Diagnostic Centre - Gujranwala', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:58:47'),
(54, 'meta_image', 'uploads/meta/adc-logo (1)_1766817131.png', 0, 1, '2025-12-27 01:32:11', '2025-12-27 01:32:11'),
(55, 'defult_timezone', 'Asia/Karachi', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:58:01'),
(56, 'defult_language', 'en', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:58:01'),
(57, 'site_date_format', 'd-m-Y', 0, 1, '2025-12-27 01:34:18', '2025-12-27 14:58:01'),
(58, 'site_time_format', 'g:i A', 0, 1, '2025-12-27 01:34:18', '2025-12-27 14:58:01'),
(59, 'favicon', 'uploads/logo/favicon_1766817451.png', 0, 1, '2025-12-27 01:37:32', '2025-12-27 14:59:04'),
(60, 'logo_dark', 'uploads/logo/logo_dark_1766817467.png', 0, 1, '2025-12-27 01:37:48', '2025-12-27 14:59:04'),
(61, 'logo_light', 'uploads/logo/logo_light_1766817489.png', 0, 1, '2025-12-27 01:38:09', '2025-12-27 14:59:04'),
(62, 'plan_package', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(63, 'currency_format', '1', 10, 3, NULL, NULL),
(64, 'defult_currancy', 'PKR', 10, 3, NULL, NULL),
(65, 'defult_currancy_symbol', '₨', 10, 3, NULL, NULL),
(66, 'defult_language', 'en', 10, 3, NULL, NULL),
(67, 'defult_timezone', 'Asia/Karachi', 10, 3, NULL, NULL),
(68, 'site_currency_symbol_position', 'pre', 10, 3, NULL, NULL),
(69, 'site_date_format', 'd-m-Y', 10, 3, NULL, NULL),
(70, 'site_time_format', 'g:i A', 10, 3, NULL, NULL),
(71, 'title_text', 'Amad Diagnostic Centre - Gujranwala', 10, 3, NULL, NULL),
(72, 'footer_text', 'Copyright © 2025 All rights reserved. Powered by PolytronX - Business Digitalized', 10, 3, NULL, NULL),
(73, 'site_rtl', 'off', 10, 3, NULL, NULL),
(74, 'cust_darklayout', 'off', 10, 3, NULL, NULL),
(75, 'site_transparent', 'off', 10, 3, NULL, NULL),
(76, 'color', 'theme-1', 10, 3, NULL, NULL),
(77, 'currency_format', '1', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(78, 'defult_currancy', 'PKR', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(79, 'defult_currancy_symbol', '₨', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(80, 'enable_cookie', 'off', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(81, 'necessary_cookies', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(82, 'cookie_logging', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(83, 'cookie_title', 'We use cookies!', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(84, 'cookie_description', 'Hi, this website uses essential cookies to ensure its proper operation and tracking cookies to understand how you interact with it', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(85, 'strictly_cookie_title', 'Strictly necessary cookies', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(86, 'strictly_cookie_description', 'These cookies are essential for the proper functioning of my website. Without these cookies, the website would not work properly', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(87, 'more_information_description', 'For any queries in relation to our policy on cookies and your choices, please contact us', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(88, 'contactus_url', '#', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(89, 'custome_package', 'on', 0, 1, '2025-12-27 14:55:05', '2025-12-27 14:55:05'),
(90, 'bank_transfer_payment_is_on', 'on', 0, 1, NULL, NULL),
(91, 'bank_number', 'Amad Diagnostic Centre - Gujranwala\r\nAllied Bank Limited\r\nIBAN#PK76ABPL3642479343', 0, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `location_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `user_id`, `location_id`, `service_id`, `description`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'Dr Mian Waheed Ahmad', 11, '1', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89', 'Dr Mian Waheed Ahmad', 2, 3, '2025-12-27 15:14:33', '2025-12-27 15:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `subscribes`
--

CREATE TABLE `subscribes` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `billable_id` bigint UNSIGNED NOT NULL,
  `billable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paddle_id` int NOT NULL,
  `paddle_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paddle_plan` int NOT NULL,
  `quantity` int NOT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `paused_from` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_items`
--

CREATE TABLE `subscription_items` (
  `id` bigint UNSIGNED NOT NULL,
  `subscription_id` bigint UNSIGNED NOT NULL,
  `product_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `theme_settings`
--

CREATE TABLE `theme_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `theme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `billable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billable_id` bigint UNSIGNED NOT NULL,
  `paddle_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paddle_subscription_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'company',
  `active_status` tinyint(1) NOT NULL DEFAULT '0',
  `active_business` int NOT NULL DEFAULT '0',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'uploads/users-avatar/avatar.png',
  `requested_plan` int NOT NULL DEFAULT '0',
  `dark_mode` tinyint(1) NOT NULL DEFAULT '0',
  `lang` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `messenger_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2180f3',
  `active_plan` int NOT NULL DEFAULT '0',
  `active_module` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plan_expire_date` date DEFAULT NULL,
  `billing_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_user` int NOT NULL DEFAULT '-1',
  `seeder_run` int NOT NULL DEFAULT '0',
  `is_enable_login` int NOT NULL DEFAULT '1',
  `is_disable` int NOT NULL DEFAULT '1',
  `trial_expire_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_trial_done` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `total_business` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '-1',
  `business_id` int NOT NULL DEFAULT '0',
  `created_by` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `mobile_no`, `email_verified_at`, `password`, `remember_token`, `type`, `active_status`, `active_business`, `avatar`, `requested_plan`, `dark_mode`, `lang`, `messenger_color`, `active_plan`, `active_module`, `plan_expire_date`, `billing_type`, `total_user`, `seeder_run`, `is_enable_login`, `is_disable`, `trial_expire_date`, `is_trial_done`, `total_business`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'superadmin@example.com', NULL, '2025-12-19 00:44:01', '$2y$12$MYPb43cE6lI5jyUUn4mVYOZ5a4bUr2MPCHyBiN/SZh89E.kwp2cEG', NULL, 'super admin', 1, 0, 'uploads/users-avatar/Untitled design_1766177129.png', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 0, 0, '2025-12-19 00:44:01', '2025-12-19 15:45:29'),
(3, 'Mian Waheed Ahmad', 'admin@amaddiagnosticcentre.com.pk', NULL, '2025-12-20 00:17:03', '$2y$12$FqW2yOEuOTX0xf4w6kUOEOR8559kTTFVcNsHIjfYkhb/jDG7Mt/F.', NULL, 'company', 0, 2, 'uploads/users-avatar/images_1766854108.jpeg', 0, 0, 'en', '#2180f3', 2, 'Stripe,Paypal,GoogleCaptcha,Photography,CarService', '2026-01-20', NULL, 5, 0, 1, 1, NULL, '0', '5', 2, 1, '2025-12-20 00:15:37', '2025-12-27 11:48:56'),
(4, 'John Barber', 'john@example.com', NULL, NULL, '$2y$12$a2fC1n5UNxAyqnRQv6UwyemvbKFlf8535ycuk/hqarM31dVbjsv3S', NULL, 'staff', 0, 0, 'uploads/users-avatar/avatar.png', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 8, 3, '2025-12-27 04:45:41', '2025-12-27 04:45:41'),
(5, 'John Barber', 'john@example.com', NULL, NULL, '$2y$12$mmchLnWuf10CgGJvX4E3sOI2cbtUT5XXrNjQY3J4FwvVC99WSNCly', NULL, 'staff', 0, 0, 'uploads/users-avatar/avatar.png', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 8, 3, '2025-12-27 04:45:50', '2025-12-27 04:45:50'),
(6, 'John Barber', 'john@example.com', NULL, NULL, '$2y$12$9ZP6Q3JhSEJCCfgji1W8e.eRuUdiFzSVDHMpAI8p/.P1tvqiShZF2', NULL, 'staff', 0, 0, 'uploads/users-avatar/avatar.png', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 8, 3, '2025-12-27 04:45:58', '2025-12-27 04:45:58'),
(7, 'John Barber', 'john@example.com', NULL, NULL, '$2y$12$cbKHnZ9dnnopbCSbRJLTO.gWlgLDthYWumNCSlcy923eTnGfJKcR6', NULL, 'staff', 0, 0, 'uploads/users-avatar/avatar.png', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 9, 3, '2025-12-27 07:38:29', '2025-12-27 07:38:29'),
(11, 'Dr Mian Waheed Ahmad', 'drmianwaheedahmad@gmail.com', NULL, '2025-12-27 03:14:33', NULL, NULL, 'Staff', 0, 0, 'uploads/Staff/images_1766866473.jpeg', 0, 0, 'en', '#2180f3', 0, NULL, NULL, NULL, -1, 0, 1, 1, NULL, '0', '-1', 2, 3, '2025-12-27 15:14:33', '2025-12-27 15:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `user_active_modules`
--

CREATE TABLE `user_active_modules` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_coupons`
--

CREATE TABLE `user_coupons` (
  `id` bigint UNSIGNED NOT NULL,
  `user` int NOT NULL,
  `coupon` int NOT NULL,
  `order` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `add_ons`
--
ALTER TABLE `add_ons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointments_location_id_foreign` (`location_id`),
  ADD KEY `appointments_service_id_foreign` (`service_id`),
  ADD KEY `appointments_staff_id_foreign` (`staff_id`),
  ADD KEY `appointments_business_id_foreign` (`business_id`),
  ADD KEY `appointments_created_by_foreign` (`created_by`);

--
-- Indexes for table `appointment_payments`
--
ALTER TABLE `appointment_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_payments_appointment_id_foreign` (`appointment_id`),
  ADD KEY `appointment_payments_business_id_foreign` (`business_id`),
  ADD KEY `appointment_payments_created_by_foreign` (`created_by`);

--
-- Indexes for table `bank_transfer_payments`
--
ALTER TABLE `bank_transfer_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blogs_business_id_foreign` (`business_id`),
  ADD KEY `blogs_created_by_foreign` (`created_by`);

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `businesses_created_by_foreign` (`created_by`);

--
-- Indexes for table `business_holidays`
--
ALTER TABLE `business_holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_holidays_business_id_foreign` (`business_id`),
  ADD KEY `business_holidays_created_by_foreign` (`created_by`);

--
-- Indexes for table `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_hours_business_id_foreign` (`business_id`),
  ADD KEY `business_hours_created_by_foreign` (`created_by`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_business_id_foreign` (`business_id`),
  ADD KEY `categories_created_by_foreign` (`created_by`);

--
-- Indexes for table `contact_us`
--
ALTER TABLE `contact_us`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_us_business_id_foreign` (`business_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customers_user_id_foreign` (`user_id`),
  ADD KEY `customers_business_id_foreign` (`business_id`),
  ADD KEY `customers_created_by_foreign` (`created_by`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custom_fields_business_id_foreign` (`business_id`),
  ADD KEY `custom_fields_created_by_foreign` (`created_by`);

--
-- Indexes for table `custom_statuses`
--
ALTER TABLE `custom_statuses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custom_statuses_business_id_foreign` (`business_id`),
  ADD KEY `custom_statuses_created_by_foreign` (`created_by`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_template_langs`
--
ALTER TABLE `email_template_langs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `files_business_id_foreign` (`business_id`),
  ADD KEY `files_created_by_foreign` (`created_by`);

--
-- Indexes for table `join_us`
--
ALTER TABLE `join_us`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `join_us_email_unique` (`email`);

--
-- Indexes for table `landingpage_pixels`
--
ALTER TABLE `landingpage_pixels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `landing_page_settings`
--
ALTER TABLE `landing_page_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `landing_page_settings_name_unique` (`name`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `locations_business_id_foreign` (`business_id`),
  ADD KEY `locations_created_by_foreign` (`created_by`);

--
-- Indexes for table `login_details`
--
ALTER TABLE `login_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `login_details_business_foreign` (`business`);

--
-- Indexes for table `marketplace_page_settings`
--
ALTER TABLE `marketplace_page_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_template_langs`
--
ALTER TABLE `notification_template_langs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_order_id_unique` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_unique` (`name`);

--
-- Indexes for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `permission_role_role_id_foreign` (`role_id`);

--
-- Indexes for table `permission_user`
--
ALTER TABLE `permission_user`
  ADD PRIMARY KEY (`user_id`,`permission_id`,`user_type`),
  ADD KEY `permission_user_permission_id_foreign` (`permission_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipts_order_id_unique` (`order_id`),
  ADD UNIQUE KEY `receipts_receipt_url_unique` (`receipt_url`),
  ADD KEY `receipts_billable_id_billable_type_index` (`billable_id`,`billable_type`),
  ADD KEY `receipts_paddle_subscription_id_index` (`paddle_subscription_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`user_id`,`role_id`,`user_type`),
  ADD KEY `role_user_role_id_foreign` (`role_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `services_category_id_foreign` (`category_id`),
  ADD KEY `services_business_id_foreign` (`business_id`),
  ADD KEY `services_created_by_foreign` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `settings_created_by_index` (`created_by`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_user_id_foreign` (`user_id`),
  ADD KEY `staff_business_id_foreign` (`business_id`),
  ADD KEY `staff_created_by_foreign` (`created_by`);

--
-- Indexes for table `subscribes`
--
ALTER TABLE `subscribes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscribes_business_id_foreign` (`business_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscriptions_paddle_id_unique` (`paddle_id`),
  ADD KEY `subscriptions_billable_id_billable_type_index` (`billable_id`,`billable_type`);

--
-- Indexes for table `subscription_items`
--
ALTER TABLE `subscription_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscription_items_subscription_id_price_id_unique` (`subscription_id`,`price_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `testimonials_customer_id_foreign` (`customer_id`),
  ADD KEY `testimonials_business_id_foreign` (`business_id`),
  ADD KEY `testimonials_created_by_foreign` (`created_by`);

--
-- Indexes for table `theme_settings`
--
ALTER TABLE `theme_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `theme_settings_business_id_foreign` (`business_id`),
  ADD KEY `theme_settings_created_by_foreign` (`created_by`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transactions_paddle_id_unique` (`paddle_id`),
  ADD KEY `transactions_billable_type_billable_id_index` (`billable_type`,`billable_id`),
  ADD KEY `transactions_paddle_subscription_id_index` (`paddle_subscription_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_active_modules`
--
ALTER TABLE `user_active_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_active_modules_user_id_index` (`user_id`);

--
-- Indexes for table `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `add_ons`
--
ALTER TABLE `add_ons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `appointment_payments`
--
ALTER TABLE `appointment_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `bank_transfer_payments`
--
ALTER TABLE `bank_transfer_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `business_holidays`
--
ALTER TABLE `business_holidays`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contact_us`
--
ALTER TABLE `contact_us`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_fields`
--
ALTER TABLE `custom_fields`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `custom_statuses`
--
ALTER TABLE `custom_statuses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `email_template_langs`
--
ALTER TABLE `email_template_langs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `join_us`
--
ALTER TABLE `join_us`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `landingpage_pixels`
--
ALTER TABLE `landingpage_pixels`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `landing_page_settings`
--
ALTER TABLE `landing_page_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_details`
--
ALTER TABLE `login_details`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `marketplace_page_settings`
--
ALTER TABLE `marketplace_page_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notification_template_langs`
--
ALTER TABLE `notification_template_langs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `subscribes`
--
ALTER TABLE `subscribes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_items`
--
ALTER TABLE `subscription_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `theme_settings`
--
ALTER TABLE `theme_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_active_modules`
--
ALTER TABLE `user_active_modules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_coupons`
--
ALTER TABLE `user_coupons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_payments`
--
ALTER TABLE `appointment_payments`
  ADD CONSTRAINT `appointment_payments_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_payments_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blogs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `businesses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_holidays`
--
ALTER TABLE `business_holidays`
  ADD CONSTRAINT `business_holidays_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_holidays_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_hours`
--
ALTER TABLE `business_hours`
  ADD CONSTRAINT `business_hours_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_hours_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_us`
--
ALTER TABLE `contact_us`
  ADD CONSTRAINT `contact_us_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD CONSTRAINT `custom_fields_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_fields_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_statuses`
--
ALTER TABLE `custom_statuses`
  ADD CONSTRAINT `custom_statuses_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_statuses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `locations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_details`
--
ALTER TABLE `login_details`
  ADD CONSTRAINT `login_details_business_foreign` FOREIGN KEY (`business`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `permission_user`
--
ALTER TABLE `permission_user`
  ADD CONSTRAINT `permission_user_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role_user`
--
ALTER TABLE `role_user`
  ADD CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `services_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `services_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscribes`
--
ALTER TABLE `subscribes`
  ADD CONSTRAINT `subscribes_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `testimonials_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `testimonials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `testimonials_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `theme_settings`
--
ALTER TABLE `theme_settings`
  ADD CONSTRAINT `theme_settings_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `theme_settings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
