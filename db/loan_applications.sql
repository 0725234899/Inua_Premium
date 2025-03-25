-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2025 at 06:12 PM
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
-- Database: `microfinance`
--

-- --------------------------------------------------------

--
-- Table structure for table `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` int(11) NOT NULL,
  `borrower` int(11) NOT NULL,
  `loan_product` varchar(255) DEFAULT NULL,
  `principal` decimal(10,2) DEFAULT NULL,
  `loan_release_date` date DEFAULT NULL,
  `interest` decimal(10,2) DEFAULT NULL,
  `interest_method` varchar(50) DEFAULT NULL,
  `loan_interest` decimal(5,2) DEFAULT NULL,
  `loan_duration` int(11) DEFAULT NULL,
  `repayment_cycle` varchar(50) DEFAULT NULL,
  `number_of_repayments` int(11) DEFAULT NULL,
  `processing_fee` decimal(5,2) DEFAULT NULL,
  `registration_fee` decimal(5,2) DEFAULT NULL,
  `loan_status` varchar(50) DEFAULT NULL,
  `total_amount` double NOT NULL,
  `total_amount_inclusive` decimal(10,2) NOT NULL,
  `id_photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
