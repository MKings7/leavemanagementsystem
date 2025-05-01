-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 30, 2025 at 05:23 PM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbportal`
--
CREATE DATABASE IF NOT EXISTS `dbportal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `dbportal`;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`, `Description`, `CreatedAt`) VALUES
(1, 'Human Resources', 'HR department responsible for employee relations', '2025-03-29 11:50:27'),
(2, 'Information Technology', 'IT department responsible for technical support', '2025-03-29 11:50:27'),
(3, 'Finance', 'Finance and accounting department', '2025-03-29 11:50:27'),
(4, 'Marketing', 'Marketing and communications department', '2025-03-29 11:50:27');

-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

DROP TABLE IF EXISTS `email_settings`;
CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL,
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `smtp_from_email` varchar(255) NOT NULL,
  `smtp_from_name` varchar(255) NOT NULL,
  `smtp_encryption` enum('tls','ssl') NOT NULL DEFAULT 'tls',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `email_settings`
--

INSERT INTO `email_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_from_email`, `smtp_from_name`, `smtp_encryption`, `updated_at`) VALUES
(1, 'smtp.gmail.com', 587, 'smuyuka5@gmail.com', 'dcmu izwd knvb jxno', 'smuyuka5@gmail.com', 'Leave Management System', 'tls', '2025-03-29 12:08:38');

-- --------------------------------------------------------

--
-- Table structure for table `hr_users`
--

DROP TABLE IF EXISTS `hr_users`;
CREATE TABLE `hr_users` (
  `HRID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `RequestID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `LeaveTypeID` int(11) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `Reason` text NOT NULL,
  `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `AppliedOn` timestamp NOT NULL DEFAULT current_timestamp(),
  `ProcessedBy` varchar(255) DEFAULT NULL,
  `ProcessedDate` timestamp NULL DEFAULT NULL,
  `RejectionReason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`RequestID`, `UserID`, `LeaveTypeID`, `StartDate`, `EndDate`, `Reason`, `Status`, `AppliedOn`, `ProcessedBy`, `ProcessedDate`, `RejectionReason`) VALUES
(15, 4, 1, '2025-02-28', '2025-03-08', '', 'Approved', '2025-02-18 10:35:53', NULL, NULL, NULL),
(16, 4, 4, '2025-02-28', '2025-06-28', '', 'Approved', '2025-02-18 10:36:24', NULL, NULL, NULL),
(17, 4, 1, '2025-02-22', '2025-04-26', '', 'Rejected', '2025-02-18 10:43:54', NULL, NULL, NULL),
(20, 13, 5, '2025-02-24', '2025-02-26', 'hihi', 'Rejected', '2025-02-22 12:13:17', NULL, NULL, NULL),
(29, 16, 3, '2025-03-04', '2025-03-22', 'Reason for casual leave', 'Approved', '2025-03-01 07:45:21', NULL, NULL, NULL),
(31, 4, 3, '2025-03-25', '2025-04-04', 'feeling tired', 'Rejected', '2025-03-04 08:03:38', NULL, NULL, NULL),
(34, 5, 1, '2025-03-21', '2025-04-25', 'tired', 'Rejected', '2025-03-05 09:40:23', NULL, NULL, NULL),
(36, 4, 6, '2025-03-29', '2025-04-18', 'to further my studies', 'Rejected', '2025-03-06 10:04:06', NULL, NULL, NULL),
(39, 5, 5, '2025-03-28', '2025-04-11', 'in mourning', 'Rejected', '2025-03-15 11:20:24', NULL, NULL, NULL),
(40, 5, 1, '2025-03-20', '2025-03-21', 'Hello', 'Rejected', '2025-03-15 12:00:47', '18', '2025-03-15 12:10:33', NULL),
(41, 5, 3, '2025-03-21', '2025-03-21', 'not now', 'Approved', '2025-03-15 12:01:06', '18', '2025-03-15 12:09:48', NULL),
(42, 4, 5, '2025-04-04', '2025-04-05', 'in mourning mode', 'Approved', '2025-03-15 12:14:10', '18', '2025-03-15 12:14:30', NULL),
(43, 5, 1, '2025-03-28', '2025-04-11', 'Tired', 'Approved', '2025-03-20 09:41:18', '20', '2025-03-22 12:47:51', NULL),
(44, 5, 5, '2025-04-04', '2025-04-18', 'feeling down', 'Approved', '2025-03-20 09:41:47', '18', '2025-03-20 09:45:15', NULL),
(45, 5, 3, '2025-03-28', '2025-04-04', 'tired', 'Approved', '2025-03-20 11:24:24', '20', '2025-03-22 12:47:44', NULL),
(46, 21, 1, '2025-03-24', '2025-03-28', 'leave i want to goooooooooooooooo', 'Approved', '2025-03-24 11:40:56', '20', '2025-03-24 09:42:44', NULL),
(47, 22, 1, '2025-03-29', '2025-04-03', '', 'Approved', '2025-03-29 10:12:40', '20', '2025-03-29 09:05:47', NULL),
(48, 22, 1, '2025-04-01', '2025-04-04', 'i wan to go', 'Approved', '2025-03-29 11:28:23', '20', '2025-03-29 11:17:13', NULL),
(49, 5, 2, '2025-04-04', '2025-04-11', 'so sick', 'Approved', '2025-03-29 13:02:15', '20', '2025-03-29 11:03:28', NULL),
(50, 5, 6, '2025-04-04', '2025-04-11', 'to study', 'Approved', '2025-03-29 13:04:18', '20', '2025-03-29 11:08:06', NULL),
(51, 5, 1, '2025-04-04', '2025-04-08', 'tired', 'Approved', '2025-03-29 13:10:03', '20', '2025-03-29 11:12:23', NULL),
(52, 7, 8, '2025-04-04', '2025-04-11', 'im a father', 'Approved', '2025-03-29 15:24:22', '20', '2025-03-29 13:24:56', NULL),
(53, 8, 1, '2025-03-31', '2025-04-11', 'very tired', 'Approved', '2025-03-30 07:52:35', '20', '2025-03-30 06:57:32', NULL),
(54, 10, 4, '2025-04-18', '2025-06-20', 'very tired', 'Approved', '2025-03-30 07:54:20', '20', '2025-03-30 06:57:28', NULL),
(55, 6, 2, '2025-04-04', '2025-04-11', 'very sick', 'Approved', '2025-03-30 07:56:33', '20', '2025-03-30 06:57:24', NULL),
(56, 6, 3, '2025-04-04', '2025-04-08', 'tired', 'Approved', '2025-03-30 08:17:41', '20', '2025-03-30 07:59:09', NULL),
(57, 5, 8, '2025-05-02', '2025-05-09', 'just need a rest', 'Approved', '2025-04-03 11:43:10', '20', '2025-04-03 10:44:42', NULL),
(58, 1, 1, '2025-04-30', '2025-05-01', 'hi \\r\\nI would like to go for my leave', 'Pending', '2025-04-30 15:19:35', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_substitutes`
--

