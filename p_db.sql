-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2026 at 11:06 AM
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
-- Database: `p_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `admin_code` varchar(20) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `admin_email` varchar(150) NOT NULL,
  `admin_password` varchar(64) NOT NULL,
  `admin_role` enum('owner','hr_manager','payroll_manager') NOT NULL DEFAULT 'owner',
  `admin_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `admin_code`, `admin_name`, `admin_email`, `admin_password`, `admin_role`, `admin_time`) VALUES
(1, 'A001', 'Owner', 'owner@tiptop.com', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'owner', '2026-05-22 19:48:26'),
(2, 'A002', 'HR Manager', 'hr@tiptop.com', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'hr_manager', '2026-05-22 19:48:26'),
(3, 'A003', 'Payroll Manager', 'payroll@tiptop.com', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'payroll_manager', '2026-05-22 19:48:26');

-- --------------------------------------------------------

--
-- Table structure for table `admin_employee_link`
--

CREATE TABLE `admin_employee_link` (
  `admin_code` varchar(20) NOT NULL,
  `emp_code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_employee_link`
--

INSERT INTO `admin_employee_link` (`admin_code`, `emp_code`) VALUES
('A002', 'EMP001'),
('A003', 'EMP002');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent') NOT NULL DEFAULT 'absent',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `note_in` varchar(255) DEFAULT NULL,
  `note_out` varchar(255) DEFAULT NULL,
  `marked_by` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(64) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `date_of_birth` date NOT NULL,
  `contact_no` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `section` varchar(100) NOT NULL,
  `date_of_joining` date NOT NULL,
  `employment_type` enum('permanent','contract','temporary') NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowance_target` decimal(10,2) DEFAULT 0.00,
  `allowance_attendance` decimal(10,2) DEFAULT 0.00,
  `allowance_meal` decimal(10,2) DEFAULT 0.00,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`emp_id`, `emp_code`, `first_name`, `last_name`, `email`, `password`, `gender`, `date_of_birth`, `contact_no`, `address`, `city`, `designation`, `section`, `date_of_joining`, `employment_type`, `basic_salary`, `allowance_target`, `allowance_attendance`, `allowance_meal`, `bank_name`, `bank_account_no`, `status`, `created_at`) VALUES
(1, 'EMP001', 'Ruwan', 'Wijesinghe', 'hr@tiptop.com', '6e833f5cd78885be5b024232f38bb8fa637c68dc', 'male', '1993-01-04', '0714578963', 'No. 4/5/A, Bokundara, Piliyandala', 'Piliyandala', 'HR Manager', 'HR', '2023-01-04', 'permanent', 80000.00, 0.00, 0.00, 0.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(2, 'EMP002', 'Heshan', 'Fernando', 'payroll@tiptop.com', 'e47f01b2fba632f3fffdd8645f6a0fc563ae09e4', 'male', '1991-01-02', '0714998963', 'No.107, Wijerama, Maharagama', 'Maharagama', 'Payroll Manager', 'Payroll', '2023-01-15', 'permanent', 80000.00, 0.00, 0.00, 0.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(3, 'EMP003', 'Kasun', 'Perera', 'kasun.perera@tiptop.com', '7ab5dd8e4a2c040c4ee94040036bceb2fa3257d2', 'male', '2000-01-15', '0771234001', 'No.67, Main Street, Kandy', 'Kandy', 'Worker', 'Cutting', '2024-01-15', 'permanent', 35000.00, 5000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(4, 'EMP004', 'Nimal', 'Silva', 'nimal.silva@tiptop.com', 'b6037c824fba0e5b652fb94dc152d343627ced27', 'male', '1998-08-22', '0771234002', 'No.24, Main Street, Kandy', 'Kandy', 'Worker', 'Sewing', '2024-02-01', 'permanent', 33000.00, 4000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(5, 'EMP005', 'Kamali', 'Fernando', 'kamali.fernando@tiptop.com', '2bab023d3681f821b561f495cfb76d1b1ea176d3', 'female', '2001-03-10', '0771234003', 'No.99, Main Street, Kandy', 'Kandy', 'Worker', 'Packing', '2024-01-20', 'permanent', 30000.00, 3000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(6, 'EMP006', 'Dilani', 'Jayasena', 'dilani.jayasena@tiptop.com', '3f331ef820161e8127f9be5722fc6a13ec83da39', 'female', '1997-05-18', '0771234004', 'No.77, Main Street, Kandy', 'Kandy', 'Worker', 'Ironing', '2024-03-01', 'permanent', 32000.00, 3500.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(7, 'EMP007', 'Roshan', 'Wickramasinghe', 'roshan.wickrama@tiptop.com', '10ac3b2c3e3af7c2936c5add73122b6d541ea6bc', 'male', '2002-09-25', '0771234005', 'No.58, Main Street, Kandy', 'Kandy', 'Worker', 'Cutting', '2024-02-15', 'temporary', 35000.00, 0.00, 0.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(8, 'EMP008', 'Amali', 'Rathnayake', 'amali.rathnayake@tiptop.com', 'ecbc1790f13866181223049d2f820b32bbe60ddb', 'female', '1999-12-03', '0771234006', 'No.14, Main Street, Kandy', 'Kandy', 'Worker', 'Sewing', '2024-01-10', 'temporary', 33000.00, 0.00, 0.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(9, 'EMP009', 'Sunil', 'Dissanayake', 'sunil.dissanayake@tiptop.com', 'a946e17e0d46ae8c8b5b174a518e3a4c3160259e', 'male', '1996-10-07', '0771234007', 'No.39, Main Street, Kandy', 'Kandy', 'Worker', 'Packing', '2024-04-01', 'permanent', 30000.00, 3000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(10, 'EMP010', 'Thilini', 'Madushani', 'thilini.madushani@tiptop.com', '68f2a423f85eddf98700ec3d7e96b6a595f6c85e', 'female', '2001-07-14', '0771234008', 'No.31, Main Street, Kandy', 'Kandy', 'Worker', 'Ironing', '2024-02-20', 'permanent', 32000.00, 3500.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(11, 'EMP011', 'Pradeep', 'Kumarasinghe', 'pradeep.kumarasinghe@tiptop.com', '5ccce223824b0547cae936f857d4f292c6950249', 'male', '1989-05-11', '0771234009', 'No.67, Main Street, Kandy', 'Kandy', 'Worker', 'Cutting', '2024-03-10', 'permanent', 35000.00, 5000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27'),
(12, 'EMP012', 'Sanduni', 'Gunawardena', 'sanduni.gunawardena@tiptop.com', 'a01e70ac4a7fa69dc2446e2a4a53abef0cc1ab99', 'female', '2002-06-30', '0771234010', 'No.38, Main Street, Kandy', 'Kandy', 'Worker', 'Sewing', '2024-01-25', 'permanent', 33000.00, 4000.00, 3000.00, 2000.00, NULL, NULL, 'active', '2026-05-22 19:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(200) NOT NULL,
  `holiday_desc` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`holiday_id`, `holiday_date`, `holiday_name`, `holiday_desc`, `created_at`) VALUES
(1, '2026-02-04', 'Sri Lanka Independence Day', 'National holiday', '2026-05-22 19:48:27'),
(2, '2026-02-26', 'Maha Sivarathri', 'Hindu religious holiday', '2026-05-22 19:48:27'),
(3, '2026-03-03', 'Medin Full Moon Poya', 'Buddhist poya day', '2026-05-22 19:48:27'),
(4, '2026-04-03', 'Good Friday', 'Christian holiday', '2026-05-22 19:48:27'),
(5, '2026-05-01', 'May Day', 'International Labour Day', '2026-05-22 19:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `leavelimits`
--

CREATE TABLE `leavelimits` (
  `limit_id` int(11) NOT NULL,
  `annual_allowed_leaves` int(11) NOT NULL DEFAULT 24,
  `deduction_per_day` decimal(10,2) NOT NULL DEFAULT 2000.00,
  `manager_annual_leaves` int(11) NOT NULL DEFAULT 24,
  `manager_deduction_per_day` decimal(10,2) NOT NULL DEFAULT 3000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavelimits`
--

INSERT INTO `leavelimits` (`limit_id`, `annual_allowed_leaves`, `deduction_per_day`, `manager_annual_leaves`, `manager_deduction_per_day`) VALUES
(1, 24, 2000.00, 24, 3000.00);

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `leave_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `leave_type` enum('casual','sick','annual','no_pay') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `no_of_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `leave_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_date` timestamp NULL DEFAULT NULL,
  `reviewed_by` varchar(20) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`leave_id`, `emp_code`, `leave_type`, `start_date`, `end_date`, `no_of_days`, `reason`, `leave_status`, `admin_remarks`, `applied_date`, `reviewed_date`, `reviewed_by`, `approved_by`) VALUES
