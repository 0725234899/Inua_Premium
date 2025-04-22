-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2025 at 06:33 PM
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
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `area_id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`area_id`, `area_name`, `created_at`) VALUES
(1, 'Kericho Town', '2024-07-15 19:05:46'),
(2, 'Nairobi', '2024-07-15 19:05:46'),
(3, 'Mombasa', '2024-07-15 19:05:46'),
(4, 'Eldoret', '2024-07-15 19:05:46'),
(5, 'Kisumu', '2024-07-15 19:05:46');

-- --------------------------------------------------------

--
-- Table structure for table `borrowers`
--

CREATE TABLE `borrowers` (
  `id` int(11) NOT NULL,
  `id_upload` varchar(200) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `passport_photo` varchar(200) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `unique_number` varchar(20) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT NULL,
  `open_loans_balance` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `loan_officer` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowers`
--

INSERT INTO `borrowers` (`id`, `id_upload`, `full_name`, `passport_photo`, `business_name`, `unique_number`, `mobile`, `email`, `total_paid`, `open_loans_balance`, `status`, `loan_officer`) VALUES
(12, '../uploads/1743873412_capture.png', 'EVANDAS KOMILO(ALEX)', '../uploads/1743873412_capture.png', 'EVANDAS KOMILO(ALEX)', '12345', '0710157246', 'evandas@gmail.com', 0.00, 0.00, 'self employed', 'sheba@gmail.com'),
(13, '../uploads/1743873686_capture.png', 'HILLARY RONO', '../uploads/1743873686_capture.png', 'HILLARY RONO', '12345', '0724654965', 'hillary@gmail.com', 0.00, 0.00, 'employed', 'sheba@gmail.com'),
(14, '../uploads/1743873907_capture.png', 'RAYMOND KIPTOO', '../uploads/1743873907_capture.png', 'RAYMOND KIPTOO', '12345', '0714295860', 'ray@gmail.com', 0.00, 0.00, 'self employed', 'sheba@gmail.com'),
(15, '../uploads/1743873987_capture.png', 'KIBET SIMOTWO', '../uploads/1743873987_capture.png', 'KIBET SIMOTWO', '12345', '0722541722', 'k@gmail.com', 0.00, 0.00, 'self employed', 'sheba@gmail.com'),
(16, '../uploads/1743874071_capture.png', 'KIPLANGAT ERICK CHELULE', '../uploads/1743874071_capture.png', 'KIPLANGAT ERICK CHELULE', '12345', '0708885100', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'sheba@gmail.com'),
(18, '../uploads/1743876997_user passport.png', 'JEDIDA CHEBET', '../uploads/1743876997_user passport.png', 'JEDIDA CHEBET', '12345', '0724834296', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(19, '../uploads/1743877251_user passport.png', 'SELYNE CHELANGAT CHUMEK', '../uploads/1743877251_user passport.png', 'SELYNE CHELANGAT CHUMEK', '12345', '0728667510', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(20, '../uploads/1743877315_user passport.png', 'CHARLES KIBET KIRUI', '../uploads/1743877315_user passport.png', 'CHARLES KIBET KIRUI', '12345', '0713117742', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(21, '../uploads/1743877459_user passport.png', 'SHARON CHEPNGENO', '../uploads/1743877459_user passport.png', 'SHARON CHEPNGENO', '12345', '0720956517', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(22, '../uploads/1743877503_user passport.png', 'EDWIN KIPROTICH LANGAT', '../uploads/1743877503_user passport.png', 'EDWIN KIPROTICH LANGAT', '12345', '0721879137', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(23, '../uploads/1743877602_user passport.png', 'EMMANUEL KIRUI', '../uploads/1743877602_user passport.png', 'EMMANUEL KIRUI', '12345', '0720063266', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(24, '../uploads/1743877681_user passport.png', 'KIPLANGAT ROTICH', '../uploads/1743877681_user passport.png', 'KIPLANGAT ROTICH', '12345', '0724099208', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(25, '../uploads/1743877714_user passport.png', 'EDWARD YEGON', '../uploads/1743877714_user passport.png', 'EDWARD YEGON', '12345', '0710842218', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(26, '../uploads/1743877745_user passport.png', 'FREDRICK SANG OMONDI', '../uploads/1743877745_user passport.png', 'FREDRICK SANG OMONDI', '12345', '0711629988', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(27, '../uploads/1743877798_user passport.png', 'MARGARET CHEPWOGEN ONDUKO', '../uploads/1743877798_user passport.png', 'MARGARET CHEPWOGEN ONDUKO', '12345', '0769339936     ', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(28, '../uploads/1743877837_user passport.png', 'MATAYO MUTAI KIPRONO', '../uploads/1743877837_user passport.png', 'MATAYO MUTAI KIPRONO', '12345', '0799374406', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(29, '../uploads/1743877873_user passport.png', 'NICHOLAS KIPNGETICH LIMO', '../uploads/1743877873_user passport.png', 'NICHOLAS KIPNGETICH LIMO', '123435', '0726531962', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(30, '../uploads/1743877913_user passport.png', 'ESTHER CHEMELI TOO', '../uploads/1743877913_user passport.png', 'ESTHER CHEMELI TOO', '12345', '0723794409', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(31, '../uploads/1743877946_user passport.png', 'HELLEN CHEPKWONY', '../uploads/1743877946_user passport.png', 'HELLEN CHEPKWONY', '12345', '724619798', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(32, '../uploads/1743878003_user passport.png', 'JOSEPH CHEPKWONY', '../uploads/1743878003_user passport.png', 'JOSEPH CHEPKWONY', '112345', '0723991085', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(33, '../uploads/1743878042_user passport.png', 'KIPNGENO GEORGE', '../uploads/1743878042_user passport.png', 'KIPNGENO GEORGE', '12345', '0721639051', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(34, '../uploads/1743878127_user passport.png', 'ANNA CHELIMO SAMOEI', '../uploads/1743878127_user passport.png', 'ANNA CHELIMO SAMOEI', '12345', '0798693940', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(35, '../uploads/1743878174_user passport.png', 'BENSON NYAKWALA', '../uploads/1743878174_user passport.png', 'BENSON NYAKWALA', '12345', '0711871849', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(36, '../uploads/1743878207_user passport.png', 'EUNICE ADOYO ATANGA', '../uploads/1743878207_user passport.png', 'EUNICE ADOYO ATANGA', '1212434', '0713829705', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(37, '../uploads/1743878244_user passport.png', 'PETER KERONGO GENI', '../uploads/1743878244_user passport.png', 'PETER KERONGO GENI', '123435', '0716090197', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(38, '../uploads/1743878277_user passport.png', 'ALFRED KIRUI', '../uploads/1743878277_user passport.png', 'ALFRED KIRUI', '12345', '0799297323', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(39, '../uploads/1743878309_user passport.png', 'GLADYS CHERONO KAIYONI', '../uploads/1743878309_user passport.png', 'GLADYS CHERONO KAIYONI', '12345', '0757432315', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(40, '../uploads/1743878353_user passport.png', 'MILGA CHEPKIRUI', '../uploads/1743878353_user passport.png', 'MILGA CHEPKIRUI', '12345', '0729642315', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(41, '../uploads/1743878387_user passport.png', 'PENINAH KAMAU', '../uploads/1743878387_user passport.png', 'PENINAH KAMAU', '12345', '0725226161', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(42, '../uploads/1743878432_user passport.png', 'FAITH CHEPKEMOI', '../uploads/1743878432_user passport.png', 'FAITH CHEPKEMOI', '12345', '0723140473', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(43, '../uploads/1743878472_user passport.png', 'HELLEN ROTICH', '../uploads/1743878472_user passport.png', 'HELLEN ROTICH', '123435', '0728862431', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(44, '../uploads/1743878509_user passport.png', 'LAMECH OSINDI', '../uploads/1743878509_user passport.png', 'LAMECH OSINDI', '12345', '0797626003', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(45, '../uploads/1743878553_user passport.png', 'PHYLIS YATOR', '../uploads/1743878553_user passport.png', 'PHYLIS YATOR', '12345', '0711381311', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(46, '../uploads/1743878588_user passport.png', 'PHILIP TIROP', '../uploads/1743878588_user passport.png', 'PHILIP TIROP', '123435', '0725881533', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(47, '../uploads/1743878629_user passport.png', 'ERIC KIPNGENO', '../uploads/1743878629_user passport.png', 'ERIC KIPNGENO', '123435', '0797839910', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(48, '../uploads/1743878683_user passport.png', 'KIRUI ENOCK', '../uploads/1743878683_user passport.png', 'KIRUI ENOCK', '123435', '0715160016', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(49, '../uploads/1743878738_user passport.png', 'DANIEL KIPROTICH KIMETO', '../uploads/1743878738_user passport.png', 'DANIEL KIPROTICH KIMETO', '123435', '0715575080', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(50, '../uploads/1743886898_user passport.png', 'RONALD ISINGA', '../uploads/1743886898_user passport.png', 'RONALD ISINGA', '12345', '0710745058', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'chepkiruiwinny@gmail.com'),
(51, '../uploads/1743930595_user passport.png', 'ABEL NYAKUNDI', '../uploads/1743930595_user passport.png', 'ABEL NYAKUNDI', '12345', '0706507494', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(52, '../uploads/1743930902_Lucy Ruguru Mwangi.jpg', 'LUCY MWANGI', '../uploads/1743930902_Lucy Ruguru Mwangi.jpg', 'LUCY MWANGI', '12345', '0726021538', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(53, '../uploads/1743930958_user passport.png', 'KIPKOECH ROTICH(ADAKIM)', '../uploads/1743930958_user passport.png', 'KIPKOECH ROTICH(ADAKIM)', '12345', '0727412395', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(54, '../uploads/1743931037_user passport.png', 'KIBET ELVIS', '../uploads/1743931037_user passport.png', 'top trim kinyozi', '12345', '0714869539', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(55, '../uploads/1743931100_user passport.png', 'EMMANUEL KIPLANGAT KIRUI', '../uploads/1743931100_user passport.png', 'EMMANUEL KIPLANGAT KIRUI', '12345', '0708385573', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(56, '../uploads/1743931183_Jeremiah Kiprotich Bosuben.jpg', 'JEREMIAH KIPROTICH BOSUBEN', '../uploads/1743931183_Jeremiah Kiprotich Bosuben.jpg', 'JEREMIAH KIPROTICH BOSUBEN', '12345', '0708384697', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(57, '../uploads/1743931243_Shindrah Chebet.jpg', 'SHIDRAH CHEBET', '../uploads/1743931243_Shindrah Chebet.jpg', 'SHIDRAH CHEBET', '12345', '0725352167', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(58, '../uploads/1743931295_user passport.png', 'Millicent Matoke', '../uploads/1743931295_user passport.png', 'ceemj', '12345', '0792254000', 'ceemj@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(59, '../uploads/1743931342_user passport.png', 'BRENDA MUENI', '../uploads/1743931342_user passport.png', 'brenda shop', '12345', '0725440128', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(60, '../uploads/1743931433_Amos Kipngetich Langat.jpg', 'AMOS KIPNGETICH LANGAT', '../uploads/1743931433_Amos Kipngetich Langat.jpg', 'boda boda rider', '12345', '0715482188', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(61, '../uploads/1743931500_Peter Mbugua.jpg', 'PETER MBUGUA', '../uploads/1743931500_Peter Mbugua.jpg', 'peters kibanda', '12345', '0793954426', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(62, '../uploads/1743931562_user passport.png', 'KIBET WILLY(ADAKIM)', '../uploads/1743931562_user passport.png', 'KIBET WILLY(ADAKIM)', '12345', '0706075386', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(63, '../uploads/1743931595_user passport.png', 'EMILY NDUNGU', '../uploads/1743931595_user passport.png', 'mama soko(mitumba)', '12345', '0726417993', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(64, '../uploads/1743931638_user passport.png', 'CHARLES WAWERU', '../uploads/1743931638_user passport.png', 'waweru garage', '12345', '0721438092', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(65, '../uploads/1743931666_user passport.png', 'EMMANUEL KIPCHUMBA BWAMBOK', '../uploads/1743931666_user passport.png', 'emadan chemist', '12345', '0722647546', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(66, '../uploads/1743931702_user passport.png', 'ALFRED ODWAR', '../uploads/1743931702_user passport.png', 'sbm bank', '12345', '0712533259', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(67, '../uploads/1743931741_user passport.png', 'MARTIN KIPYEGON TANGUS', '../uploads/1743931741_user passport.png', 'equity bank', '123435', '0115112091', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(68, '../uploads/1743931772_user passport.png', 'SAMMY KAPSOOT', '../uploads/1743931772_user passport.png', 'family bank', '12345', '0728961721', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(69, '../uploads/1743931772_user passport.png', 'SAMMY KAPSOOT', '../uploads/1743931772_user passport.png', 'family bank', '12345', '0728961721', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(72, '../uploads/1743931831_Ngeno Shadrack Kiprotich.jpg', 'SHADRACK NGENO', '../uploads/1743931831_Ngeno Shadrack Kiprotich.jpg', 'safari shuttle', '12345', '0729718161', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(73, '../uploads/1743931928_user passport.png', 'FRIDA CHELANGAT TALAM', '../uploads/1743931928_user passport.png', 'FRIDA CHELANGAT TALAM', '12345', '0707661640', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(74, '../uploads/1743931983_user passport.png', 'JANE SCOLASTICA WACHERA MWANGI', '../uploads/1743931983_user passport.png', 'JANE SCOLASTICA WACHERA MWANGI', '12345', '0727936246', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(75, '../uploads/1743932045_Peter Kipkoech Chepkwony.jpg', 'PETER KIPKOECH CHEPKWONY', '../uploads/1743932045_Peter Kipkoech Chepkwony.jpg', 'boda boda rider', '12345', '0720520456', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(76, '../uploads/1743932124_user passport.png', 'PAUL SIGEI- ADAKIM', '../uploads/1743932124_user passport.png', 'adakim', '12345', '0708341267', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(78, '../uploads/1743932383_Wesley Kipkorir Kirui.jpg', 'WESLEY KIPKORIR KIRUI', '../uploads/1743932383_Wesley Kipkorir Kirui.jpg', 'Abule shop', '123435', '0722259653', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(79, '../uploads/1743932455_Kiplangat Terer.jpg', 'KIPLANGAT TERER', '../uploads/1743932455_Kiplangat Terer.jpg', 'boda boda rider', '123435', '0791281500', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(80, '../uploads/1743932641_Gloria Cherotich Meli.jpg', 'GLORIA CHEROTICH MELI', '../uploads/1743932641_Gloria Cherotich Meli.jpg', 'palm boutique', '123435', '0702975189', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'cantonio@kabarak.ac.ke'),
(81, '../uploads/1743934601_user passport.png', 'SHARON CHEPNGETICH', '../uploads/1743934601_user passport.png', 'SHARON CHEPNGETICH', '12345', '0748880438', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(82, '../uploads/1743934651_user passport.png', 'EVALINE CHEPWOGEN LANGAT ', '../uploads/1743934651_user passport.png', 'EVALINE CHEPWOGEN LANGAT', '123435', '0725391714', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(83, '../uploads/1743934651_user passport.png', 'EVALINE CHEPWOGEN LANGAT ', '../uploads/1743934651_user passport.png', 'EVALINE CHEPWOGEN LANGAT', '123435', '0725391714', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(84, '../uploads/1743934690_user passport.png', 'KIPKIRUI ISMAS BETT', '../uploads/1743934690_user passport.png', 'KIPKIRUI ISMAS BETT', '12345', '0793995020', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(85, '../uploads/1743934728_user passport.png', 'VIVIAN CHELANGAT BII', '../uploads/1743934728_user passport.png', 'VIVIAN CHELANGAT BII', '12345', '0759381555', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(86, '../uploads/1743934774_user passport.png', 'RICHARD KIPKORIR MUTAI', '../uploads/1743934774_user passport.png', 'RICHARD KIPKORIR MUTAI', '12345', '0705840077', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(87, '../uploads/1743934812_user passport.png', 'CHEPKEMOI JENIFFER ', '../uploads/1743934812_user passport.png', 'CHEPKEMOI JENIFFER ', '123435', '0720231848', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(88, '../uploads/1743934870_user passport.png', 'BRIAN KIPRUTTO KORIR', '../uploads/1743934870_user passport.png', 'BRIAN KIPRUTTO KORIR', '724214735', '0724214735', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(89, '../uploads/1743934921_user passport.png', 'RAYMOND KIPROTICH KOROS', '../uploads/1743934921_user passport.png', 'RAYMOND KIPROTICH KOROS', '717060675', '0717060675', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(90, '../uploads/1743934968_user passport.png', 'JOEL KIRUI', '../uploads/1743934968_user passport.png', 'JOEL KIRUI', '729861910', '0729861910', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(91, '../uploads/1743935009_user passport.png', 'GEOFFREY KIBET TARUS', '../uploads/1743935009_user passport.png', 'GEOFFREY KIBET TARUS', '721516580', '721516580', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(92, '../uploads/1743935061_user passport.png', 'GRACE CHEBOO KORIR', '../uploads/1743935061_user passport.png', 'GRACE CHEBOO KORIR', '708000357', '0708000357', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(93, '../uploads/1743935133_user passport.png', 'CAREN CHEPKEMOI', '../uploads/1743935133_user passport.png', 'CAREN CHEPKEMOI', '707355613', '0707355613', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(94, '../uploads/1743935186_user passport.png', 'MAGDALINE KERUBO', '../uploads/1743935186_user passport.png', 'MAGDALINE KERUBO', '702118332', '0702118332', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(95, '../uploads/1743935235_user passport.png', 'ROSE ONDARI', '../uploads/1743935235_user passport.png', 'ROSE ONDARI', ' 714258298', '0714258298', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(96, '../uploads/1743935269_user passport.png', 'FLORENCE ASEYO', '../uploads/1743935269_user passport.png', 'FLORENCE ASEYO', '701123429', '701123429', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(97, '../uploads/1743935306_user passport.png', 'FAITH CHEROTICH', '../uploads/1743935306_user passport.png', 'FAITH CHEROTICH', '768862079', '0768862079', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(98, '../uploads/1743935464_user passport.png', 'CHEBET BETTY', '../uploads/1743935464_user passport.png', 'CHEBET BETTY', ' 111433704', '0111433704', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(99, '../uploads/1743935526_user passport.png', 'SIMION CHEROROT', '../uploads/1743935526_user passport.png', 'SIMION CHEROROT', '715699538', '0715699538', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(100, '../uploads/1743935578_user passport.png', 'VICTOR CHERUIYOT', '../uploads/1743935578_user passport.png', 'VICTOR CHERUIYOT', '723582583', '0723582583', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(101, '../uploads/1743935627_user passport.png', 'MARY CHEPKIRUI SOY', '../uploads/1743935627_user passport.png', 'MARY CHEPKIRUI SOY', '700919652', '0700919652', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(102, '../uploads/1743935670_user passport.png', 'ISABELLA GOVOGI', '../uploads/1743935670_user passport.png', 'ISABELLA GOVOGI', '769371132', '0769371132', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(103, '../uploads/1743935727_user passport.png', 'CAROLINE KUYIATO KORIATA', '../uploads/1743935727_user passport.png', 'CAROLINE KUYIATO KORIATA', '714138536', '0714138536', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(104, '../uploads/1743935780_user passport.png', 'SOLOMON KIPKOECH', '../uploads/1743935780_user passport.png', 'SOLOMON KIPKOECH', '706470077', '0706470077', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(105, '../uploads/1743935815_user passport.png', 'STEPHEN ONYANGO ODHIAMBO', '../uploads/1743935815_user passport.png', 'STEPHEN ONYANGO ODHIAMBO', '12345', '0702524588', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(106, '../uploads/1743935856_user passport.png', 'HAARON LANGAT', '../uploads/1743935856_user passport.png', 'HAARON LANGAT', '12345', '0722147742', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(107, '../uploads/1743935892_user passport.png', 'CHERUIYOT KIPKOECH', '../uploads/1743935892_user passport.png', 'CHERUIYOT KIPKOECH', '12345', '0720430509', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(108, '../uploads/1743935923_user passport.png', 'STANLEY KORIR KIPKOECH', '../uploads/1743935923_user passport.png', 'STANLEY KORIR KIPKOECH', '12345', '0708888989', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(109, '../uploads/1743935956_user passport.png', 'WALTER OTIENO ONGOGI', '../uploads/1743935956_user passport.png', 'WALTER OTIENO ONGOGI', '707440172', '0707440172', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(110, '../uploads/1743935993_user passport.png', 'COLLINS WAFULA OUMA', '../uploads/1743935993_user passport.png', 'COLLINS WAFULA OUMA', '741833632', '0741833632', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(111, '../uploads/1743936026_user passport.png', 'PHILIP KIPKORIR CHERUIYOT', '../uploads/1743936026_user passport.png', 'PHILIP KIPKORIR CHERUIYOT', '746013624', '0746013624', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(112, '../uploads/1743936062_user passport.png', 'CHELANGAT MARY', '../uploads/1743936062_user passport.png', 'CHELANGAT MARY', '796376795', '796376795', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(113, '../uploads/1743936099_user passport.png', 'SAMSON OGOLLA AMUNDO', '../uploads/1743936099_user passport.png', 'SAMSON OGOLLA AMUNDO', '723573153', '723573153', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(114, '../uploads/1743936137_user passport.png', 'BENARD KIMETTO KIBET', '../uploads/1743936137_user passport.png', 'BENARD KIMETTO KIBET', '710736226', '710736226', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(115, '../uploads/1743936179_user passport.png', 'TABITHA MIANO', '../uploads/1743936179_user passport.png', 'TABITHA MIANO', '723676634', '0723676634', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(116, '../uploads/1743936253_user passport.png', 'VELMAX CHEBET', '../uploads/1743936253_user passport.png', 'VELMAX CHEBET', '722883563', '0722883563', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(117, '../uploads/1743936282_user passport.png', 'VICTOR OKWIRI', '../uploads/1743936282_user passport.png', 'VICTOR OKWIRI', '726907530', '726907530', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(118, '../uploads/1743936316_user passport.png', 'JACKLINE GESARE KIRIAGO', '../uploads/1743936316_user passport.png', 'JACKLINE GESARE KIRIAGO', '790304464', '790304464', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(119, '../uploads/1743936350_user passport.png', 'NAOMI CHERONO', '../uploads/1743936350_user passport.png', 'NAOMI CHERONO', '114780208', '0114780208', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(120, '../uploads/1743936384_user passport.png', 'CHRISTOPHER OMBOGO', '../uploads/1743936384_user passport.png', 'CHRISTOPHER OMBOGO', '710252414', '710252414', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(121, '../uploads/1743936419_user passport.png', 'CAROLINE WANJIKU KIMATHI', '../uploads/1743936419_user passport.png', 'CAROLINE WANJIKU KIMATHI', '722932994', '0722932994', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(122, '../uploads/1743936469_user passport.png', 'MOHAMMED SWALAH', '../uploads/1743936469_user passport.png', 'MOHAMMED SWALAH', '723300300', '0723300300', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(123, '../uploads/1743936510_user passport.png', 'MERCY CHEBET(SEARS)', '../uploads/1743936510_user passport.png', 'MERCY CHEBET(SEARS)', '746102200', '0746102200', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(124, '../uploads/1743936542_user passport.png', 'GILBERT KIPKOECH RONO', '../uploads/1743936542_user passport.png', 'GILBERT KIPKOECH RONO', '722894785', '0722894785', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(125, '../uploads/1743936584_user passport.png', 'GEOFFREY KIPKOECH KIRUI', '../uploads/1743936584_user passport.png', 'GEOFFREY KIPKOECH KIRUI', '795721079', '0795721079', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(126, '../uploads/1743936663_user passport.png', 'ELIZABETH MACHARIA', '../uploads/1743936663_user passport.png', 'ELIZABETH MACHARIA', '712901697', '0712901697', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(127, '../uploads/1743936744_user passport.png', 'FAITH CHEROTICH', '../uploads/1743936744_user passport.png', 'FAITH CHEROTICH', '717530315', '0717530315', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(128, '../uploads/1743936788_user passport.png', 'JOYCE BII', '../uploads/1743936788_user passport.png', 'JOYCE BII', '745223444', '0745223444', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(129, '../uploads/1743936826_user passport.png', 'MIRIAM MBATIA', '../uploads/1743936826_user passport.png', 'MIRIAM MBATIA', '725254955', '0725254955', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(130, '../uploads/1743936857_user passport.png', 'DORCAS TESOT', '../uploads/1743936857_user passport.png', 'DORCAS TESOT', '720472125', '0720472125', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(131, '../uploads/1743936890_user passport.png', 'MONICA WANGARI', '../uploads/1743936890_user passport.png', 'MONICA WANGARI', '724018423', '0724018423', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(132, '../uploads/1743936930_user passport.png', 'KENNEDY TUITOEK', '../uploads/1743936930_user passport.png', 'KENNEDY TUITOEK', '793698518', '0793698518', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(133, '../uploads/1743936961_user passport.png', 'PETER CHERUIYOT', '../uploads/1743936961_user passport.png', 'PETER CHERUIYOT', '722642494', '0722642494', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(134, '../uploads/1743936994_user passport.png', 'CHEPKEMOI GLADYS', '../uploads/1743936994_user passport.png', 'CHEPKEMOI GLADYS', '798119028', '0798119028', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(135, '../uploads/1743937027_user passport.png', 'DAVIE MUGA/JACOB OBORA', '../uploads/1743937027_user passport.png', 'DAVIE MUGA/JACOB OBORA', '797894956', '0797894956', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(136, '../uploads/1743937077_user passport.png', 'IVYNE CHEMUTAI', '../uploads/1743937077_user passport.png', 'IVYNE CHEMUTAI', '746168923', '0746168923', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(137, '../uploads/1743937124_user passport.png', 'AUDREY  CHELANGAT', '../uploads/1743937124_user passport.png', 'AUDREY  CHELANGAT', '711444764', '0711444764', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(138, '../uploads/1743937155_user passport.png', 'CHERUIYOT SIELE', '../uploads/1743937155_user passport.png', 'CHERUIYOT SIELE', '725882503', '0725882503', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(139, '../uploads/1743937184_user passport.png', 'MADLINE ANYANGO MAKOKHA', '../uploads/1743937184_user passport.png', 'MADLINE ANYANGO MAKOKHA', '745088676', '0745088676', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(140, '../uploads/1743937221_user passport.png', 'REDEMPTA NAIVAS SUPERMARKET', '../uploads/1743937221_user passport.png', 'REDEMPTA NAIVAS SUPERMARKET', '726061194', '0726061194', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(141, '../uploads/1743937253_user passport.png', 'WINNY CHEPKORIR KOECH', '../uploads/1743937253_user passport.png', 'WINNY CHEPKORIR KOECH', '714779284', '0714779284', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(142, '../uploads/1743937279_user passport.png', 'WESLEY KIPKOECH LANGAT', '../uploads/1743937279_user passport.png', 'WESLEY KIPKOECH LANGAT', '722533883', '0722533883', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(143, '../uploads/1743937311_user passport.png', 'LORNA CHEPKEMOI', '../uploads/1743937311_user passport.png', 'LORNA CHEPKEMOI', '712333256', '0712333256', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(144, '../uploads/1743937347_user passport.png', 'TIMOTHY CHERUIYOT BETT', '../uploads/1743937347_user passport.png', 'TIMOTHY CHERUIYOT BETT', '743668083', '0743668083', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(145, '../uploads/1743937387_user passport.png', 'KIPRONO LEONARD', '../uploads/1743937387_user passport.png', 'KIPRONO LEONARD', '715547125', '0715547125', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(146, '../uploads/1743937418_user passport.png', 'JUSTUS MARITIM', '../uploads/1743937418_user passport.png', 'JUSTUS MARITIM', '700695570', '0700695570', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(147, '../uploads/1743937448_user passport.png', 'SAMMARY KOROS', '../uploads/1743937448_user passport.png', 'SAMMARY KOROS', '724480818', '0724480818', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(148, '../uploads/1743937479_user passport.png', 'MUTAI KIPKOECH VINCENT', '../uploads/1743937479_user passport.png', 'MUTAI KIPKOECH VINCENT', '759276947', '0759276947', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'denniskipngeno@gmail.com'),
(149, '../uploads/1743937746_user passport.png', 'DOMNIC LANGAT', '../uploads/1743937746_user passport.png', 'DOMNIC LANGAT', '728785507', '0728785507', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(150, '../uploads/1743937783_user passport.png', 'DAISY CHEPKEMOI', '../uploads/1743937783_user passport.png', 'DAISY CHEPKEMOI', '721325533', '0721325533', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(151, '../uploads/1743937829_user passport.png', 'WESLEY KIPLANGAT KORGOREN', '../uploads/1743937829_user passport.png', 'WESLEY KIPLANGAT KORGOREN', '712149460', '0712149460', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(152, '../uploads/1743937862_user passport.png', 'KIPKIRUI EVANS KIGEN', '../uploads/1743937862_user passport.png', 'KIPKIRUI EVANS KIGEN', '720144225', '0720144225', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(153, '../uploads/1743937907_user passport.png', 'FRANCIS KIPKEMOI KOECH', '../uploads/1743937907_user passport.png', 'FRANCIS KIPKEMOI KOECH', '721999175', '0721999175', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(154, '../uploads/1743937957_user passport.png', 'KETER TITUS', '../uploads/1743937957_user passport.png', 'KETER TITUS', '728322500', '0728322500', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(155, '../uploads/1743937989_user passport.png', 'KIRUI WILSON', '../uploads/1743937989_user passport.png', 'KIRUI WILSON', '716759261', '0716759261', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(156, '../uploads/1743938024_user passport.png', 'JOAN CHERONO/WILFRED RONO', '../uploads/1743938024_user passport.png', 'JOAN CHERONO/WILFRED RONO', '723552770', '0723552770', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(157, '../uploads/1743938145_user passport.png', 'CHARLES METET', '../uploads/1743938145_user passport.png', 'CHARLES METET', '721640546', '0721640546', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(158, '../uploads/1743938291_user passport.png', 'VICTOR KIPROTICH CHERUIYOT', '../uploads/1743938291_user passport.png', 'VICTOR KIPROTICH CHERUIYOT', '704312245', '704312245', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(159, '../uploads/1743938370_user passport.png', 'PHILEMON KIPRONO BETT', '../uploads/1743938370_user passport.png', 'PHILEMON KIPRONO BETT', '715165886', '0715165886', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(160, '../uploads/1743938418_user passport.png', 'JOSEPH MBATIA', '../uploads/1743938418_user passport.png', 'JOSEPH MBATIA', '712603139', '0712603139', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(161, '../uploads/1743938452_user passport.png', 'DERICK MUSHIRINGANI', '../uploads/1743938452_user passport.png', 'DERICK MUSHIRINGANI', '720216744', '0720216744', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(162, '../uploads/1743938486_user passport.png', 'AMOS OCHONG', '../uploads/1743938486_user passport.png', 'AMOS OCHONG', '729366156', '0729366156', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(163, '../uploads/1743938520_user passport.png', 'STANLEY MWARE ARINGU', '../uploads/1743938520_user passport.png', 'STANLEY MWARE ARINGU', '795720986', '0795720986', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(164, '../uploads/1743938554_user passport.png', 'HARON KIPRONO  KIRUI/NEDDY CHESANG', '../uploads/1743938554_user passport.png', 'HARON KIPRONO  KIRUI/NEDDY CHESANG', '717541735', '0717541735', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(165, '../uploads/1743938591_user passport.png', 'GILBERT MELLY', '../uploads/1743938591_user passport.png', 'GILBERT MELLY', '728918722', '0728918722', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(166, '../uploads/1743938638_user passport.png', 'STEPHEN KIPKEMOI NGETICH', '../uploads/1743938638_user passport.png', 'STEPHEN KIPKEMOI NGETICH', '769226730', '0769226730', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(167, '../uploads/1743938671_user passport.png', 'VINCENT KIMUTAI BII', '../uploads/1743938671_user passport.png', 'VINCENT KIMUTAI BII', '115049117', '0115049117', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(168, '../uploads/1743938707_user passport.png', 'ZEPHANIA KIPROTICH RUTTO', '../uploads/1743938707_user passport.png', 'ZEPHANIA KIPROTICH RUTTO', '718826821', '0718826821', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(169, '../uploads/1743938743_user passport.png', 'LAWRENCE KIPROTICH', '../uploads/1743938743_user passport.png', 'LAWRENCE KIPROTICH', '728884407', '0728884407', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(170, '../uploads/1743938780_user passport.png', 'DOMNIC KIPKOECH BETT', '../uploads/1743938780_user passport.png', 'DOMNIC KIPKOECH BETT', '712149864', '0712149864', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(171, '../uploads/1743938815_user passport.png', 'ISAAC KOSKEI', '../uploads/1743938815_user passport.png', 'ISAAC KOSKEI', '724739206', '0724739206', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(172, '../uploads/1743938856_user passport.png', 'NANCY CHEPKORIR ROTICH', '../uploads/1743938856_user passport.png', 'NANCY CHEPKORIR ROTICH', '791255636', '0791255636', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(173, '../uploads/1743938884_user passport.png', 'CHEPNGENO EVALINE(KURESOI)', '../uploads/1743938884_user passport.png', 'CHEPNGENO EVALINE(KURESOI)', '721971936', '0721971936', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(174, '../uploads/1743938927_user passport.png', 'RAEL ASIKO', '../uploads/1743938927_user passport.png', 'RAEL ASIKO', '723375972', '0723375972', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(175, '../uploads/1743939002_user passport.png', 'NOEL KIPROTICH CHIRCHIR', '../uploads/1743939002_user passport.png', 'NOEL KIPROTICH CHIRCHIR', '724382133', '0724382133', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(176, '../uploads/1743939051_user passport.png', 'DENIS KIPLANGAT MUTAI', '../uploads/1743939051_user passport.png', 'DENIS KIPLANGAT MUTAI', '725740234', '0725740234', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(177, '../uploads/1743939097_user passport.png', 'WELDON CHERUIYOT KOECH', '../uploads/1743939097_user passport.png', 'WELDON CHERUIYOT KOECH', '727599875', '0727599875', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(178, '../uploads/1743939134_user passport.png', 'GEOFFREY KIPROTICH MUTAI', '../uploads/1743939134_user passport.png', 'GEOFFREY KIPROTICH MUTAI', '723231640', '0723231640', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(179, '../uploads/1743939166_user passport.png', 'VINCENT MALIMO', '../uploads/1743939166_user passport.png', 'VINCENT MALIMO', '716289696', '0716289696', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(180, '../uploads/1743939196_user passport.png', 'HASSAN KIPSANG KOECH', '../uploads/1743939196_user passport.png', 'HASSAN KIPSANG KOECH', '704187851', '0704187851', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(181, '../uploads/1743939232_user passport.png', 'RONO DENIS', '../uploads/1743939232_user passport.png', 'RONO DENIS', '707300103', '0707300103', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(182, '../uploads/1743939278_user passport.png', 'DOMNIC CHERUIYOT KIRUI', '../uploads/1743939278_user passport.png', 'DOMNIC CHERUIYOT KIRUI', '716240255', '0716240255', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(183, '../uploads/1743939361_user passport.png', 'HILLARY RUGUT/MAXMILLA KOECH-0707964289/0723591246', '../uploads/1743939361_user passport.png', 'HILLARY RUGUT/MAXMILLA KOECH', '0723591246', '0723591246', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(184, '../uploads/1743939403_user passport.png', 'JAPHET KIPKORIR KOECH', '../uploads/1743939403_user passport.png', 'JAPHET KIPKORIR KOECH', '721781672', '0721781672', '', 0.00, 0.00, '', 'yegondennis@gmail.com'),
(185, '../uploads/1743939443_user passport.png', 'SOLOMON INDIMULI', '../uploads/1743939443_user passport.png', 'SOLOMON INDIMULI', '718661638', '0718661638', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(186, '../uploads/1743939475_user passport.png', 'KEVIN KIPRONO NGENO', '../uploads/1743939475_user passport.png', 'KEVIN KIPRONO NGENO', '707739589', '0707739589', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(187, '../uploads/1743939508_user passport.png', 'AARON KIPRONO CHERUIYOT', '../uploads/1743939508_user passport.png', 'AARON KIPRONO CHERUIYOT', '720330840', '0720330840', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(188, '../uploads/1743939539_user passport.png', 'EZRA RONO', '../uploads/1743939539_user passport.png', 'EZRA RONO', '725439565', '0725439565', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(189, '../uploads/1743939570_user passport.png', 'RONALD KIPRONO KOECH', '../uploads/1743939570_user passport.png', 'RONALD KIPRONO KOECH', '720343336', '0720343336', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(190, '../uploads/1743939600_user passport.png', 'BERNARD LANGAT', '../uploads/1743939600_user passport.png', 'BERNARD LANGAT', '724511024', '0724511024', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(191, '../uploads/1743939634_user passport.png', 'NGENO KIPKIRUI WALTER', '../uploads/1743939634_user passport.png', 'NGENO KIPKIRUI WALTER', '720651751', '0720651751', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(192, '../uploads/1743939665_user passport.png', 'KIBET VICTOR', '../uploads/1743939665_user passport.png', 'KIBET VICTOR', '726456475', '0726456475', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(193, '../uploads/1743939703_user passport.png', 'BOBCOLLINS LANGAT', '../uploads/1743939703_user passport.png', 'BOBCOLLINS LANGAT', '715358750', '0715358750', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(194, '../uploads/1743939740_user passport.png', 'SAMUEL KAMAU', '../uploads/1743939740_user passport.png', 'SAMUEL KAMAU', '700919601', '0700919601', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(195, '../uploads/1743939771_user passport.png', 'FELIX KIPROTICH BETT', '../uploads/1743939771_user passport.png', 'FELIX KIPROTICH BETT', '727993150', '0727993150', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(196, '../uploads/1743939816_user passport.png', 'GLADYS RUTTO', '../uploads/1743939816_user passport.png', 'GLADYS RUTTO', '704264903', '0704264903', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(197, '../uploads/1743939848_user passport.png', 'HENRY SHIRATI', '../uploads/1743939848_user passport.png', 'HENRY SHIRATI', '723568880', '0723568880', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(198, '../uploads/1743939884_user passport.png', 'CHERONO BETCY', '../uploads/1743939884_user passport.png', 'CHERONO BETCY', '724903749', '0724903749', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(199, '../uploads/1743939923_user passport.png', 'AMOS CHERUIYOT', '../uploads/1743939923_user passport.png', 'AMOS CHERUIYOT', '720639166', '0720639166', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'yegondennis@gmail.com'),
(200, '../uploads/1743940562_user passport.png', 'VIOLA CHEROTICH', '../uploads/1743940562_user passport.png', 'VIOLA CHEROTICH', '704401194', '0704401194', 'inuapremium@gmail.com', 0.00, 0.00, 'self employed', 'kiplakirui@gmail.com'),
(201, '../uploads/1743940661_user passport.png', 'JULIET CHEPNGENO', '../uploads/1743940661_user passport.png', 'JULIET CHEPNGENO', '720103818', '0720103818', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(202, '../uploads/1743940707_user passport.png', 'BENARD BII', '../uploads/1743940707_user passport.png', 'BENARD BII', '722970361', '0722970361', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(203, '../uploads/1743940740_user passport.png', 'ELISHA BARUSEI NGETICH', '../uploads/1743940740_user passport.png', 'ELISHA BARUSEI NGETICH', '720493422', '0720493422', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(204, '../uploads/1743940742_user passport.png', 'ELISHA BARUSEI NGETICH', '../uploads/1743940742_user passport.png', 'ELISHA BARUSEI NGETICH', '720493422', '0720493422', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(205, '../uploads/1743940743_user passport.png', 'ELISHA BARUSEI NGETICH', '../uploads/1743940743_user passport.png', 'ELISHA BARUSEI NGETICH', '720493422', '0720493422', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(206, '../uploads/1743940781_user passport.png', 'JOSEPH MAINA ITEGI', '../uploads/1743940781_user passport.png', 'JOSEPH MAINA ITEGI', '720574810', '0720574810', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(207, '../uploads/1743940811_user passport.png', 'BENSON KIPROTICH BETT', '../uploads/1743940811_user passport.png', 'BENSON KIPROTICH BETT', '726561437', '0726561437', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(208, '../uploads/1743940852_user passport.png', 'PASKAL KIPKEMOI', '../uploads/1743940852_user passport.png', 'PASKAL KIPKEMOI', '723934864', '0723934864', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(209, '../uploads/1743941021_user passport.png', 'VINCENT KIPKOECH RONO', '../uploads/1743941021_user passport.png', 'VINCENT KIPKOECH RONO', '719119590', '0719119590', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(210, '../uploads/1743941050_user passport.png', 'OLIVER KIPKIRUI', '../uploads/1743941050_user passport.png', 'OLIVER KIPKIRUI', '713652412', '0713652412', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(211, '../uploads/1743941321_user passport.png', 'CALEB WAFULA CHESOTI', '../uploads/1743941321_user passport.png', 'CALEB WAFULA CHESOTI', '718720258', '0718720258', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(212, '../uploads/1743941363_user passport.png', 'PHILLINE CHELANGAT ', '../uploads/1743941363_user passport.png', 'PHILLINE CHELANGAT ', '723179810', '0723179810', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(213, '../uploads/1743941398_user passport.png', 'COLLINS OCHIENG', '../uploads/1743941398_user passport.png', 'COLLINS OCHIENG', '700486401', '0700486401', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(214, '../uploads/1743941433_user passport.png', 'JULIUS KIPNGETICH LANGAT', '../uploads/1743941433_user passport.png', 'JULIUS KIPNGETICH LANGAT', '703111336', '0703111336', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(215, '../uploads/1743941469_user passport.png', 'JOSEPH NYAGA NDUTA', '../uploads/1743941469_user passport.png', 'JOSEPH NYAGA NDUTA', '791296380', '0791296380', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(216, '../uploads/1743941514_user passport.png', 'HILLARY KIPLANGAT BETT', '../uploads/1743941514_user passport.png', 'HILLARY KIPLANGAT BETT', '793367340', '0793367340', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(217, '../uploads/1743941547_user passport.png', 'KIBET LANGAT', '../uploads/1743941547_user passport.png', 'KIBET LANGAT', '0711136237', '0711136237', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(218, '../uploads/1743941617_user passport.png', 'BRENDA CHEPNGETICH', '../uploads/1743941617_user passport.png', 'BRENDA CHEPNGETICH', '720877161', '0720877161', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(219, '../uploads/1743941655_user passport.png', 'JANET CHEPNGENO', '../uploads/1743941655_user passport.png', 'JANET CHEPNGENO', '724678746', '0724678746', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(220, '../uploads/1743941694_user passport.png', 'SAMARY CHEBET MITEI', '../uploads/1743941694_user passport.png', 'SAMARY CHEBET MITEI', '724763949', '0724763949', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(221, '../uploads/1743941789_user passport.png', 'CAROLINE CHEROTICH SOI', '../uploads/1743941789_user passport.png', 'CAROLINE CHEROTICH SOI', '768464522', '0768464522', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(222, '../uploads/1743941870_user passport.png', 'YVONNE CHEROTICH MITEI SIMIYU', '../uploads/1743941870_user passport.png', 'YVONNE CHEROTICH MITEI SIMIYU', '714675754', '0714675754', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(223, '../uploads/1743941921_user passport.png', 'CHEPKORIR FAITH(EQUITY)', '../uploads/1743941921_user passport.png', 'CHEPKORIR FAITH(EQUITY)', '725960469', '0725960469', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com');
INSERT INTO `borrowers` (`id`, `id_upload`, `full_name`, `passport_photo`, `business_name`, `unique_number`, `mobile`, `email`, `total_paid`, `open_loans_balance`, `status`, `loan_officer`) VALUES
(224, '../uploads/1743941962_user passport.png', 'LENARD KIPKEMOI KOROS', '../uploads/1743941962_user passport.png', 'LENARD KIPKEMOI KOROS', '710116024', '0710116024', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(225, '../uploads/1743942113_user passport.png', 'BOIWA CHEROTICH BETTY', '../uploads/1743942113_user passport.png', 'BOIWA CHEROTICH BETTY', '796600327', '0796600327', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(226, '../uploads/1743942164_user passport.png', 'RODAH MWIKALI MUANGE', '../uploads/1743942164_user passport.png', 'RODAH MWIKALI MUANGE', '703832955', '0703832955', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com'),
(227, '../uploads/1743942239_user passport.png', 'FAITH CHEPNGENO(LETSHEGO)', '../uploads/1743942239_user passport.png', 'FAITH CHEPNGENO(LETSHEGO)', '723476901', '0723476901', 'inuapremium@gmail.com', 0.00, 0.00, 'employed', 'kiplakirui@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `min_loan_amount` decimal(15,2) DEFAULT 0.00,
  `max_loan_amount` decimal(15,2) DEFAULT NULL,
  `min_interest_rate` decimal(5,2) DEFAULT 0.00,
  `max_interest_rate` decimal(5,2) DEFAULT NULL,
  `branch_capital` decimal(15,2) DEFAULT 0.00,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `phone`, `min_loan_amount`, `max_loan_amount`, `min_interest_rate`, `max_interest_rate`, `branch_capital`, `created`, `status`) VALUES
(1, 'Premium', '0702502952', 0.00, 30000.00, 0.00, 6.00, 30000.00, '2024-07-29 11:34:50', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `custmarried`
--

CREATE TABLE `custmarried` (
  `custmarried_id` int(11) NOT NULL,
  `custmarried_status` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `custmarried`
--

INSERT INTO `custmarried` (`custmarried_id`, `custmarried_status`) VALUES
(0, 'N/A'),
(1, 'Single'),
(2, 'Married'),
(3, 'Widowed'),
(4, 'Divorced');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `cust_id` int(11) NOT NULL,
  `cust_no` varchar(50) DEFAULT NULL,
  `cust_name` varchar(100) DEFAULT NULL,
  `cust_dob` int(11) DEFAULT NULL,
  `custsex_id` int(11) NOT NULL,
  `cust_address` varchar(100) DEFAULT NULL,
  `cust_phone` varchar(50) DEFAULT NULL,
  `cust_email` varchar(100) DEFAULT NULL,
  `cust_occup` varchar(50) DEFAULT NULL,
  `custmarried_id` int(11) NOT NULL,
  `cust_heir` varchar(100) DEFAULT NULL,
  `cust_heirrel` varchar(50) DEFAULT NULL,
  `cust_lengthres` int(11) DEFAULT NULL,
  `cust_since` int(11) DEFAULT NULL,
  `custsick_id` int(11) DEFAULT NULL,
  `cust_lastsub` int(11) DEFAULT NULL,
  `cust_active` int(1) NOT NULL DEFAULT 0,
  `cust_lastupd` int(11) DEFAULT NULL,
  `cust_pic` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`cust_id`, `cust_no`, `cust_name`, `cust_dob`, `custsex_id`, `cust_address`, `cust_phone`, `cust_email`, `cust_occup`, `custmarried_id`, `cust_heir`, `cust_heirrel`, `cust_lengthres`, `cust_since`, `custsick_id`, `cust_lastsub`, `cust_active`, `cust_lastupd`, `cust_pic`, `user_id`) VALUES
(0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(1, '001/2016', 'John Wycliff', -1262307600, 1, 'Yorkshire', '031 12 1384', '', 'Theologian', 1, '', '', NULL, 1140000000, 0, 1630718000, 1, 1457431040, 'uploads/photos/customers/cust1_146x190.jpg', 1),
(2, '002/2006', 'Jan Hus', 78793200, 1, 'Prague', '0607 1415', '', 'Reformer', 2, 'Joh. Joseph Hu&szlig;', 'Father', NULL, 1141776000, 0, 1456268400, 1, 1457431296, 'uploads/photos/customers/cust2_146x190.jpg', 1),
(3, '003/2006', 'Martin Luther', 437266800, 1, 'Geneva', '018 02 1546', '', 'Reformer', 2, 'Katharina von Bora', 'Wife', NULL, 1141884000, 5, 1475963995, 1, 1507576835, 'uploads/photos/customers/cust3_146x190.jpg', 1),
(4, '004/2006', 'Huldrych Zwingli', 441759600, 1, 'Zurich', '011 10 1531', '', 'Reformer', 2, 'Anna Reinhart', 'Wife', NULL, 1155552000, 0, 1507500000, 1, 1457433917, 'uploads/photos/customers/cust4_146x190.jpg', 1),
(5, '005/2006', 'Martin Bucer', 689814000, 1, 'Strasbourg', '010 31551', '', 'Reformer', 2, 'Elisabeth Silbereisen', 'Wife', NULL, 1159440000, 0, 1426990400, 1, 1457434157, 'uploads/photos/customers/cust5_146x190.jpg', 1),
(6, '006/2015', 'Philip Melanchthon', 856047600, 1, 'Wittenberg', '019 041560', '', 'Reformer', 2, 'Katharina Krapp', 'Wife', NULL, 1163328000, 0, 1622942000, 1, 1457434738, 'uploads/photos/customers/cust6_146x190.jpg', 1),
(7, '007/2006', 'Heinrich Bullinger', -2065654800, 1, 'Zurich', '017 091575', '', 'Reformer', 2, 'Anna Adlischweiler', 'Wife', NULL, 1167216000, 0, 1456190000, 1, 1457434831, 'uploads/photos/customers/cust7_146x190.jpg', 1),
(8, '008/2006', 'Johannes Calvin', -1908579600, 1, 'Geneva', '027 051564', '', 'Reformer', 2, 'Idelette de Bure', 'Wife', NULL, 1171104000, 0, 1425077995, 1, 1458667201, 'uploads/photos/customers/cust8_146x190.jpg', 1),
(9, '009/2006', 'John Knox', -1767229200, 1, 'Edinburgh', '024 111572', '', 'Reformer', 1, '', '', NULL, 1174992000, 0, 1430446400, 1, 1457435038, 'uploads/photos/customers/cust9_146x190.jpg', 1),
(10, '010/2006', 'Caspar Olevian', -1053824400, 1, 'Heidelberg', '015 031587', '', 'Reformer', 2, 'Philippine von Metz', 'Wife', NULL, 1178880000, 0, 1508104800, 1, 1457435215, 'uploads/photos/customers/cust10_146x190.jpg', 1),
(11, '011/2006', 'Nydius Melvinus', -341802000, 3, 'Kiziba Kikyusa Archdeaconry', '0772-968414', 'huxpoll@yahoo.com', 'Preacher', 2, 'Mrs. Luna Mwamiza', 'Wife', NULL, 1182768000, 1, 1402174400, 0, 1454656213, NULL, 1),
(12, '012/2006', 'Joshua Vandenburg  ', -552448800, 1, 'Kiziba Kikyusa Arch', '0772-551662', '', 'Clergy Man', 2, '', '', NULL, 1186656000, 0, 1469138400, 1, 1420070400, NULL, 1),
(13, '013/2006', 'Melania Mitchem  ', 158364000, 1, 'Kalere', '0782-380513', '', 'Clergy', 1, '', '', NULL, 1190544000, 0, 1413902400, 0, 1420070400, NULL, 1),
(14, '014/2006', 'Clemmie Ellithorpe  ', -929930400, 1, 'Kazinga Butuntumula', '021513', '', 'Clergy Man', 2, '', '', NULL, 1194432000, 0, 1469138400, 1, 1469178601, NULL, 1),
(15, '015/2006', 'Kristofer Artis  ', -90000, 1, 'Kisenyi', '0', '', '', 0, '', '', NULL, 1198320000, 0, 1508104800, 1, 1452688368, NULL, 1),
(16, '016/2007', 'Lulu Obando  ', -440989200, 7, 'Sempa Parish ', '0782-096008', '', 'Clergy Man', 2, '', '', NULL, 1202208000, 0, 1436424400, 1, 1458640847, NULL, 1),
(17, '017/2006', 'Kai Soriano  ', -86403600, 1, 'Luteete', '02314 549945', '', 'Pastor / Teacher', 2, '', '', NULL, 1206096000, 0, 1437358400, 1, 1453822238, NULL, 1),
(18, '018/2006', 'Lynne Pratico  ', 160182000, 1, 'Bwaziba', '0891 128461', '', 'Clergy / Farmer', 2, '', '', NULL, 1209984000, 0, 1418222400, 0, 1453145549, NULL, 1),
(19, '019/2006', 'Noella Holyfield  ', -633578400, 1, 'Kasana -Kiwogozi', '0772-984673', '', 'Clergy Man', 2, '', '', NULL, 1213872000, 0, 1439086400, 1, 1420070400, NULL, 1),
(20, '020/2006', 'Berry Steve  ', -256525200, 1, 'Bombo', '0782-453477', '', 'Clergy Man', 2, '', '', NULL, 1217760000, 0, 1507672800, 1, 1427241600, NULL, 2),
(21, '021/2006', 'Gregorio Schurr  ', -479527200, 1, 'Kasiso', '0772-532964', '', 'Clergy Man', 2, '', '', NULL, 1221648000, 0, 1440814400, 1, 1420070400, NULL, 1),
(22, '022/2006', 'Arnetta Lobato', -744170400, 2, 'Bakijulura', '0785 368641', '', 'Retired', 3, '', '', NULL, 1225536000, 1, 1424991595, 1, 1458718116, NULL, 1),
(23, '023/2006', 'Ayana Mohammed  ', -368762400, 1, 'St. Mark Luweero', '0772-183125', '', 'Clergy Man', 2, '', '', NULL, 1229424000, 0, 1442542400, 1, 1420070400, NULL, 1),
(24, '024/2006', 'Conrad Keitt  ', -748404000, 1, 'Namusale', NULL, '', 'Clergy Man', 2, '', '', NULL, 1233312000, 0, 1443406400, 1, 1420070400, NULL, 1),
(25, '025/2006', 'Stephine Leitner  ', -559875600, 1, 'Buwana', '0773142217', '', 'Clergy Man', 2, '', '', NULL, 1237200000, 0, 1444270400, 0, 1458639837, NULL, 1),
(26, '026/2007', 'Tequila Lino  ', -597549600, 1, 'Sekamuli Area', '0782-880521', '', 'Clergy Man', 2, '', '', NULL, 1241088000, 0, 1445134400, 1, 1420070400, NULL, 1),
(27, '027/2007', 'Deena Hawes  ', -932349600, 1, 'Zirobwe', NULL, '', 'Clergy Man', 2, '', '', NULL, 1244976000, 0, 1445998400, 1, 1420070400, NULL, 1),
(28, '028/2006', 'Kellye Whitley  ', -363924000, 1, 'Lukomera', '0779-253864', '', 'Clergy Man / Teacher', 2, '', '', NULL, 1248864000, 0, 1446862400, 0, 1507628187, NULL, 1),
(29, '029/2007', 'Judi Spillman  ', -573703200, 1, 'Balitta- Lwogi', '0782-559766', '', 'Clergy Man', 2, '', '', NULL, 1252752000, 0, 1447726400, 1, 1420070400, NULL, 1),
(30, '030/2006', 'Lion of Juda Secondary School', -3600, 6, 'Luweero', '0251-1213159', '', '', 0, 'Dr. Raul Philips', 'Headmaster', NULL, 1256640000, 0, 1507672800, 1, 1454954625, NULL, 1),
(31, '031/2006', 'Robena Burget  ', -90000, 5, 'Kasana', '02589 452103', '', 'Clergy Man', 2, '', '', NULL, 1260528000, 0, 1449454400, 0, 1454655778, NULL, 1),
(32, '032/2006', 'Milda Mcamis  ', -427860000, 1, 'Bweyeeyo-Luweero', NULL, '', 'Clergy Man', 2, '', '', NULL, 1264416000, 0, 1450318400, 1, 1420070400, NULL, 1),
(33, '033/2006', 'Alec Kearl  ', -336794400, 1, 'Nakaseke', '0773-974456', '', 'Pastor / Teacher', 2, '', '', NULL, 1268304000, 0, 1451182400, 1, 1427241600, NULL, 3),
(34, '034/2006', 'Ngoc Alcantar  ', -185335200, 1, 'Kasana Kvule-Luweero', NULL, '', 'Clergy Man', 2, '', '', NULL, 1272192000, 0, 1452046400, 1, 1420070400, NULL, 1),
(35, '035/2006', 'Sharen Harr  ', -33271200, 2, 'Luweero Town Council', '0772-442574', '', 'Accounts Clerk', 2, '', '', NULL, 1276080000, 0, 1452910400, 1, 1420070400, NULL, 1),
(36, '036/2006', 'Kryshtam Rebem  ', -320115600, 2, 'Kungu-Busula', '09125 - 54138', '', '', 2, '', '', NULL, 1279968000, 0, 1453774400, 1, 1454959237, NULL, 1),
(37, '037/2006', 'Ronni Knoles  ', -213069600, 1, 'Kungu-Busula', '0772-365951', '', 'Social Worker', 2, '', '', NULL, 1283856000, 0, 1454638400, 1, 1420070400, NULL, 1),
(38, '038/2006', 'Ela Denmark  ', 401230800, 2, 'Kungu-Busula', NULL, '', 'Counsellor / Volunteer', 1, '', '', NULL, 1287744000, 0, 1455502400, 1, 1420070400, NULL, 1),
(39, '039/2006', 'Grace Hamer  ', 55717200, 1, 'Busula', '0701-855942', '', 'Road Supervisor', 1, '', '', NULL, 1291632000, 0, 1456366400, 1, 1420070400, NULL, 1),
(40, '040/2006', 'Emma Bermea  ', -340855200, 2, 'Wobulenzi', NULL, '', 'Teacher', 2, '', '', NULL, 1295520000, 0, 1457230400, 1, 1420070400, NULL, 1),
(41, '041/2006', 'Rosana Breit  ', 534549600, 1, 'Busula', NULL, '', 'Student', 1, '', '', NULL, 1299408000, 0, 1458094400, 1, 1420070400, NULL, 1),
(42, '042/2006', 'Evelynn Mickles  ', 292543200, 2, 'Kungu-Busula', NULL, '', 'Trader - Retail', 2, '', '', NULL, 1303296000, 0, 1458958400, 1, 1420070400, NULL, 1),
(43, '043/2006', 'Tonie Maroney  ', 141858000, 2, 'Bendegere Namusaale', NULL, '', 'Customer Care Manager', 2, '', '', NULL, 1307184000, 0, 1459822400, 1, 1420070400, NULL, 1),
(44, '044/2006', 'Fallon Rosendahl  ', -46231200, 1, 'Buwana Kinyogoga', NULL, '', 'Clergy Man', 2, '', '', NULL, 1311072000, 0, 1460686400, 0, 1427241600, NULL, 3),
(45, '045/2006', 'Renato Loudon  ', -361072800, 1, 'Kaswa- Busula', '0774-764113', '', 'Lay-Reader', 2, '', '', NULL, 1314960000, 0, 1461550400, 1, 1420070400, NULL, 1),
(46, '046/2006', 'Garth Swartwood  ', -184298400, 2, 'Kikoma C/U Wobulenzi Tc', NULL, '', 'Lay-Reader', 2, '', '', NULL, 1318848000, 0, 1462414400, 1, 1420070400, NULL, 1),
(47, '047/2006', 'Joannie Gust  ', 75589200, 2, 'Kikoma Wobulenzi', NULL, '', 'Peasant - Farmer', 2, '', '', NULL, 1322736000, 0, 1463278400, 1, 1420070400, NULL, 1),
(48, '048/2006', 'Fermina Collazo  ', -240890400, 2, 'Kikona Wobulenzi Central', NULL, '', 'Peasant / Farmer', 2, '', '', NULL, 1326624000, 0, 1464142400, 1, 1420070400, NULL, 1),
(49, '049/2006', 'Lavenia Byler  ', -252468000, 1, 'Kayindu C/U', '0785-772868', '', 'Lay-Reader', 2, '', '', NULL, 1330512000, 0, 1465006400, 1, 1420070400, NULL, 1),
(50, '050/2006', 'Patrick Mukasa', 167439600, 1, 'Katuugo Parish', '0782-447156', '', 'Lay-Reader / Tailor', 2, '', '', NULL, 1334400000, 0, 1507672800, 1, 1460549411, NULL, 1),
(51, '051/2008', 'Alicia Wehner  ', -207453600, 2, 'Waluleeta Makulubita', '0782-461460', '', 'Trainer / Social Worker', 2, '', '', NULL, 1338288000, 0, 1466734400, 1, 1420070400, NULL, 1),
(52, '052/2006', 'Ocie Edds  ', -605412000, 1, 'Administrator Luweero Diocese', NULL, '', 'Diocesan Bishop', 2, '', '', NULL, 1342176000, 0, 1467598400, 1, 1420070400, NULL, 1),
(53, '053/2006', 'Darcy Read  ', 309736800, 2, 'Luwero TC', NULL, '', 'Secretary', 1, '', '', NULL, 1346064000, 0, 1468462400, 1, 1420070400, NULL, 1),
(54, '054/2006', 'Augustina Shuman  ', -244605600, 2, 'Kaswa- Busula', NULL, '', 'Lay-Reader', 1, '', '', NULL, 1349952000, 0, 1469326400, 1, 1420070400, NULL, 1),
(55, '055/2009', 'Catherine Adler  ', -3600, 3, 'Luweero Diocese', '0785 368641', '', '', 3, '', '', NULL, 1353840000, 3, 1470190400, 1, 1454572218, NULL, 1),
(56, '056/2007', 'Shanae Bello  ', 77144400, 2, 'Luweero Boys School', NULL, '', 'Teacher', 1, '', '', NULL, 1357728000, 0, 1471054400, 1, 1420070400, NULL, 1),
(57, '057/2006', 'Ferne Munson  ', -7200, 1, 'Bweyeyo', NULL, '', 'Lay-Reader', 2, '', '', NULL, 1361616000, 0, 1471918400, 0, 1427241600, NULL, 3),
(58, '058/2006', 'Ja Nordby  ', -7200, 2, 'Kungu- Kikoma', NULL, '', 'Housewife', 2, '', '', NULL, 1365504000, 0, 1472782400, 1, 1420070400, NULL, 1),
(59, '059/2006', 'Illa Penaflor  ', -179632800, 2, 'Kiwogozi', '0772-662202', '', 'Teacher / MP', 0, '', '', NULL, 1369392000, 0, 1473646400, 1, 1420070400, NULL, 1),
(60, '060/2007', 'Annabelle Bradham  ', -455763600, 5, 'Kiwoko Arch', '0772-657419', '', 'Clergy Man', 2, '', '', NULL, 1373280000, 0, 1474510400, 1, 1454655767, NULL, 1),
(61, '061/2006', 'Tanner Wake  ', -539143200, 1, 'Bukalabi Parish', '0752-631706', '', 'Clergy Man', 2, '', '', NULL, 1377168000, 0, 1475374400, 1, 1420070400, NULL, 1),
(62, '062/2007', 'Cristobal Passman  ', -399088800, 2, 'Luteete Arch', NULL, '', 'Housewife', 2, '', '', NULL, 1381056000, 0, 1476238400, 1, 1420070400, NULL, 1),
(63, '063/2007', 'Rosita Pankratz  ', -394077600, 2, 'Ndejje Village', NULL, '', 'Peasant / Farmer', 2, '', '', NULL, 1384944000, 0, 1477102400, 1, 1420070400, NULL, 1),
(64, '064/2007', 'Angila Gauldin  ', 404949600, 2, 'Nalinya Lwantale Girls P/S', NULL, '', 'Teacher', 1, '', '', NULL, 1388832000, 0, 1477966400, 1, 1420070400, NULL, 1),
(65, '065/2007', 'Jerrica Darnell  ', 534981600, 1, 'Ndejje- Sambwe', NULL, '', 'Student', 1, '', '', NULL, 1392720000, 0, 1478830400, 1, 1420070400, NULL, 1),
(66, '066/2007', 'Paul Mushrush  ', 513554400, 2, 'Ndejje - Sambwe', NULL, '', '', 1, '', '', NULL, 1396608000, 0, 1479694400, 1, 1420070400, NULL, 1),
(67, '067/1970', 'Daren Konkol', -3600, 1, 'Entebbe', '0201 456316', 'konkol@yahoo.com', '', 2, '', '', NULL, 1400496000, 0, 1424905195, 1, 1457078853, NULL, 1),
(68, '068/2007', 'Kristin Lippard  ', 967323600, 2, 'Ndejje- Sambwe', NULL, '', '', 1, '', '', NULL, 1404384000, 0, 1481422400, 1, 1420070400, NULL, 1),
(69, '069/2007', 'Frederic Marchese  ', 510012000, 1, 'Ndejje- Sambwe', NULL, '', '', 1, '', '', NULL, 1408272000, 0, 1482286400, 1, 1420070400, NULL, 1),
(70, '070/2007', 'Gaynelle Busbee  ', -90000, 0, 'Kikoma Wobulenzi', '0566121212', '', 'Service Provider', 2, '', '', NULL, 1412160000, 0, 1483150400, 0, 1453146345, NULL, 1),
(71, '071/2007', 'Remona Sheffler  ', -75693600, 2, 'Kisaawe Muyenga Wobulenzi', NULL, '', 'Teacher', 1, '', '', NULL, 1416048000, 0, 1484014400, 0, 1427241600, NULL, 3),
(72, '072/2006', 'Federica Iliff  ', -115261200, 2, 'Luweero Child Devt Centre', '02589 452103', '', 'Peasant', 1, '', '', NULL, 1419936000, 0, 1517879600, 1, 1455023003, NULL, 1),
(73, '073/2008', 'Chan Milby  ', 864252000, 2, 'St.Peters-Kisugu', NULL, '', '', 1, '', '', NULL, 1423824000, 0, 1485742400, 1, 1420070400, NULL, 1),
(74, '074/2007', 'Piedad Mcgonigal  ', -208231200, 2, 'Ndejje Arch', NULL, '', 'Health Coordinator', 2, '', '', NULL, 1427712000, 0, 1486606400, 1, 1420070400, NULL, 1),
(75, '075/1970', 'Rhonda Pierpont  ', -3600, 2, 'Nakasongola', '0215161', '', '', 0, '', '', NULL, 1431600000, 0, 1487470400, 1, 1460789669, NULL, 1),
(76, '076/2007', 'Celinda Dulac  ', -45194400, 1, 'Luweerotc- Kizito Zone', '0712-219411', '', 'Clergy Man / Teacher', 2, '', '', NULL, 1435488000, 0, 1488334400, 1, 1420070400, NULL, 1),
(77, '077/2007', 'Edmond Kneeland  ', 120348000, 2, 'Luweero', NULL, '', 'Secretary', 2, '', '', NULL, 1439376000, 0, 1489198400, 1, 1420070400, NULL, 1),
(78, '078/2007', 'Lyndia Kump  ', -872301600, 2, 'C/O DCA Kampala', NULL, '', 'Nurse', 1, '', '', NULL, 1443264000, 0, 1490062400, 1, 1420070400, NULL, 1),
(79, '079/2007', 'Michael Poovey  ', -358740000, 2, 'Luweero Diocese', NULL, '', 'CBO Trainer', 2, '', '', NULL, 1447152000, 0, 1490926400, 1, 1420070400, NULL, 1),
(80, '080/2007', 'Omega Prochnow  ', -121312800, 2, 'Luweero Diocese', '0782-352335', '', 'Nurse', 2, '', '', NULL, 1451040000, 0, 1491790400, 1, 1420070400, NULL, 1),
(81, '081/2007', 'Sheri Stuck  ', -873770400, 1, 'Kiteredde Buyuki Katikamu', NULL, '', 'Peasant / Farmer', 2, '', '', NULL, 1454928000, 0, 1492654400, 1, 1420070400, NULL, 1),
(82, '082/2007', 'Shellie Bromley  ', -24544800, 1, 'Kangulumira- Mpologoma ', NULL, '', 'Teacher', 2, '', '', NULL, 1458816000, 0, 1493518400, 0, 1420070400, NULL, 1),
(83, '083/2007', 'Joshua Meiser  ', -1036803600, 1, 'Kikasa Wobulenzi Cetral', '0790-562315', '', 'Building Contractor', 2, 'Anne Meiser', 'Wife', NULL, 1462704000, 0, 1494382400, 1, 1445425402, NULL, 1),
(84, '084/2007', 'Jean Piehl  ', 135727200, 1, 'Wobulenzi-Kigulu', NULL, '', '', 2, '', '', NULL, 1466592000, 0, 1495246400, 1, 1420070400, NULL, 1),
(85, '085/2007', 'Lovella Canaday  ', 399934800, 1, 'Kiwoko - Kasana ', NULL, '', 'Primary Teacher', 1, '', '', NULL, 1470480000, 0, 1496110400, 1, 1420070400, NULL, 1),
(86, '086/2007', 'Val Cauley  ', 200955600, 2, 'Luweero T/C', '0772-688874', '', 'Social Worker', 1, '', '', NULL, 1474368000, 0, 1496974400, 1, 1420070400, NULL, 1),
(87, '087/2008', 'Michale Belvin  ', -600228000, 3, 'Kyatagali - Mabuye -Kamira', NULL, '', 'Lay-Reader / Peasant', 2, '', '', NULL, 1478256000, 0, 1497838400, 1, 1420070400, NULL, 1),
(88, '088/2007', 'Vernon Shade  ', 252630000, 2, 'Kagoma', '0', '', 'Social Worker', 2, '', '', NULL, 1482144000, 0, 1498702400, 1, 1460387555, NULL, 1),
(89, '089/2007', 'Susie Cratty  ', 72054000, 2, 'Katikamu P/S', '0782-158039', '', 'Teacher', 2, '', '', NULL, 1486032000, 0, 1499566400, 1, 1427241600, NULL, 2),
(90, '090/2007', 'Sima Cunningham  ', 188690400, 1, 'Luweero Town Council', '0772-305106', '', 'Social Worker', 1, '', '', NULL, 1489920000, 0, 1500430400, 1, 1420070400, NULL, 1),
(91, '091/2007', 'Leonel Weitzman  ', -164941200, 1, 'Katikamu Trinity Church', '0774068617', '', 'Lay-Reader', 2, '', '', NULL, 1493808000, 0, 1501294400, 1, 1427241600, NULL, 2),
(92, '092/2007', 'Corine Hansell  ', 135986400, 2, 'Katikamu- Sebamala', '0782-485545', '', 'Teacher', 2, '', '', NULL, 1497696000, 0, 1502158400, 1, 1420070400, NULL, 1),
(93, '093/2008', 'Beatrice Cortez  ', 166744800, 1, 'Kibula LC1 Kabakeddi Parish', NULL, '', 'Lay-Reader', 2, '', '', NULL, 1501584000, 0, 1503022400, 1, 1420070400, NULL, 1),
(94, '094/2007', 'Lore Keltz  ', 16837200, 1, 'Katikamu', '0772-670909', '', 'Clergy Man', 2, '', '', NULL, 1505472000, 0, 1503886400, 1, 1420070400, NULL, 1),
(95, '095/2007', 'Eda Edmonson  ', 261352800, 1, 'Kasoma Zone', '0772-641144', '', 'Health Worker', 1, '', '', NULL, 1509360000, 0, 1504750400, 1, 1420070400, NULL, 1),
(96, '096/2007', 'Clotilde Fuqua  ', -83210400, 1, 'Kangulumira- Mpologoma ', '0773-266136', '', 'Business Man', 2, '', '', NULL, 1513248000, 0, 1505614400, 1, 1420070400, NULL, 1),
(97, '097/2007', 'Rosamaria Hardeman  ', -7200, 1, 'Sempa C/U', '0772964823', '', 'Lay-Reader', 2, '', '', NULL, 1517136000, 0, 1506478400, 1, 1420070400, NULL, 1),
(98, '098/2007', 'Wilfred Dinger  ', 24094800, 1, 'Nalulya Butuntumula Luweero Diocese', '0782-424243', '', 'Lay-Reader', 1, '', '', NULL, 1521024000, 0, 1507342400, 1, 1420070400, NULL, 1),
(99, '099/2007', 'Minh Myrie  ', -161920800, 1, 'Mulilo Zone', NULL, '', 'Tailor', 2, '', '', NULL, 1524912000, 0, 1508206400, 1, 1420070400, NULL, 1),
(100, '100/2007', 'Sherly Boudreau', 313974000, 2, 'Kasana T/C', '0782-415747', '', 'Child Development Officer', 1, 'Hans Wurst', '', NULL, 1528800000, 0, 1509070400, 1, 1456493050, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `custsex`
--

CREATE TABLE `custsex` (
  `custsex_id` int(11) NOT NULL,
  `custsex_name` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `custsex`
--

INSERT INTO `custsex` (`custsex_id`, `custsex_name`) VALUES
(0, NULL),
(1, 'Male'),
(2, 'Female'),
(3, 'Couple'),
(4, 'Family'),
(5, 'Group'),
(6, 'Institution'),
(7, 'Business');

-- --------------------------------------------------------

--
-- Table structure for table `custsick`
--

CREATE TABLE `custsick` (
  `custsick_id` int(11) NOT NULL,
  `custsick_name` varchar(50) NOT NULL,
  `custsick_risk` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `custsick`
--

INSERT INTO `custsick` (`custsick_id`, `custsick_name`, `custsick_risk`) VALUES
(0, 'None', 0),
(1, 'Heart Attack', 1),
(2, 'Stroke', 1),
(3, 'Cancer', 3),
(4, 'HIV/AIDS', 3),
(5, 'Ulcer', 1);

-- --------------------------------------------------------

--
-- Table structure for table `disbursed_loans`
--

CREATE TABLE `disbursed_loans` (
  `loan_id` int(11) NOT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `disbursement_date` date NOT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disbursed_loans`
--

INSERT INTO `disbursed_loans` (`loan_id`, `borrower_name`, `amount`, `disbursement_date`, `status`) VALUES
(1, 'John Doe', 1500.00, '2024-01-15', 'Disbursed'),
(2, 'Jane Smith', 2000.00, '2024-02-20', 'Disbursed'),
(3, 'Michael Johnson', 2500.00, '2024-03-25', 'Disbursed'),
(4, 'Emily Davis', 3000.00, '2024-04-10', 'Disbursed'),
(5, 'David Brown', 1200.00, '2024-05-05', 'Disbursed');

-- --------------------------------------------------------

--
-- Table structure for table `guarantors`
--

CREATE TABLE `guarantors` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guarantors`
--

INSERT INTO `guarantors` (`id`, `full_name`, `email`, `phone`) VALUES
(1, 'VINCENT KIPKURUI', 'vincentbettoh@gmail.com', '0702502952'),
(2, 'VINCENT KIPKURUI', 'vincentbettoh@gmail.com', '0702502952');

-- --------------------------------------------------------

--
-- Table structure for table `incomes`
--

CREATE TABLE `incomes` (
  `inc_id` int(11) NOT NULL,
  `inctype_id` int(11) NOT NULL,
  `cust_id` int(11) DEFAULT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `ltrans_id` int(11) DEFAULT NULL,
  `sav_id` int(11) DEFAULT NULL,
  `inc_amount` int(11) NOT NULL,
  `inc_date` int(15) NOT NULL,
  `inc_receipt` int(11) NOT NULL,
  `inc_text` varchar(200) NOT NULL,
  `inc_created` int(11) NOT NULL,
  `user_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `incomes`
--

INSERT INTO `incomes` (`inc_id`, `inctype_id`, `cust_id`, `loan_id`, `ltrans_id`, `sav_id`, `inc_amount`, `inc_date`, `inc_receipt`, `inc_text`, `inc_created`, `user_id`) VALUES
(1, 7, 1, NULL, NULL, NULL, 10000, 1452812400, 1483, '', 1453118784, 1),
(2, 3, 1, NULL, NULL, NULL, 6000, 1454108400, 1484, '', 1453118805, 1),
(3, 2, 1, NULL, NULL, NULL, 1000, 1453158000, 1281, '', 1453207255, 2),
(4, 9, 90, NULL, NULL, NULL, 18000, 1453244400, 180, '', 1453208404, 1),
(5, 2, 100, NULL, NULL, NULL, 1000, 1454281200, 5678, '', 1454329440, 1),
(6, 2, 4, NULL, NULL, NULL, 1000, 1423436400, 548, '', 1455024777, 1),
(7, 2, 5, NULL, NULL, NULL, 1000, 1448924400, 659, '', 1455025157, 1),
(8, 2, 12, NULL, NULL, NULL, 1000, 1435615200, 884, '', 1455025453, 1),
(9, 7, 5, NULL, NULL, NULL, 10000, 1454194800, 8501, '', 1456487835, 1),
(10, 7, 20, 5, NULL, NULL, 10000, 1454540400, 18, '', 1456491502, 1),
(11, 3, 5, 4, NULL, NULL, 8000, 1456268400, 1712, '', 1456491634, 1),
(12, 2, 4, NULL, NULL, 429, 1000, 1456527600, 151, '', 1456576375, 1),
(13, 2, 1, NULL, NULL, 447, 1000, 1456959600, 1236, '', 1457081678, 1),
(14, 2, 15, NULL, NULL, 450, 1000, 1457046000, 1563, '', 1457081766, 1),
(15, 7, 65, 6, NULL, NULL, 10000, 1460325600, 551, '', 1460388152, 1),
(16, 7, 17, 7, NULL, NULL, 10000, 1460412000, 546, '', 1460473151, 1),
(17, 7, 17, 8, NULL, NULL, 10000, 1460412000, 4664, '', 1460473263, 1),
(18, 3, 17, 8, NULL, NULL, 5600, 1460412000, 123, '', 1460479717, 1),
(19, 3, 17, 8, NULL, NULL, 5600, 1460412000, 646894, '', 1460480288, 1),
(20, 3, 17, 8, NULL, NULL, 5600, 1460412000, 684698, '', 1460480655, 1),
(21, 3, 17, 8, NULL, NULL, 5600, 1460412000, 6468, '', 1460480758, 1),
(22, 3, 17, 8, NULL, NULL, 5600, 1460412000, 6464, '', 1460481112, 1),
(23, 3, 17, 8, NULL, NULL, 5600, 1460412000, 6846, '', 1460481451, 1),
(26, 4, 5, NULL, 23, NULL, 24000, 1460498400, 216, '', 1460535050, 1),
(27, 3, 5, 4, NULL, NULL, 8000, 1460498400, 6464, '', 1460538480, 1),
(28, 3, 5, 4, NULL, NULL, 8000, 1460498400, 646, '', 1460538562, 1),
(29, 4, 5, NULL, 175, NULL, 21000, 1460498400, 555, '', 1460538723, 1),
(30, 4, 5, NULL, 176, NULL, 20130, 1460498400, 983, '', 1460539057, 1),
(31, 4, 5, NULL, 177, NULL, 21064, 1460498400, 123, '', 1460539231, 1),
(32, 4, 5, NULL, 178, NULL, 21544, 1460498400, 313, '', 1460539403, 1),
(33, 3, 5, 4, NULL, NULL, 8000, 1460498400, 646, '', 1460539725, 1),
(34, 4, 5, NULL, 185, NULL, 21000, 1460498400, 1689, '', 1460539919, 1),
(35, 3, 20, 5, NULL, NULL, 8000, 1460498400, 4711, '', 1460542720, 1),
(36, 10, 20, 5, NULL, NULL, 12000, 1460498400, 4711, '', 1460542720, 1),
(37, 4, 20, NULL, 195, NULL, 16000, 1460498400, 1234, '', 1460546121, 1),
(38, 4, 20, NULL, 196, NULL, 13720, 1460498400, 56, '', 1460546308, 1),
(39, 4, 20, NULL, 197, NULL, 12414, 1460498400, 1010, '', 1460546608, 1),
(40, 4, 20, NULL, 198, NULL, 12117, 1460498400, 5050, '', 1460546628, 1),
(41, 3, 20, 5, NULL, NULL, 7500, 1460498400, 65456, '', 1460547162, 1),
(42, 10, 20, 5, NULL, NULL, 11250, 1460498400, 65456, '', 1460547162, 1),
(44, 4, 20, NULL, 201, NULL, 15000, 1460498400, 999, '', 1460547290, 1),
(45, 4, 20, NULL, 202, NULL, 13300, 1460498400, 888, '', 1460547333, 1),
(46, 4, 20, NULL, 203, NULL, 11566, 1460498400, 1010, '', 1460547431, 1),
(47, 4, 20, NULL, 204, NULL, 8700, 1460498400, 180, '', 1460547469, 1),
(49, 4, 20, NULL, 205, NULL, 5274, 1460498400, 15, '', 1460547566, 1),
(52, 7, 50, 9, NULL, NULL, 10000, 1461103200, 9876, '', 1460549998, 1),
(53, 3, 50, 9, NULL, NULL, 9000, 1461708000, 6556, '', 1460550055, 1),
(54, 10, 50, 9, NULL, NULL, 13500, 1461708000, 6556, '', 1460550055, 1),
(55, 7, 40, 10, NULL, NULL, 10000, 1462053600, 991, '', 1460550227, 1),
(56, 7, 40, 11, NULL, NULL, 10000, 1462140000, 8486, '', 1460550300, 1),
(57, 7, 35, 12, NULL, NULL, 10000, 1464645600, 153136, '', 1460550528, 1),
(58, 7, 19, 13, NULL, NULL, 10000, 1460498400, 4456, '', 1460550633, 1),
(59, 3, 19, 13, NULL, NULL, 25000, 1461967200, 654156, '', 1460550649, 1),
(60, 10, 19, 13, NULL, NULL, 37500, 1461967200, 654156, '', 1460550649, 1),
(61, 7, 60, 14, NULL, NULL, 10000, 1464732000, 1712, '', 1460557716, 1),
(62, 7, 49, 15, NULL, NULL, 10000, 1461967200, 565, '', 1460557834, 1),
(63, 3, 49, 15, NULL, NULL, 76000, 1461967200, 4646, '', 1460558655, 1),
(64, 10, 49, 15, NULL, NULL, 114000, 1461967200, 4646, '', 1460558655, 1),
(65, 11, 49, 15, NULL, NULL, 8000, 1461967200, 4646, '', 1460558655, 1),
(66, 7, 45, 16, NULL, NULL, 10000, 1467151200, 514641, '', 1460558956, 1),
(67, 3, 45, 16, NULL, NULL, 90000, 1467410400, 654654, '', 1460558992, 1),
(68, 10, 45, 16, NULL, NULL, 135000, 1467410400, 654654, '', 1460558992, 1),
(69, 7, 75, 17, NULL, NULL, 10000, 1460757600, 564, '', 1460789883, 1),
(70, 3, 75, 17, NULL, NULL, 50000, 1461448800, 2344, '', 1460790080, 1),
(71, 10, 75, 17, NULL, NULL, 75000, 1461448800, 2344, '', 1460790080, 1),
(72, 11, 75, 17, NULL, NULL, 5000, 1461448800, 2344, '', 1460790080, 1),
(73, 4, 75, NULL, 253, NULL, 150000, 1464127200, 123, '', 1460790208, 1),
(74, 4, 75, NULL, 254, NULL, 130500, 1465336800, 999, '', 1460790288, 1),
(75, 8, 12, NULL, NULL, 0, 5000, 1469138400, 999, '', 1469178552, 1),
(76, 8, 14, NULL, NULL, 0, 5000, 1469138400, 888, '', 1469178616, 1),
(80, 8, 4, NULL, NULL, 469, 5000, 1507500000, 4711, '', 1507577418, 1),
(85, 9, 17, 0, NULL, NULL, 54800, 1507500000, 4545, 'IT Sales', 1507582608, 1),
(86, 2, 4, NULL, NULL, 632, 1000, 1507586400, 13, '', 1507627793, 1),
(88, 4, 1, NULL, 7, NULL, 15000, 1459461600, 78978, '', 1507628706, 1),
(89, 4, 1, NULL, 8, NULL, 15000, 1463263200, 123123, '', 1507628767, 1),
(92, 7, 10, 18, NULL, NULL, 10000, 1507586400, 560, '', 1507629416, 1),
(93, 3, 10, 18, NULL, NULL, 10000, 1507672800, 800000, '', 1507629498, 1),
(94, 10, 10, 18, NULL, NULL, 12000, 1507672800, 800000, '', 1507629498, 1),
(95, 11, 10, 18, NULL, NULL, 1000, 1507672800, 800000, '', 1507629498, 1),
(96, 8, 50, NULL, NULL, 0, 5000, 1507672800, 8456, '', 1507732097, 1),
(97, 4, 50, NULL, 207, NULL, 27000, 1507672800, 13546, '', 1507732124, 1),
(104, 2, 50, NULL, NULL, 645, 1000, 1507672800, 64, '', 1507736231, 1),
(106, 2, 20, NULL, NULL, 647, 1000, 1507672800, 48, '', 1507740340, 1),
(107, 2, 20, NULL, NULL, 648, 1000, 1507672800, 646, '', 1507740366, 1),
(108, 8, 20, NULL, NULL, 650, 5000, 1507672800, 849, '', 1507740441, 1),
(109, 8, 30, NULL, NULL, 653, 5000, 1507672800, 979, '', 1507740564, 1),
(110, 4, 50, NULL, 208, NULL, 20000, 1507672800, 21, '', 1507740798, 1),
(111, 5, 50, NULL, 209, 655, 5000, 1507672800, 84648, '', 1507740867, 0),
(112, 5, 50, NULL, 210, 656, 15000, 1507672800, 45646, '', 1507748327, 0),
(113, 8, 10, NULL, NULL, 657, 5000, 1508104800, 458, '', 1508149777, 1),
(114, 8, 15, NULL, NULL, 658, 5000, 1508104800, 846, '', 1508150086, 1),
(115, 7, 15, 19, NULL, NULL, 10000, 1508104800, 123, '', 1508150253, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inctype`
--

CREATE TABLE `inctype` (
  `inctype_id` int(11) NOT NULL,
  `inctype_type` varchar(50) NOT NULL,
  `inctype_short` varchar(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `inctype`
--

INSERT INTO `inctype` (`inctype_id`, `inctype_type`, `inctype_short`) VALUES
(1, 'Entrance Fee', 'INC_ENF'),
(2, 'Withdrawal Fee', 'INC_WDF'),
(3, 'Loan Fee', 'INC_LOF'),
(4, 'Loan Interest', 'INC_INT'),
(5, 'Loan Default Fine', 'INC_LDF'),
(6, 'Stationery Sales', 'INC_STS'),
(7, 'Loan Application Fee', 'INC_LAF'),
(8, 'Subscription Fee', 'INC_SUF'),
(9, 'Other', 'INC_OTH'),
(10, 'Insurance', 'INC_INS'),
(11, 'Loan Stationary', 'INC_XL1');

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `installment_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `installment_number` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan`
--

CREATE TABLE `loan` (
  `id` int(11) NOT NULL,
  `loan_product` varchar(255) DEFAULT NULL,
  `business_loan` varchar(255) DEFAULT NULL,
  `borrower` varchar(255) DEFAULT NULL,
  `loan_number` varchar(255) DEFAULT NULL,
  `custom_loan_number` varchar(255) DEFAULT NULL,
  `principal_amount` decimal(10,2) DEFAULT NULL,
  `disbursed_by` varchar(255) DEFAULT NULL,
  `loan_release_date` date DEFAULT NULL,
  `interest_amount` decimal(10,2) DEFAULT NULL,
  `interest_method` varchar(255) DEFAULT NULL,
  `loan_interest_percentage` decimal(5,2) DEFAULT NULL,
  `loan_duration` int(11) DEFAULT NULL,
  `repayment_cycle` varchar(255) DEFAULT NULL,
  `number_of_repayments` int(11) DEFAULT NULL,
  `automated_payments` tinyint(1) DEFAULT NULL,
  `extend_loan_after_maturity` tinyint(1) DEFAULT NULL,
  `processing_fee` decimal(5,2) DEFAULT NULL,
  `registration_fee` decimal(5,2) DEFAULT NULL,
  `loan_status` varchar(255) DEFAULT NULL,
  `guarantors` text DEFAULT NULL,
  `loan_title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `accounting_account` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan`
--

INSERT INTO `loan` (`id`, `loan_product`, `business_loan`, `borrower`, `loan_number`, `custom_loan_number`, `principal_amount`, `disbursed_by`, `loan_release_date`, `interest_amount`, `interest_method`, `loan_interest_percentage`, `loan_duration`, `repayment_cycle`, `number_of_repayments`, `automated_payments`, `extend_loan_after_maturity`, `processing_fee`, `registration_fee`, `loan_status`, `guarantors`, `loan_title`, `description`, `accounting_account`) VALUES
(23, 'Personal Loan', '', 'John Doe', 'LN12345', 'CLN123', 5000.00, 'Cash', '2024-08-01', 500.00, 'flat_rate', 10.00, 12, 'monthly', 12, 1, 0, 2.00, 1.00, '0', '0', 'John\'s Personal Loan', 'Loan for personal expenses.', 'cash'),
(24, 'Personal Loan', '', 'John Doe', 'LN12345', 'CLN123', 5000.00, 'Cash', '2024-08-01', 500.00, 'flat_rate', 10.00, 12, 'monthly', 12, 1, 0, 2.00, 1.00, '0', '0', 'John\'s Personal Loan', 'Loan for personal expenses.', 'cash'),
(25, 'Personal Loan', '', 'John Doe', 'LN12345', 'CLN123', 5000.00, 'Cash', '2024-08-01', 500.00, 'flat_rate', 10.00, 12, 'monthly', 12, 1, 0, 2.00, 1.00, '0', '0', 'John\'s Personal Loan', 'Loan for personal expenses.', 'cash'),
(26, 'Personal Loan', '', 'John Doe', 'LN12345', 'CLN123', 5000.00, 'Cash', '2024-08-01', 500.00, 'flat_rate', 10.00, 12, 'monthly', 12, 1, 0, 2.00, 1.00, '0', '0', 'John\'s Personal Loan', 'Loan for personal expenses.', 'cash');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `term` int(11) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Disbursed') NOT NULL,
  `due_date` date DEFAULT NULL,
  `loan_officer` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loanstatus`
--

CREATE TABLE `loanstatus` (
  `loanstatus_id` int(11) NOT NULL,
  `loanstatus_status` varchar(50) NOT NULL,
  `loanstatus_short` varchar(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `loanstatus`
--

INSERT INTO `loanstatus` (`loanstatus_id`, `loanstatus_status`, `loanstatus_short`) VALUES
(1, 'Pending', 'LST_PEN'),
(2, 'Approved', 'LST_APP'),
(3, 'Refused', 'LST_REF'),
(4, 'Abandoned', 'LST_ABN'),
(5, 'Cleared', 'LST_CLR');

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
-- Dumping data for table `loan_applications`
--

INSERT INTO `loan_applications` (`id`, `borrower`, `loan_product`, `principal`, `loan_release_date`, `interest`, `interest_method`, `loan_interest`, `loan_duration`, `repayment_cycle`, `number_of_repayments`, `processing_fee`, `registration_fee`, `loan_status`, `total_amount`, `total_amount_inclusive`, `id_photo_path`) VALUES
(89, 11, '1', 30000.00, '2025-03-10', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 500.00, 200.00, 'approved', 39000, 39700.00, NULL),
(91, 17, '1', 15000.00, '2025-01-06', 6.00, 'flat_rate', 6.00, 8, 'weekly', 8, 500.00, 0.00, 'approved', 22200, 22700.00, NULL),
(108, 33, '1', 26000.00, '2025-03-04', 6.00, 'flat_rate', 6.00, 8, 'weekly', 8, 500.00, 0.00, 'approved', 38480, 38980.00, NULL),
(110, 34, '1', 10000.00, '2025-03-19', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 12400, 12900.00, NULL),
(111, 35, '1', 10000.00, '2025-01-30', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 500.00, 0.00, 'approved', 13000, 13500.00, NULL),
(112, 36, '1', 40000.00, '2025-02-24', 6.00, 'flat_rate', 6.00, 8, 'weekly', 8, 500.00, 0.00, 'approved', 59200, 59700.00, NULL),
(113, 37, '1', 40000.00, '2025-03-06', 6.00, 'flat_rate', 6.00, 8, 'daily', 8, 500.00, 0.00, 'approved', 59200, 59700.00, NULL),
(114, 38, '1', 5000.00, '2025-03-13', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 400.00, 0.00, 'approved', 6200, 6600.00, NULL),
(115, 39, '1', 100000.00, '2025-03-13', 7.00, 'flat_rate', 7.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 128000, 128500.00, NULL),
(116, 40, '1', 20000.00, '2025-03-20', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 500.00, 0.00, 'approved', 26000, 26500.00, NULL),
(117, 41, '1', 10000.00, '2025-03-20', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 500.00, 0.00, 'approved', 13600, 14100.00, NULL),
(118, 42, '1', 15000.00, '2025-03-20', 24.00, 'flat_rate', 24.00, 1, 'monthly', 1, 500.00, 0.00, 'approved', 18600, 19100.00, NULL),
(119, 43, '1', 20000.00, '2025-03-20', 42.50, 'flat_rate', 42.50, 1, 'monthly', 1, 500.00, 0.00, 'approved', 28500, 29000.00, NULL),
(120, 44, '1', 8000.00, '2025-04-03', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 400.00, 0.00, 'approved', 9920, 10320.00, NULL),
(121, 45, '1', 10000.00, '2025-03-07', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 12400, 12900.00, NULL),
(122, 46, '1', 20000.00, '2025-03-15', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 500.00, 0.00, 'approved', 27200, 27700.00, NULL),
(123, 47, '1', 10000.00, '2025-03-28', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 500.00, 0.00, 'approved', 13000, 13500.00, NULL),
(124, 48, '1', 150000.00, '2025-03-28', 26.66, 'flat_rate', 26.66, 1, 'monthly', 1, 500.00, 500.00, 'approved', 189990, 190990.00, NULL),
(125, 49, '1', 20000.00, '2025-04-04', 6.00, 'flat_rate', 6.00, 8, 'weekly', 8, 500.00, 0.00, 'approved', 29600, 30100.00, NULL),
(126, 50, '1', 15000.00, '2025-01-06', 6.00, 'flat_rate', 6.00, 8, 'weekly', 8, 500.00, 0.00, 'approved', 22200, 22700.00, NULL),
(128, 51, '1', 20000.00, '2025-03-03', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 500.00, 0.00, 'approved', 27200, 27700.00, NULL),
(129, 52, '1', 20000.00, '2025-03-17', 6.00, 'flat_rate', 6.00, 10, 'weekly', 10, 500.00, 0.00, 'approved', 32000, 32500.00, NULL),
(130, 53, '1', 20000.00, '2025-03-18', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 200.00, 'approved', 25000, 25700.00, NULL),
(131, 54, '1', 10000.00, '2025-03-24', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 12400, 12900.00, NULL),
(132, 55, '1', 12000.00, '2025-10-07', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 500.00, 0.00, 'approved', 16320, 16820.00, NULL),
(133, 56, '1', 5000.00, '2025-03-04', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 400.00, 0.00, 'approved', 6500, 6900.00, NULL),
(134, 57, '1', 10000.00, '2025-04-01', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 12400, 12900.00, NULL),
(135, 58, '1', 10000.00, '2025-04-01', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 500.00, 0.00, 'approved', 12400, 12900.00, NULL),
(136, 59, '1', 5000.00, '2025-02-19', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 400.00, 0.00, 'approved', 6200, 6600.00, NULL),
(137, 60, '1', 5000.00, '2025-03-26', 6.00, 'flat_rate', 6.00, 5, 'weekly', 5, 400.00, 0.00, 'approved', 6500, 6900.00, NULL),
(138, 61, '1', 8400.00, '2024-11-28', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 400.00, 0.00, 'approved', 11424, 11824.00, NULL),
(139, 62, '1', 20000.00, '2025-03-06', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 200.00, 'approved', 25000, 25700.00, NULL),
(140, 63, '1', 15000.00, '2025-03-11', 7.50, 'flat_rate', 7.50, 4, 'weekly', 4, 500.00, 0.00, 'approved', 19500, 20000.00, NULL),
(141, 64, '1', 255000.00, '2025-03-13', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 0.00, 'approved', 318750, 319250.00, NULL),
(142, 65, '1', 50000.00, '2025-03-20', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 500.00, 500.00, 'approved', 68000, 69000.00, NULL),
(143, 66, '1', 45000.00, '2025-03-24', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 0.00, 'approved', 56250, 56750.00, NULL),
(144, 67, '1', 24000.00, '2025-03-24', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 0.00, 'approved', 30000, 30500.00, NULL),
(145, 68, '1', 150000.00, '2025-03-24', 25.00, 'flat_rate', 25.00, 1, 'monthly', 1, 500.00, 0.00, 'approved', 187500, 188000.00, NULL),
(146, 72, '1', 60000.00, '2025-03-27', 28.00, 'flat_rate', 28.00, 1, 'monthly', 1, 500.00, 500.00, 'approved', 76800, 77800.00, NULL),
(147, 73, '1', 5000.00, '2025-04-03', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 400.00, 200.00, 'approved', 6800, 7400.00, NULL),
(148, 74, '1', 20000.00, '2025-04-03', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 400.00, 200.00, 'approved', 24800, 25400.00, NULL),
(149, 75, '1', 20000.00, '2025-02-21', 6.00, 'flat_rate', 6.00, 12, 'weekly', 12, 500.00, 0.00, 'approved', 34400, 34900.00, NULL),
(150, 76, '1', 20000.00, '2025-02-26', 30.00, 'flat_rate', 30.00, 1, 'monthly', 1, 500.00, 200.00, 'approved', 26000, 26700.00, NULL),
(151, 80, '1', 7000.00, '2025-02-28', 6.00, 'flat_rate', 6.00, 6, 'weekly', 6, 400.00, 0.00, 'approved', 9520, 9920.00, NULL),
(152, 78, '1', 5000.00, '2025-03-07', 6.00, 'flat_rate', 6.00, 4, 'weekly', 4, 400.00, 0.00, 'approved', 6200, 6600.00, NULL),
(153, 79, '1', 7000.00, '2025-03-07', 7.50, 'flat_rate', 7.50, 7, 'weekly', 7, 400.00, 0.00, 'approved', 10675, 11075.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_guarantors`
--

CREATE TABLE `loan_guarantors` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `guarantor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_products`
--

CREATE TABLE `loan_products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `branch_access` varchar(255) NOT NULL,
  `penalty_settings` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_products`
--

INSERT INTO `loan_products` (`id`, `name`, `branch_access`, `penalty_settings`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Business Loan', 'All Branches', 'Set Penalty', 'Active', '2024-07-21 14:15:00', '2024-07-21 14:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `logrec`
--

CREATE TABLE `logrec` (
  `logrec_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `logrec_start` int(11) DEFAULT NULL,
  `logrec_end` int(11) DEFAULT NULL,
  `logrec_logout` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `logrec`
--

INSERT INTO `logrec` (`logrec_id`, `user_id`, `logrec_start`, `logrec_end`, `logrec_logout`) VALUES
(1, 1, 1458026228, 1459793333, 1),
(2, 1, 1458639083, 1458666852, 1),
(3, 1, 1458666860, 1458670114, 1),
(4, 1, 1458717669, 1458733206, 1),
(5, 1, 1458734461, 1458735254, 1),
(6, 1, 1459001753, 1459001765, 1),
(7, 2, 1459326864, 1459326891, 1),
(8, 1, 1459326903, 1459331867, 1),
(9, 1, 1459784538, 1459791828, 1),
(10, 1, 1459793363, 1459793767, 1),
(11, 1, 1459795734, 1459795996, 1),
(12, 1, 1460204664, 1460205585, 1),
(13, 1, 1460361736, 1460370046, 0),
(14, 1, 1460370046, 1460370260, 1),
(15, 1, 1460386502, 1460471813, 0),
(16, 1, 1460471813, 1460482606, 1),
(17, 1, 1460482632, 1460484435, 1),
(18, 1, 1460484465, 1460484467, 1),
(19, 1, 1460529114, 1460564336, 1),
(20, 1, 1460625771, 1460627784, 1),
(21, 1, 1460629702, 1460630173, 1),
(22, 1, 1460789156, 1461143055, 0),
(23, 1, 1461143055, 1461315675, 0),
(24, 1, 1461315675, 1469179191, 1),
(25, 1, 1469179204, 1469179207, 1),
(26, 1, 1461570119, 1461570773, 1),
(27, 1, 1461844411, 1462345641, 0),
(28, 1, 1462345641, 1462355982, 1),
(29, 1, 1462369592, 1462376237, 0),
(30, 1, 1462376237, 1462377696, 1),
(31, 1, 1462448431, 1507561317, 0),
(32, 1, 1507561317, 1507561368, 0),
(33, 1, 1507561368, 1507561459, 0),
(34, 1, 1507561459, 1507563740, 0),
(35, 1, 1507563740, 1507572001, 0),
(36, 1, 1507572001, 1507572080, 1),
(37, 1, 1507572094, 1507573954, 1),
(38, 3, 1507573968, 1507573976, 1),
(39, 1, 1507573990, 1507581557, 0),
(40, 1, 1507581557, 1507584155, 1),
(41, 1, 1507584622, 1507584662, 1),
(42, 1, 1507627342, 1507630786, 1),
(43, 1, 1507634939, 1507635099, 1),
(44, 1, 1507635487, 1507664454, 0),
(45, 3, 1507661078, 1507661084, 1),
(46, 1, 1507664454, 1507668937, 0),
(47, 1, 1507668937, 1507672482, 1),
(48, 1, 1507672770, 1507672784, 1),
(49, 1, 1507731873, 1507740301, 0),
(50, 1, 1507740301, 1507747087, 0),
(51, 1, 1507747087, 1507748615, 1),
(52, 1, 1507749015, 1507749160, 1),
(53, 1, 1508143696, 1508156672, 0),
(54, 1, 1508156672, 1508159377, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ltrans`
--

CREATE TABLE `ltrans` (
  `ltrans_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `ltrans_due` int(11) DEFAULT NULL,
  `ltrans_date` int(15) DEFAULT NULL,
  `ltrans_principaldue` int(11) DEFAULT NULL,
  `ltrans_principal` int(15) DEFAULT NULL,
  `ltrans_interestdue` int(11) DEFAULT NULL,
  `ltrans_interest` int(11) DEFAULT NULL,
  `ltrans_fined` int(1) NOT NULL DEFAULT 0,
  `ltrans_receipt` int(11) DEFAULT NULL,
  `ltrans_created` int(15) DEFAULT NULL,
  `user_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `ltrans`
--

INSERT INTO `ltrans` (`ltrans_id`, `loan_id`, `ltrans_due`, `ltrans_date`, `ltrans_principaldue`, `ltrans_principal`, `ltrans_interestdue`, `ltrans_interest`, `ltrans_fined`, `ltrans_receipt`, `ltrans_created`, `user_id`) VALUES
(1, 1, 1456956000, 1458424800, 141665, 118750, 21250, 21250, 0, 1234, 1445421102, 3),
(2, 1, 1459634400, 1461103200, 725000, 78750, 21750, 21250, 0, 5678, 1445421253, 3),
(3, 1, 1462312800, NULL, 141667, NULL, 21250, NULL, 0, NULL, 1445421520, 1),
(4, 1, 1464991200, NULL, 141667, NULL, 21250, NULL, 0, NULL, 1454333347, 1),
(5, 1, 1467669600, NULL, 141667, NULL, 21250, NULL, 0, NULL, NULL, 1),
(6, 1, 1470348000, NULL, 141667, NULL, 21250, NULL, 0, NULL, NULL, 1),
(7, 2, 1456786800, 1459461600, 50000, 45000, 15000, 15000, 0, 78978, 1507628706, 1),
(8, 2, 1459465200, 1463263200, 50000, 105000, 15000, 15000, 0, 123123, 1507628767, 1),
(9, 2, 1462143600, NULL, 50000, NULL, 15000, NULL, 0, NULL, 1507628940, 1),
(10, 2, 1464822000, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(11, 2, 1467500400, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(12, 2, 1470178800, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(13, 2, 1472857200, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(14, 2, 1475535600, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(15, 2, 1478214000, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(16, 2, 1480892400, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(17, 2, 1483570800, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(18, 2, 1486249200, NULL, 50000, NULL, 15000, NULL, 0, NULL, NULL, 1),
(143, 8, 1463090400, NULL, 22000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(144, 8, 1465768800, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(145, 8, 1468447200, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(146, 8, 1471125600, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(147, 8, 1473804000, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(148, 8, 1476482400, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(149, 8, 1479160800, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(150, 8, 1481839200, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(151, 8, 1484517600, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(152, 8, 1487196000, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(153, 8, 1489874400, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(154, 8, 1492552800, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(155, 8, 1495231200, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(156, 8, 1497909600, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(157, 8, 1500588000, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(158, 8, 1503266400, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(159, 8, 1505944800, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(160, 8, 1508623200, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(161, 8, 1511301600, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(162, 8, 1513980000, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(163, 8, 1516658400, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(164, 8, 1519336800, NULL, 18000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(185, 4, 1463176800, 1460498400, 70000, 49000, 21000, 21000, 0, 1689, 1460539919, 1),
(186, 4, 1465855200, NULL, 70000, NULL, 18900, NULL, 0, NULL, NULL, 1),
(187, 4, 1468533600, NULL, 70000, NULL, 16800, NULL, 0, NULL, NULL, 1),
(188, 4, 1471212000, NULL, 70000, NULL, 14700, NULL, 0, NULL, NULL, 1),
(189, 4, 1473890400, NULL, 70000, NULL, 12600, NULL, 0, NULL, NULL, 1),
(190, 4, 1476568800, NULL, 70000, NULL, 10500, NULL, 0, NULL, NULL, 1),
(191, 4, 1479247200, NULL, 70000, NULL, 8400, NULL, 0, NULL, NULL, 1),
(192, 4, 1481925600, NULL, 70000, NULL, 6300, NULL, 0, NULL, NULL, 1),
(193, 4, 1484604000, NULL, 70000, NULL, 4200, NULL, 0, NULL, NULL, 1),
(194, 4, 1487282400, NULL, 70000, NULL, 2100, NULL, 0, NULL, NULL, 1),
(201, 5, 1463176800, 1460498400, 125000, 85000, 15000, 15000, 0, 999, 1460547290, 1),
(202, 5, 1465855200, 1460498400, 133000, 86700, 13300, 13300, 0, 888, 1460547333, 1),
(203, 5, 1468533600, 1460498400, 143300, 143300, 11566, 11566, 0, 1010, 1460547431, 1),
(204, 5, 1471212000, 1460498400, 145000, 171300, 8700, 8700, 0, 180, 1460547469, 1),
(205, 5, 1473890400, 1460498400, 131700, 134726, 5274, 5274, 0, 15, 1460547566, 1),
(206, 5, 1476568800, NULL, 128974, NULL, 2579, NULL, 0, NULL, NULL, 1),
(207, 9, 1464386400, 1507672800, 150000, 193000, 27000, 27000, 0, 13546, 1507732124, 1),
(208, 9, 1467064800, 1507672800, 150000, 0, 22500, 20000, 0, 21, 1507740798, 1),
(209, 9, 1469743200, NULL, 150000, NULL, 18000, NULL, 1, NULL, 1507740867, 1),
(210, 9, 1472421600, NULL, 150000, NULL, 13500, NULL, 1, NULL, 1507748327, 1),
(211, 9, 1475100000, NULL, 150000, NULL, 9000, NULL, 0, NULL, NULL, 1),
(212, 9, 1477778400, NULL, 150000, NULL, 4500, NULL, 0, NULL, NULL, 1),
(213, 13, 1464645600, NULL, 212000, NULL, 75000, NULL, 0, NULL, NULL, 1),
(214, 13, 1467324000, NULL, 208000, NULL, 68640, NULL, 0, NULL, NULL, 1),
(215, 13, 1470002400, NULL, 208000, NULL, 62400, NULL, 0, NULL, NULL, 1),
(216, 13, 1472680800, NULL, 208000, NULL, 56160, NULL, 0, NULL, NULL, 1),
(217, 13, 1475359200, NULL, 208000, NULL, 49920, NULL, 0, NULL, NULL, 1),
(218, 13, 1478037600, NULL, 208000, NULL, 43680, NULL, 0, NULL, NULL, 1),
(219, 13, 1480716000, NULL, 208000, NULL, 37440, NULL, 0, NULL, NULL, 1),
(220, 13, 1483394400, NULL, 208000, NULL, 31200, NULL, 0, NULL, NULL, 1),
(221, 13, 1486072800, NULL, 208000, NULL, 24960, NULL, 0, NULL, NULL, 1),
(222, 13, 1488751200, NULL, 208000, NULL, 18720, NULL, 0, NULL, NULL, 1),
(223, 13, 1491429600, NULL, 208000, NULL, 12480, NULL, 0, NULL, NULL, 1),
(224, 13, 1494108000, NULL, 208000, NULL, 6240, NULL, 0, NULL, NULL, 1),
(225, 15, 1464645600, NULL, 637000, NULL, 228000, NULL, 0, NULL, NULL, 1),
(226, 15, 1467324000, NULL, 633000, NULL, 208890, NULL, 0, NULL, NULL, 1),
(227, 15, 1470002400, NULL, 633000, NULL, 189900, NULL, 0, NULL, NULL, 1),
(228, 15, 1472680800, NULL, 633000, NULL, 170910, NULL, 0, NULL, NULL, 1),
(229, 15, 1475359200, NULL, 633000, NULL, 151920, NULL, 0, NULL, NULL, 1),
(230, 15, 1478037600, NULL, 633000, NULL, 132930, NULL, 0, NULL, NULL, 1),
(231, 15, 1480716000, NULL, 633000, NULL, 113940, NULL, 0, NULL, NULL, 1),
(232, 15, 1483394400, NULL, 633000, NULL, 94950, NULL, 0, NULL, NULL, 1),
(233, 15, 1486072800, NULL, 633000, NULL, 75960, NULL, 0, NULL, NULL, 1),
(234, 15, 1488751200, NULL, 633000, NULL, 56970, NULL, 0, NULL, NULL, 1),
(235, 15, 1491429600, NULL, 633000, NULL, 37980, NULL, 0, NULL, NULL, 1),
(236, 15, 1494108000, NULL, 633000, NULL, 18990, NULL, 0, NULL, NULL, 1),
(237, 16, 1470088800, NULL, 555000, NULL, 360000, NULL, 0, NULL, NULL, 1),
(238, 16, 1472767200, NULL, 563000, NULL, 337800, NULL, 0, NULL, NULL, 1),
(239, 16, 1475445600, NULL, 563000, NULL, 315280, NULL, 0, NULL, NULL, 1),
(240, 16, 1478124000, NULL, 563000, NULL, 292760, NULL, 0, NULL, NULL, 1),
(241, 16, 1480802400, NULL, 563000, NULL, 270240, NULL, 0, NULL, NULL, 1),
(242, 16, 1483480800, NULL, 563000, NULL, 247720, NULL, 0, NULL, NULL, 1),
(243, 16, 1486159200, NULL, 563000, NULL, 225200, NULL, 0, NULL, NULL, 1),
(244, 16, 1488837600, NULL, 563000, NULL, 202680, NULL, 0, NULL, NULL, 1),
(245, 16, 1491516000, NULL, 563000, NULL, 180160, NULL, 0, NULL, NULL, 1),
(246, 16, 1494194400, NULL, 563000, NULL, 157640, NULL, 0, NULL, NULL, 1),
(247, 16, 1496872800, NULL, 563000, NULL, 135120, NULL, 0, NULL, NULL, 1),
(248, 16, 1499551200, NULL, 563000, NULL, 112600, NULL, 0, NULL, NULL, 1),
(249, 16, 1502229600, NULL, 563000, NULL, 90080, NULL, 0, NULL, NULL, 1),
(250, 16, 1504908000, NULL, 563000, NULL, 67560, NULL, 0, NULL, NULL, 1),
(251, 16, 1507586400, NULL, 563000, NULL, 45040, NULL, 0, NULL, NULL, 1),
(252, 16, 1510264800, NULL, 563000, NULL, 22520, NULL, 0, NULL, NULL, 1),
(253, 17, 1464127200, 1464127200, 835000, 650000, 150000, 150000, 0, 123, 1460790208, 1),
(254, 17, 1466805600, 1465336800, 870000, 1069500, 130500, 130500, 0, 999, 1460790288, 1),
(255, 17, 1469484000, NULL, 820500, NULL, 98415, NULL, 0, NULL, NULL, 1),
(256, 17, 1472162400, NULL, 820000, NULL, 73800, NULL, 0, NULL, NULL, 1),
(257, 17, 1474840800, NULL, 820000, NULL, 49200, NULL, 0, NULL, NULL, 1),
(258, 17, 1477519200, NULL, 820000, NULL, 24600, NULL, 0, NULL, NULL, 1),
(259, 18, 1510351200, NULL, 80000, NULL, 20000, NULL, 0, NULL, NULL, 1),
(260, 18, 1513029600, NULL, 80000, NULL, 18000, NULL, 0, NULL, NULL, 1),
(261, 18, 1515708000, NULL, 80000, NULL, 16000, NULL, 0, NULL, NULL, 1),
(262, 18, 1518386400, NULL, 80000, NULL, 14000, NULL, 0, NULL, NULL, 1),
(263, 18, 1521064800, NULL, 80000, NULL, 12000, NULL, 0, NULL, NULL, 1),
(264, 18, 1523743200, NULL, 80000, NULL, 10000, NULL, 0, NULL, NULL, 1),
(265, 18, 1526421600, NULL, 80000, NULL, 8000, NULL, 0, NULL, NULL, 1),
(266, 18, 1529100000, NULL, 80000, NULL, 6000, NULL, 0, NULL, NULL, 1),
(267, 18, 1531778400, NULL, 80000, NULL, 4000, NULL, 0, NULL, NULL, 1),
(268, 18, 1534456800, NULL, 80000, NULL, 2000, NULL, 0, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `managers`
--

CREATE TABLE `managers` (
  `manager_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `managers`
--

INSERT INTO `managers` (`manager_id`, `name`, `email`, `phone`, `password`, `area_id`, `created_at`) VALUES
(1, 'Alice Johnson', 'alice.johnson@example.com', '+254700000001', 'hashed_password1', 1, '2024-07-15 19:06:17'),
(2, 'Bob Smith', 'bob.smith@example.com', '+254700000002', 'hashed_password2', 2, '2024-07-15 19:06:17');

-- --------------------------------------------------------

--
-- Table structure for table `navigation_items`
--

CREATE TABLE `navigation_items` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `url` varchar(200) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `navigation_items`
--

INSERT INTO `navigation_items` (`id`, `title`, `url`, `icon`, `parent_id`) VALUES
(1, 'Dashboard', 'index.php', 'bi bi-grid', NULL),
(2, 'Loan Applications', '#loanApplicationsSubmenu', 'bi bi-cash', NULL),
(3, 'New Loan Application', 'loan_application.php', NULL, 2),
(4, 'Approved Loans', 'approved-loans.php', NULL, 2),
(5, 'Rejected Loans', 'rejected-loans.php', NULL, 2),
(6, 'Pending Loans', 'pending-loans.php', NULL, 2),
(7, 'Clients', '#clientsSubmenu', 'bi bi-person', NULL),
(8, 'View Clients', 'view_borrowers.php', NULL, 7),
(9, 'Add Client', 'add_borrower.php', NULL, 7),
(10, 'Repayments', '#repaymentsSubmenu', 'bi bi-credit-card', NULL),
(11, 'Track Repayments', 'repayments.php', NULL, 10),
(12, 'Overdue Repayments', 'overdue_repayments.php', NULL, 10),
(13, 'Reports', '#reportsSubmenu', 'bi bi-bar-chart', NULL),
(14, 'Portfolio at Risk (PAR)', 'par.php', NULL, 13),
(15, 'Performance Reports', 'performance-reports.php', NULL, 13),
(16, 'Settings', 'settings.php', 'bi bi-gear', NULL),
(17, 'arears', 'arears.php', NULL, 13),
(18, 'Loan Officers', '', 'bi bi-person', NULL),
(19, 'View loan officers', 'view_loanOfficers.php', NULL, 18),
(20, 'add loan Officers', 'add_loanOfficers.php', NULL, 18),
(21, 'Approve Loans', 'all_loans.php', NULL, 2),
(22, 'Add Repayments', 'repayment.php', NULL, 10),
(23, 'manager_dashboard', 'manager_dashboard.php', 'bi bi-grid', NULL),
(24, 'View Specific Clients', 'view_specific_borrowers.php', '', 7);

-- --------------------------------------------------------

--
-- Table structure for table `navigation_item_roles`
--

CREATE TABLE `navigation_item_roles` (
  `navigation_item_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `navigation_item_roles`
--

INSERT INTO `navigation_item_roles` (`navigation_item_id`, `role_id`) VALUES
(2, 1),
(4, 1),
(5, 1),
(6, 1),
(10, 1),
(11, 1),
(13, 1),
(14, 1),
(15, 1),
(1, 1),
(22, 1),
(1, 4),
(13, 4),
(19, 4),
(14, 4),
(3, 3),
(19, 4),
(17, 3),
(16, 3),
(23, 4),
(21, 4),
(1, 2),
(7, 2),
(9, 2),
(24, 2),
(3, 2),
(2, 2),
(2, 4),
(4, 4),
(5, 4),
(6, 4),
(19, 4),
(18, 4);

-- --------------------------------------------------------

--
-- Table structure for table `outstanding_balance`
--

CREATE TABLE `outstanding_balance` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outstanding_balance`
--

INSERT INTO `outstanding_balance` (`id`, `loan_id`, `borrower_name`, `amount`, `due_date`, `status`) VALUES
(1, 1, 'John Doe', 1500.00, '2024-08-15', 'Pending'),
(2, 2, 'Jane Smith', 2000.00, '2024-08-20', 'Overdue'),
(3, 3, 'Michael Johnson', 2500.00, '2024-08-25', 'Pending'),
(4, 4, 'Emily Davis', 3000.00, '2024-09-10', 'Overdue'),
(5, 5, 'David Brown', 1200.00, '2024-09-05', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `mpesa_code` varchar(20) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_date_records`
--

CREATE TABLE `payment_date_records` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `PaymentDate` date NOT NULL,
  `Amount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_date_records`
--

INSERT INTO `payment_date_records` (`id`, `loan_id`, `PaymentDate`, `Amount`) VALUES
(4, 39, '2025-03-21', 775),
(5, 39, '2025-03-21', 0),
(6, 39, '2025-03-21', 500),
(7, 39, '2025-03-21', 500),
(8, 38, '2025-03-21', 5000),
(9, 48, '2025-03-25', 2300),
(10, 88, '2025-04-05', 3400),
(11, 89, '2025-04-05', 23480),
(12, 91, '2025-04-05', 14725),
(13, 126, '2025-04-06', 14725),
(14, 92, '2025-04-06', 9210),
(15, 93, '2025-04-06', 13600),
(16, 94, '2025-04-06', 3900),
(17, 96, '2025-04-06', 1550),
(18, 97, '2025-04-06', 1360),
(19, 98, '2025-04-06', 2270),
(20, 99, '2025-04-06', 5500),
(21, 102, '2025-04-06', 8957),
(22, 103, '2025-04-06', 4900),
(23, 104, '2025-04-06', 6275),
(24, 107, '2025-04-06', 13875),
(25, 108, '2025-04-06', 18760),
(26, 110, '2025-04-06', 3000),
(27, 111, '2025-04-06', 10553),
(28, 112, '2025-04-06', 31100),
(29, 114, '2025-04-06', 4080),
(30, 115, '2025-04-06', 43500),
(31, 116, '2025-04-06', 5200),
(32, 117, '2025-04-06', 4600),
(33, 121, '2025-04-06', 9300),
(34, 122, '2025-04-06', 8884),
(35, 113, '2025-04-06', 53362),
(36, 128, '2025-04-06', 16950),
(37, 129, '2025-04-06', 6500),
(38, 131, '2025-04-06', 3100),
(39, 132, '2025-04-06', 11940),
(40, 133, '2025-04-06', 5150),
(41, 136, '2025-04-06', 5940),
(42, 137, '2025-04-06', 1300),
(43, 138, '2025-04-06', 9850),
(44, 140, '2025-04-06', 16157),
(45, 142, '2025-04-06', 22670),
(46, 149, '2025-04-06', 18968),
(47, 151, '2025-04-06', 5474),
(48, 153, '2025-04-06', 4550);

-- --------------------------------------------------------

--
-- Table structure for table `repayments`
--

CREATE TABLE `repayments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `repayment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `paid` decimal(10,2) DEFAULT 0.00,
  `repaid_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repayments`
--

INSERT INTO `repayments` (`id`, `loan_id`, `repayment_date`, `amount`, `paid`, `repaid_date`) VALUES
(501, 108, '2025-03-11', 4810.00, 4810.00, '2025-04-06'),
(502, 108, '2025-03-18', 4810.00, 4810.00, '2025-04-06'),
(503, 108, '2025-03-25', 4810.00, 4810.00, '2025-04-06'),
(504, 108, '2025-04-01', 4810.00, 4330.00, '2025-04-06'),
(505, 108, '2025-04-08', 4810.00, 0.00, NULL),
(506, 108, '2025-04-15', 4810.00, 0.00, NULL),
(507, 108, '2025-04-22', 4810.00, 0.00, NULL),
(508, 108, '2025-04-29', 4810.00, 0.00, NULL),
(513, 110, '2025-03-26', 3100.00, 3000.00, '2025-04-06'),
(514, 110, '2025-04-02', 3100.00, 0.00, NULL),
(515, 110, '2025-04-09', 3100.00, 0.00, NULL),
(516, 110, '2025-04-16', 3100.00, 0.00, NULL),
(517, 111, '2025-02-06', 2600.00, 2600.00, '2025-04-06'),
(518, 111, '2025-02-13', 2600.00, 2600.00, '2025-04-06'),
(519, 111, '2025-02-20', 2600.00, 2600.00, '2025-04-06'),
(520, 111, '2025-02-27', 2600.00, 2600.00, '2025-04-06'),
(521, 111, '2025-03-06', 2600.00, 153.00, '2025-04-06'),
(522, 112, '2025-03-03', 7400.00, 7400.00, '2025-04-06'),
(523, 112, '2025-03-10', 7400.00, 7400.00, '2025-04-06'),
(524, 112, '2025-03-17', 7400.00, 7400.00, '2025-04-06'),
(525, 112, '2025-03-24', 7400.00, 7400.00, '2025-04-06'),
(526, 112, '2025-03-31', 7400.00, 1500.00, '2025-04-06'),
(527, 112, '2025-04-07', 7400.00, 0.00, NULL),
(528, 112, '2025-04-14', 7400.00, 0.00, NULL),
(529, 112, '2025-04-21', 7400.00, 0.00, NULL),
(530, 113, '2025-03-07', 7400.00, 7400.00, '2025-04-06'),
(531, 113, '2025-03-08', 7400.00, 7400.00, '2025-04-06'),
(532, 113, '2025-03-09', 7400.00, 7400.00, '2025-04-06'),
(533, 113, '2025-03-10', 7400.00, 7400.00, '2025-04-06'),
(534, 113, '2025-03-11', 7400.00, 7400.00, '2025-04-06'),
(535, 113, '2025-03-12', 7400.00, 7400.00, '2025-04-06'),
(536, 113, '2025-03-13', 7400.00, 7400.00, '2025-04-06'),
(537, 113, '2025-03-14', 7400.00, 1562.00, '2025-04-06'),
(538, 114, '2025-03-20', 1550.00, 1550.00, '2025-04-06'),
(539, 114, '2025-03-27', 1550.00, 1550.00, '2025-04-06'),
(540, 114, '2025-04-03', 1550.00, 980.00, '2025-04-06'),
(541, 114, '2025-04-10', 1550.00, 0.00, NULL),
(542, 115, '2025-03-20', 32000.00, 32000.00, '2025-04-06'),
(543, 115, '2025-03-27', 32000.00, 11500.00, '2025-04-06'),
(544, 115, '2025-04-03', 32000.00, 0.00, NULL),
(545, 115, '2025-04-10', 32000.00, 0.00, NULL),
(546, 116, '2025-03-27', 5200.00, 5200.00, '2025-04-06'),
(547, 116, '2025-04-03', 5200.00, 0.00, NULL),
(548, 116, '2025-04-10', 5200.00, 0.00, NULL),
(549, 116, '2025-04-17', 5200.00, 0.00, NULL),
(550, 116, '2025-04-24', 5200.00, 0.00, NULL),
(551, 117, '2025-03-27', 2266.67, 2266.67, '2025-04-06'),
(552, 117, '2025-04-03', 2266.67, 2266.67, '2025-04-06'),
(553, 117, '2025-04-10', 2266.67, 66.66, '2025-04-06'),
(554, 117, '2025-04-17', 2266.67, 0.00, NULL),
(555, 117, '2025-04-24', 2266.67, 0.00, NULL),
(556, 117, '2025-05-01', 2266.67, 0.00, NULL),
(557, 118, '2025-04-20', 18600.00, 0.00, NULL),
(558, 119, '2025-04-20', 28500.00, 0.00, NULL),
(559, 120, '2025-04-10', 2480.00, 0.00, NULL),
(560, 120, '2025-04-17', 2480.00, 0.00, NULL),
(561, 120, '2025-04-24', 2480.00, 0.00, NULL),
(562, 120, '2025-05-01', 2480.00, 0.00, NULL),
(563, 121, '2025-03-14', 3100.00, 3100.00, '2025-04-06'),
(564, 121, '2025-03-21', 3100.00, 3100.00, '2025-04-06'),
(565, 121, '2025-03-28', 3100.00, 3100.00, '2025-04-06'),
(566, 121, '2025-04-04', 3100.00, 0.00, NULL),
(567, 122, '2025-03-22', 4533.33, 4533.33, '2025-04-06'),
(568, 122, '2025-03-29', 4533.33, 4350.67, '2025-04-06'),
(569, 122, '2025-04-05', 4533.33, 0.00, NULL),
(570, 122, '2025-04-12', 4533.33, 0.00, NULL),
(571, 122, '2025-04-19', 4533.33, 0.00, NULL),
(572, 122, '2025-04-26', 4533.33, 0.00, NULL),
(573, 123, '2025-04-04', 2600.00, 0.00, NULL),
(574, 123, '2025-04-11', 2600.00, 0.00, NULL),
(575, 123, '2025-04-18', 2600.00, 0.00, NULL),
(576, 123, '2025-04-25', 2600.00, 0.00, NULL),
(577, 123, '2025-05-02', 2600.00, 0.00, NULL),
(578, 124, '2025-04-28', 189990.00, 0.00, NULL),
(579, 125, '2025-04-11', 3700.00, 0.00, NULL),
(580, 125, '2025-04-18', 3700.00, 0.00, NULL),
(581, 125, '2025-04-25', 3700.00, 0.00, NULL),
(582, 125, '2025-05-02', 3700.00, 0.00, NULL),
(583, 125, '2025-05-09', 3700.00, 0.00, NULL),
(584, 125, '2025-05-16', 3700.00, 0.00, NULL),
(585, 125, '2025-05-23', 3700.00, 0.00, NULL),
(586, 125, '2025-05-30', 3700.00, 0.00, NULL),
(587, 126, '2025-01-13', 2775.00, 2775.00, '2025-04-06'),
(588, 126, '2025-01-20', 2775.00, 2775.00, '2025-04-06'),
(589, 126, '2025-01-27', 2775.00, 2775.00, '2025-04-06'),
(590, 126, '2025-02-03', 2775.00, 2775.00, '2025-04-06'),
(591, 126, '2025-02-10', 2775.00, 2775.00, '2025-04-06'),
(592, 126, '2025-02-17', 2775.00, 850.00, '2025-04-06'),
(593, 126, '2025-02-24', 2775.00, 0.00, NULL),
(594, 126, '2025-03-03', 2775.00, 0.00, NULL),
(599, 128, '2025-03-10', 4533.33, 4533.33, '2025-04-06'),
(600, 128, '2025-03-17', 4533.33, 4533.33, '2025-04-06'),
(601, 128, '2025-03-24', 4533.33, 4533.33, '2025-04-06'),
(602, 128, '2025-03-31', 4533.33, 3350.01, '2025-04-06'),
(603, 128, '2025-04-07', 4533.33, 0.00, NULL),
(604, 128, '2025-04-14', 4533.33, 0.00, NULL),
(605, 129, '2025-03-24', 3200.00, 3200.00, '2025-04-06'),
(606, 129, '2025-03-31', 3200.00, 3200.00, '2025-04-06'),
(607, 129, '2025-04-07', 3200.00, 100.00, '2025-04-06'),
(608, 129, '2025-04-14', 3200.00, 0.00, NULL),
(609, 129, '2025-04-21', 3200.00, 0.00, NULL),
(610, 129, '2025-04-28', 3200.00, 0.00, NULL),
(611, 129, '2025-05-05', 3200.00, 0.00, NULL),
(612, 129, '2025-05-12', 3200.00, 0.00, NULL),
(613, 129, '2025-05-19', 3200.00, 0.00, NULL),
(614, 129, '2025-05-26', 3200.00, 0.00, NULL),
(615, 130, '2025-04-18', 25000.00, 0.00, NULL),
(616, 131, '2025-03-31', 3100.00, 3100.00, '2025-04-06'),
(617, 131, '2025-04-07', 3100.00, 0.00, NULL),
(618, 131, '2025-04-14', 3100.00, 0.00, NULL),
(619, 131, '2025-04-21', 3100.00, 0.00, NULL),
(620, 132, '2025-10-14', 2720.00, 2720.00, '2025-04-06'),
(621, 132, '2025-10-21', 2720.00, 2720.00, '2025-04-06'),
(622, 132, '2025-10-28', 2720.00, 2720.00, '2025-04-06'),
(623, 132, '2025-11-04', 2720.00, 2720.00, '2025-04-06'),
(624, 132, '2025-11-11', 2720.00, 1060.00, '2025-04-06'),
(625, 132, '2025-11-18', 2720.00, 0.00, NULL),
(626, 133, '2025-03-11', 1300.00, 1300.00, '2025-04-06'),
(627, 133, '2025-03-18', 1300.00, 1300.00, '2025-04-06'),
(628, 133, '2025-03-25', 1300.00, 1300.00, '2025-04-06'),
(629, 133, '2025-04-01', 1300.00, 1250.00, '2025-04-06'),
(630, 133, '2025-04-08', 1300.00, 0.00, NULL),
(631, 134, '2025-04-08', 3100.00, 0.00, NULL),
(632, 134, '2025-04-15', 3100.00, 0.00, NULL),
(633, 134, '2025-04-22', 3100.00, 0.00, NULL),
(634, 134, '2025-04-29', 3100.00, 0.00, NULL),
(635, 135, '2025-04-08', 3100.00, 0.00, NULL),
(636, 135, '2025-04-15', 3100.00, 0.00, NULL),
(637, 135, '2025-04-22', 3100.00, 0.00, NULL),
(638, 135, '2025-04-29', 3100.00, 0.00, NULL),
(639, 136, '2025-02-26', 1550.00, 1550.00, '2025-04-06'),
(640, 136, '2025-03-05', 1550.00, 1550.00, '2025-04-06'),
(641, 136, '2025-03-12', 1550.00, 1550.00, '2025-04-06'),
(642, 136, '2025-03-19', 1550.00, 1290.00, '2025-04-06'),
(643, 137, '2025-04-02', 1300.00, 1300.00, '2025-04-06'),
(644, 137, '2025-04-09', 1300.00, 0.00, NULL),
(645, 137, '2025-04-16', 1300.00, 0.00, NULL),
(646, 137, '2025-04-23', 1300.00, 0.00, NULL),
(647, 137, '2025-04-30', 1300.00, 0.00, NULL),
(648, 138, '2024-12-05', 1904.00, 1904.00, '2025-04-06'),
(649, 138, '2024-12-12', 1904.00, 1904.00, '2025-04-06'),
(650, 138, '2024-12-19', 1904.00, 1904.00, '2025-04-06'),
(651, 138, '2024-12-26', 1904.00, 1904.00, '2025-04-06'),
(652, 138, '2025-01-02', 1904.00, 1904.00, '2025-04-06'),
(653, 138, '2025-01-09', 1904.00, 330.00, '2025-04-06'),
(654, 139, '2025-04-06', 25000.00, 0.00, NULL),
(655, 140, '2025-03-18', 4875.00, 4875.00, '2025-04-06'),
(656, 140, '2025-03-25', 4875.00, 4875.00, '2025-04-06'),
(657, 140, '2025-04-01', 4875.00, 4875.00, '2025-04-06'),
(658, 140, '2025-04-08', 4875.00, 1532.00, '2025-04-06'),
(659, 141, '2025-04-13', 318750.00, 0.00, NULL),
(660, 142, '2025-03-27', 11333.33, 11333.33, '2025-04-06'),
(661, 142, '2025-04-03', 11333.33, 11333.33, '2025-04-06'),
(662, 142, '2025-04-10', 11333.33, 3.34, '2025-04-06'),
(663, 142, '2025-04-17', 11333.33, 0.00, NULL),
(664, 142, '2025-04-24', 11333.33, 0.00, NULL),
(665, 142, '2025-05-01', 11333.33, 0.00, NULL),
(666, 143, '2025-04-24', 56250.00, 0.00, NULL),
(667, 144, '2025-04-24', 30000.00, 0.00, NULL),
(668, 145, '2025-04-24', 187500.00, 0.00, NULL),
(669, 146, '2025-04-27', 76800.00, 0.00, NULL),
(670, 147, '2025-04-10', 1133.33, 0.00, NULL),
(671, 147, '2025-04-17', 1133.33, 0.00, NULL),
(672, 147, '2025-04-24', 1133.33, 0.00, NULL),
(673, 147, '2025-05-01', 1133.33, 0.00, NULL),
(674, 147, '2025-05-08', 1133.33, 0.00, NULL),
(675, 147, '2025-05-15', 1133.33, 0.00, NULL),
(676, 148, '2025-04-10', 6200.00, 0.00, NULL),
(677, 148, '2025-04-17', 6200.00, 0.00, NULL),
(678, 148, '2025-04-24', 6200.00, 0.00, NULL),
(679, 148, '2025-05-01', 6200.00, 0.00, NULL),
(680, 149, '2025-02-28', 2866.67, 2866.67, '2025-04-06'),
(681, 149, '2025-03-07', 2866.67, 2866.67, '2025-04-06'),
(682, 149, '2025-03-14', 2866.67, 2866.67, '2025-04-06'),
(683, 149, '2025-03-21', 2866.67, 2866.67, '2025-04-06'),
(684, 149, '2025-03-28', 2866.67, 2866.67, '2025-04-06'),
(685, 149, '2025-04-04', 2866.67, 2866.67, '2025-04-06'),
(686, 149, '2025-04-11', 2866.67, 1767.98, '2025-04-06'),
(687, 149, '2025-04-18', 2866.67, 0.00, NULL),
(688, 149, '2025-04-25', 2866.67, 0.00, NULL),
(689, 149, '2025-05-02', 2866.67, 0.00, NULL),
(690, 149, '2025-05-09', 2866.67, 0.00, NULL),
(691, 149, '2025-05-16', 2866.67, 0.00, NULL),
(692, 150, '2025-03-26', 26000.00, 0.00, NULL),
(693, 151, '2025-03-07', 1586.67, 1586.67, '2025-04-06'),
(694, 151, '2025-03-14', 1586.67, 1586.67, '2025-04-06'),
(695, 151, '2025-03-21', 1586.67, 1586.67, '2025-04-06'),
(696, 151, '2025-03-28', 1586.67, 713.99, '2025-04-06'),
(697, 151, '2025-04-04', 1586.67, 0.00, NULL),
(698, 151, '2025-04-11', 1586.67, 0.00, NULL),
(699, 152, '2025-03-14', 1550.00, 0.00, NULL),
(700, 152, '2025-03-21', 1550.00, 0.00, NULL),
(701, 152, '2025-03-28', 1550.00, 0.00, NULL),
(702, 152, '2025-04-04', 1550.00, 0.00, NULL),
(703, 153, '2025-03-14', 1525.00, 1525.00, '2025-04-06'),
(704, 153, '2025-03-21', 1525.00, 1525.00, '2025-04-06'),
(705, 153, '2025-03-28', 1525.00, 1500.00, '2025-04-06'),
(706, 153, '2025-04-04', 1525.00, 0.00, NULL),
(707, 153, '2025-04-11', 1525.00, 0.00, NULL),
(708, 153, '2025-04-18', 1525.00, 0.00, NULL),
(709, 153, '2025-04-25', 1525.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'Admin'),
(2, 'Loan Officer'),
(3, 'client'),
(4, 'manager');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `currency_words` varchar(255) DEFAULT NULL,
  `date_format` varchar(255) DEFAULT NULL,
  `decimal_separator` varchar(255) DEFAULT NULL,
  `thousand_separator` varchar(255) DEFAULT NULL,
  `results_per_page` int(11) DEFAULT NULL,
  `monthly_repayment_cycle` varchar(255) DEFAULT NULL,
  `yearly_repayment_cycle` varchar(255) DEFAULT NULL,
  `days_in_month` int(11) DEFAULT NULL,
  `days_in_year` int(11) DEFAULT NULL,
  `business_registration_number` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `zipcode` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `company_name`, `country`, `timezone`, `currency`, `currency_words`, `date_format`, `decimal_separator`, `thousand_separator`, `results_per_page`, `monthly_repayment_cycle`, `yearly_repayment_cycle`, `days_in_month`, `days_in_year`, `business_registration_number`, `address`, `city`, `province`, `zipcode`, `logo`) VALUES
(1, 'Inua premium', 'Kenya', 'Africa/Nairobi', 'KES - KSh', 'Shillings', 'dd/mm/yyyy', 'Dot (.)', 'Comma (,)', 20, 'Same Day Every Month', 'Same Day Every Year', 30, 360, '1280B', '2024', 'Nairobi', 'Riftvalley', '20200', 'logo.png');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` varchar(30) NOT NULL DEFAULT 'inactive',
  `role_id` varchar(11) NOT NULL,
  `area` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `is_active`, `role_id`, `area`) VALUES
(14, 'Antonio Cheruiyot', 'cheruiyotantonio@gmail.com', '0725234899', '$2y$10$wF6FRtiUJiNH4hNWdMgV5./vnIlUV7zAVMFX9JNvEHmoRWtcSgOLO', 'inactive', '1', '1'),
(17, 'Antonio Cheruiyot', 'cantonio@kabarak.ac.ke', '0725234899', '$2y$10$8rr5JApm.7K6TEW/VtB.Mu5x6xTqG8lcn2OimpQakPhIbQq5Y0UoO', 'inactive', '2', '1'),
(20, 'Harold koskei', 'kosgei298@gmail.com', '0713904249', '$2y$10$8vDIzd8PUDFJQEeLDlKlO.xO2xfUSQlQQRhr39.rF8z65z0q.y9w6', 'inactive', '4', '1'),
(23, 'sheba chepkorir', 'sheba@gmail.com', '0111795158', '$2y$10$wK4YfnDn.R3nsIxBKAoCFeYF6FcgvJuep4h2CTHzWxDjw3z8yM.r2', 'inactive', '2', '1'),
(24, 'Emmanuel Kiplangat', 'kiplakirui@gmail.com', '0714947451', '$2y$10$6N4WlShaIQ02MKA67birG.FJmMPGmcnCavqikViZUbo08cj1XQxfK', 'inactive', '2', '1'),
(25, 'Kipngeno Dennis', 'denniskipngeno@gmail.com', '0723618279', '$2y$10$z3avUdtmCJv51pClUjvIN.F/cBR7ZjJ9J0DvfcUXB8/41Yaw2XU.u', 'inactive', '2', '1'),
(26, 'Yegon Dennis', 'yegondennis@gmail.com', '0726530080', '$2y$10$v/KNc0FqL//UL9YgbRhoauh2Ixplao6gunrNtemcn0vF2OCxsy0tu', 'inactive', '2', '1'),
(27, 'Winny Chepkirui', 'chepkiruiwinny@gmail.com', '0708267488', '$2y$10$WMkh4TiVbwbWS/eyY6YHzu71vOSbnBsDxtJms8NraCKdO9iUZuglm', 'inactive', '2', '1');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`area_id`),
  ADD UNIQUE KEY `area_name` (`area_name`);

--
-- Indexes for table `borrowers`
--
ALTER TABLE `borrowers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disbursed_loans`
--
ALTER TABLE `disbursed_loans`
  ADD PRIMARY KEY (`loan_id`);

--
-- Indexes for table `guarantors`
--
ALTER TABLE `guarantors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`installment_id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan`
--
ALTER TABLE `loan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `guarantor_id` (`guarantor_id`);

--
-- Indexes for table `loan_products`
--
ALTER TABLE `loan_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `managers`
--
ALTER TABLE `managers`
  ADD PRIMARY KEY (`manager_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `navigation_items`
--
ALTER TABLE `navigation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `navigation_item_roles`
--
ALTER TABLE `navigation_item_roles`
  ADD KEY `navigation_item_id` (`navigation_item_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `outstanding_balance`
--
ALTER TABLE `outstanding_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mpesa_code` (`mpesa_code`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `payment_date_records`
--
ALTER TABLE `payment_date_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `repayments`
--
ALTER TABLE `repayments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repayments_ibfk_1` (`loan_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `borrowers`
--
ALTER TABLE `borrowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disbursed_loans`
--
ALTER TABLE `disbursed_loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `guarantors`
--
ALTER TABLE `guarantors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `installment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan`
--
ALTER TABLE `loan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_products`
--
ALTER TABLE `loan_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `managers`
--
ALTER TABLE `managers`
  MODIFY `manager_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `navigation_items`
--
ALTER TABLE `navigation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `outstanding_balance`
--
ALTER TABLE `outstanding_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_date_records`
--
ALTER TABLE `payment_date_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `repayments`
--
ALTER TABLE `repayments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=710;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  ADD CONSTRAINT `loan_guarantors_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_guarantors_ibfk_2` FOREIGN KEY (`guarantor_id`) REFERENCES `guarantors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `managers`
--
ALTER TABLE `managers`
  ADD CONSTRAINT `managers_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL;

--
-- Constraints for table `navigation_items`
--
ALTER TABLE `navigation_items`
  ADD CONSTRAINT `navigation_items_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`id`);

--
-- Constraints for table `navigation_item_roles`
--
ALTER TABLE `navigation_item_roles`
  ADD CONSTRAINT `navigation_item_roles_ibfk_1` FOREIGN KEY (`navigation_item_id`) REFERENCES `navigation_items` (`id`),
  ADD CONSTRAINT `navigation_item_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `repayments`
--
ALTER TABLE `repayments`
  ADD CONSTRAINT `repayments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_applications` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