DROP TABLE IF EXISTS `leave_substitutes`;
CREATE TABLE `leave_substitutes` (
  `ID` int(11) NOT NULL,
  `RequestID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `SubstituteID` int(11) NOT NULL,
  `Status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `NotificationSent` tinyint(1) DEFAULT 0,
  `DateAssigned` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leave_substitutes`
--

INSERT INTO `leave_substitutes` (`ID`, `RequestID`, `UserID`, `SubstituteID`, `Status`, `NotificationSent`, `DateAssigned`) VALUES
(1, 51, 5, 9, 'Accepted', 0, '2025-03-29 13:10:03'),
(2, 52, 7, 17, 'Pending', 0, '2025-03-29 15:24:22'),
(3, 53, 8, 21, 'Pending', 0, '2025-03-30 07:52:35'),
(4, 54, 10, 6, 'Accepted', 0, '2025-03-30 07:54:20'),
(5, 55, 6, 13, 'Accepted', 0, '2025-03-30 07:56:33'),
(6, 56, 6, 16, 'Pending', 0, '2025-03-30 08:17:41'),
(7, 57, 5, 13, 'Pending', 0, '2025-04-03 11:43:10'),
(8, 58, 1, 6, 'Pending', 0, '2025-04-30 15:19:35');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE `leave_types` (
  `LeaveTypeID` int(11) NOT NULL,
  `LeaveName` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `MaxDays` int(11) NOT NULL,
  `Status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`LeaveTypeID`, `LeaveName`, `Description`, `MaxDays`, `Status`) VALUES