(1, 'EMP007', 'casual', '2026-06-09', '2026-06-11', 2, 'aa', 'approved', NULL, '2026-06-08 19:33:23', '2026-06-08 19:35:24', 'A001', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payheads`
--

CREATE TABLE `payheads` (
  `payhead_id` int(11) NOT NULL,
  `payhead_name` varchar(100) NOT NULL,
  `payhead_type` enum('earning','deduction') NOT NULL,
  `calculation_type` enum('fixed','percentage','hours') NOT NULL,
  `default_value` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payheads`
--

INSERT INTO `payheads` (`payhead_id`, `payhead_name`, `payhead_type`, `calculation_type`, `default_value`, `is_active`, `description`, `created_at`) VALUES
(1, 'Basic Salary', 'earning', 'fixed', 0.00, 1, 'Base monthly salary', '2026-05-22 19:48:27'),
(2, 'OT Payment', 'earning', 'hours', 0.00, 1, 'Overtime payment calculated based on hours', '2026-05-22 19:48:27'),
(3, 'EPF (8%)', 'deduction', 'percentage', 8.00, 1, 'Employee Provident Fund - 8%', '2026-05-22 19:48:27'),
(4, 'ETF (3%)', 'deduction', 'percentage', 3.00, 1, 'Employee Trust Fund - 3%', '2026-05-22 19:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `pay_period_month` int(11) NOT NULL,
  `pay_period_year` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowance_target` decimal(10,2) DEFAULT 0.00,
  `allowance_attendance` decimal(10,2) DEFAULT 0.00,
  `allowance_meal` decimal(10,2) DEFAULT 0.00,
  `ot_payment` decimal(10,2) DEFAULT 0.00,
  `total_earnings` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) DEFAULT 0.00,
  `epf_employee` decimal(10,2) DEFAULT 0.00,
  `epf_employer` decimal(10,2) DEFAULT 0.00,
  `etf_employer` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','processed','paid') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`payroll_id`, `emp_code`, `pay_period_month`, `pay_period_year`, `basic_salary`, `allowance_target`, `allowance_attendance`, `allowance_meal`, `ot_payment`, `total_earnings`, `total_deductions`, `net_salary`, `epf_employee`, `epf_employer`, `etf_employer`, `payment_status`, `payment_date`, `created_at`, `updated_at`) VALUES
(1, 'EMP001', 6, 2026, 80000.00, 0.00, 0.00, 0.00, 0.00, 80000.00, 6400.00, 73600.00, 6400.00, 9600.00, 2400.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(2, 'EMP002', 6, 2026, 80000.00, 0.00, 0.00, 0.00, 0.00, 80000.00, 6400.00, 73600.00, 6400.00, 9600.00, 2400.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(3, 'EMP003', 6, 2026, 35000.00, 5000.00, 3000.00, 2000.00, 0.00, 45000.00, 2800.00, 42200.00, 2800.00, 4200.00, 1050.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(4, 'EMP004', 6, 2026, 33000.00, 4000.00, 3000.00, 2000.00, 0.00, 42000.00, 2640.00, 39360.00, 2640.00, 3960.00, 990.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(5, 'EMP005', 6, 2026, 30000.00, 3000.00, 3000.00, 2000.00, 0.00, 38000.00, 2400.00, 35600.00, 2400.00, 3600.00, 900.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(6, 'EMP006', 6, 2026, 32000.00, 3500.00, 3000.00, 2000.00, 0.00, 40500.00, 2560.00, 37940.00, 2560.00, 3840.00, 960.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(7, 'EMP007', 6, 2026, 35000.00, 0.00, 0.00, 2000.00, 0.00, 37000.00, 0.00, 37000.00, 0.00, 0.00, 0.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(8, 'EMP008', 6, 2026, 33000.00, 0.00, 0.00, 2000.00, 0.00, 35000.00, 0.00, 35000.00, 0.00, 0.00, 0.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(9, 'EMP009', 6, 2026, 30000.00, 3000.00, 3000.00, 2000.00, 0.00, 38000.00, 2400.00, 35600.00, 2400.00, 3600.00, 900.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(10, 'EMP010', 6, 2026, 32000.00, 3500.00, 3000.00, 2000.00, 0.00, 40500.00, 2560.00, 37940.00, 2560.00, 3840.00, 960.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(11, 'EMP011', 6, 2026, 35000.00, 5000.00, 3000.00, 2000.00, 0.00, 45000.00, 2800.00, 42200.00, 2800.00, 4200.00, 1050.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21'),
(12, 'EMP012', 6, 2026, 33000.00, 4000.00, 3000.00, 2000.00, 0.00, 42000.00, 2640.00, 39360.00, 2640.00, 3960.00, 990.00, 'pending', NULL, '2026-06-08 23:29:21', '2026-06-08 23:29:21');

-- --------------------------------------------------------

--
-- Table structure for table `resigned_employees`
--

CREATE TABLE `resigned_employees` (
  `resigned_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `section` varchar(100) NOT NULL,
  `date_of_joining` date NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `contact_no` varchar(15) NOT NULL,
  `email` varchar(150) NOT NULL,
  `resigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `resigned_by` varchar(20) DEFAULT NULL,
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_increments`
--

CREATE TABLE `salary_increments` (
  `increment_id` int(11) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `previous_salary` decimal(10,2) NOT NULL,
  `increment_amount` decimal(10,2) DEFAULT 0.00,
  `increment_percentage` decimal(5,2) DEFAULT 0.00,
  `new_salary` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `processed_by` varchar(20) DEFAULT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `admin_code` (`admin_code`),
  ADD UNIQUE KEY `admin_email` (`admin_email`);

--
-- Indexes for table `admin_employee_link`
--
ALTER TABLE `admin_employee_link`
  ADD PRIMARY KEY (`admin_code`,`emp_code`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_emp_date` (`emp_code`,`attendance_date`),
  ADD KEY `attendance_date` (`attendance_date`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD UNIQUE KEY `emp_code` (`emp_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `leavelimits`
--
ALTER TABLE `leavelimits`
  ADD PRIMARY KEY (`limit_id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `emp_code` (`emp_code`);

--
-- Indexes for table `payheads`
--
ALTER TABLE `payheads`
  ADD PRIMARY KEY (`payhead_id`),
  ADD UNIQUE KEY `payhead_name` (`payhead_name`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`),
  ADD UNIQUE KEY `unique_emp_period` (`emp_code`,`pay_period_month`,`pay_period_year`);

--
-- Indexes for table `resigned_employees`
--
ALTER TABLE `resigned_employees`
  ADD PRIMARY KEY (`resigned_id`);

--
-- Indexes for table `salary_increments`
--
ALTER TABLE `salary_increments`
  ADD PRIMARY KEY (`increment_id`),
  ADD KEY `emp_code` (`emp_code`),
  ADD KEY `effective_date` (`effective_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leavelimits`
--
ALTER TABLE `leavelimits`
  MODIFY `limit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `payhead_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `resigned_employees`
--
ALTER TABLE `resigned_employees`
  MODIFY `resigned_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_increments`
--
ALTER TABLE `salary_increments`
  MODIFY `increment_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