(1, 'Annual Leave', 'Paid time off work', 21, 1),
(2, 'Sick Leave', 'Time off due to illness', 10, 1),
(3, 'Casual Leave', 'Short-term personal leave', 5, 1),
(4, 'Maternity', 'Parental leave', 90, 1),
(5, 'Compasionate Leave', 'being bereaved', 5, 1),
(6, 'Study Leave', 'to go and study', 365, 1),
(8, 'Paternity', 'parental leave', 14, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Type` varchar(50) NOT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `Message`, `Type`, `IsRead`, `CreatedAt`) VALUES
(1, 9, 'BRIAN ALUDA has requested you to be their substitute from 04-Apr-2025 to 08-Apr-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-29 13:10:03'),
(2, 17, 'STEPHEN MULEHI  has requested you to be their substitute from 04-Apr-2025 to 11-Apr-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-29 15:24:22'),
(3, 21, 'SILAS LIDWAJI has requested you to be their substitute from 31-Mar-2025 to 11-Apr-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-30 07:52:35'),
(4, 6, 'SUSAN IMBIAKHA has requested you to be their substitute from 18-Apr-2025 to 20-Jun-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-30 07:54:20'),
(5, 13, 'BEATRICE SHIMULI has requested you to be their substitute from 04-Apr-2025 to 11-Apr-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-30 07:56:33'),
(6, 16, 'BEATRICE SHIMULI has requested you to be their substitute from 04-Apr-2025 to 08-Apr-2025. Please respond to this request.', 'substitute_request', 0, '2025-03-30 08:17:41'),
(7, 13, 'BRIAN ALUDA has requested you to be their substitute from 02-May-2025 to 09-May-2025. Please respond to this request.', 'substitute_request', 0, '2025-04-03 11:43:10'),
(8, 6, 'Martin Munyua has requested you to be their substitute from 30-Apr-2025 to 01-May-2025. Please respond to this request.', 'substitute_request', 0, '2025-04-30 15:19:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Fullnames` varchar(100) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` char(32) NOT NULL,
  `EmailAddress` varchar(100) NOT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `admin` smallint(6) NOT NULL,
  `RegistrationDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `hr` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Fullnames`, `Username`, `Password`, `EmailAddress`, `DepartmentID`, `admin`, `RegistrationDate`, `hr`) VALUES
(1, 'Martin Munyua', 'martin123', 'e6852d34c51dd74942eddf71f04142f7', 'comp.cslab@gmail.com', NULL, 0, '2025-02-15 12:18:34', 0),
(2, 'admin', 'admin', '827ccb0eea8a706c4c34a16891f84e7b', 'admin@gmail.com', NULL, 0, '2025-02-15 12:58:02', 0),
(3, 'Martin Munyuaaa', 'munyua1234', '284e5b3954f7d288844a958d2b5160b9', 'munyua@gmail.com', NULL, 0, '2025-02-15 13:49:45', 0),
(4, 'SHARON MUYUKA', 'smuyuka', '81dc9bdb52d04dc20036dbd8313ed055', 'smuyuka@gmail.com', NULL, 1, '2025-02-16 07:34:38', 0),
(5, 'BRIAN ALUDA', 'baluda', '6074c6aa3488f3c2dddff2a7ca821aab', 'baluda@gmail.com', NULL, 0, '2025-02-16 07:38:51', 0),
(6, 'BEATRICE SHIMULI', 'bshimuli', 'e9510081ac30ffa83f10b68cde1cac07', 'bshimuli@gmail.com', NULL, 0, '2025-02-16 07:40:53', 0),
(7, 'STEPHEN MULEHI ', 'smulehi', 'd79c8788088c2193f0244d8f1f36d2db', 'smulehi@gmail.com', NULL, 0, '2025-02-16 07:42:11', 0),
(8, 'SILAS LIDWAJI', 'slidwaji', '4a7d1ed414474e4033ac29ccb8653d9b', 'slidwaji@gmail.com', NULL, 0, '2025-02-16 07:44:39', 0),
(9, 'JULIET VUGUTSA', 'jvugutsa', 'b59c67bf196a4758191e42f76670ceba', 'jvugutsa@gmail.com', NULL, 0, '2025-02-16 07:46:20', 0),
(10, 'SUSAN IMBIAKHA', 'simbiakha', 'dbc4d84bfcfe2284ba11beffb853a8c4', 'simbiakha@gmail.com', NULL, 0, '2025-02-16 08:38:36', 0),
(12, 'wilson k', 'wilson', 'e10adc3949ba59abbe56e057f20f883e', 'winniesunda@gmail.com', NULL, 0, '2025-02-17 06:56:01', 0),
(13, 'Eunice Njeri', 'enjeri', '934b535800b1cba8f96a5d72f72f1611', 'enjer@gmail.com', NULL, 0, '2025-02-22 11:34:55', 0),
(15, 'Martin Munyua', 'mm@gmail.com', '24d508e69378d156ddbf137fced0dbb4', 'm1a2r3t4i5n6', NULL, 0, '2025-03-01 07:20:05', 0),
(16, 'Eric Muyuka', 'eric', '24d508e69378d156ddbf137fced0dbb4', 'eric@gmail.com', NULL, 0, '2025-03-01 07:44:37', 0),
(17, 'Duncan Gwat', 'duncan', 'e807f1fcf82d132f9bb018ca6738a19f', '12346', NULL, 0, '2025-03-08 09:24:47', 0),
(20, 'HR Administrator', 'hradmin', '0192023a7bbd73250516f069df18b500', 'hr@example.com', NULL, 0, '2025-03-22 14:19:31', 1),
(21, 'Martin ', 'Martin', '0192023a7bbd73250516f069df18b500', 'martinkimits@gmail.com', NULL, 0, '2025-03-24 11:38:40', 0),
(22, 'Martin', 'Kimiti', '6cce2dd8b87f3d7128af16e174df4fc6', 'kimiti@gmail.com', NULL, 0, '2025-03-29 10:06:49', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hr_users`
--
ALTER TABLE `hr_users`
  ADD PRIMARY KEY (`HRID`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `leave_requests_ibfk_1` (`UserID`),
  ADD KEY `leave_requests_ibfk_2` (`LeaveTypeID`);

--
-- Indexes for table `leave_substitutes`
--
ALTER TABLE `leave_substitutes`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `RequestID` (`RequestID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `SubstituteID` (`SubstituteID`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`LeaveTypeID`),
  ADD UNIQUE KEY `LeaveName` (`LeaveName`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `EmailAddress` (`EmailAddress`),
  ADD KEY `fk_user_department` (`DepartmentID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hr_users`
--
ALTER TABLE `hr_users`
  MODIFY `HRID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `RequestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `leave_substitutes`
--
ALTER TABLE `leave_substitutes`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `LeaveTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`LeaveTypeID`) REFERENCES `leave_types` (`LeaveTypeID`);

--
-- Constraints for table `leave_substitutes`
--
ALTER TABLE `leave_substitutes`
  ADD CONSTRAINT `leave_substitutes_ibfk_1` FOREIGN KEY (`RequestID`) REFERENCES `leave_requests` (`RequestID`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_substitutes_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `leave_substitutes_ibfk_3` FOREIGN KEY (`SubstituteID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
