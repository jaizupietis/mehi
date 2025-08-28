/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: mehu_uzd
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB-0+deb12u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary table structure for view `aktīvie_uzdevumi`
--

DROP TABLE IF EXISTS `aktīvie_uzdevumi`;
/*!50001 DROP VIEW IF EXISTS `aktīvie_uzdevumi`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `aktīvie_uzdevumi` AS SELECT
 1 AS `id`,
  1 AS `nosaukums`,
  1 AS `prioritate`,
  1 AS `statuss`,
  1 AS `jabeidz_lidz`,
  1 AS `izveidots`,
  1 AS `mehaniķis`,
  1 AS `vieta`,
  1 AS `iekārta` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `darba_grafiks`
--

DROP TABLE IF EXISTS `darba_grafiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `darba_grafiks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotaja_id` int(11) NOT NULL,
  `datums` date NOT NULL,
  `maina` enum('R','V','B') NOT NULL COMMENT 'R=Rīta maiņa, V=Vakara maiņa, B=Brīvdiena',
  `izveidoja_id` int(11) NOT NULL,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date_shift` (`lietotaja_id`,`datums`,`maina`),
  KEY `izveidoja_id` (`izveidoja_id`),
  KEY `idx_datums` (`datums`),
  KEY `idx_lietotaja_id` (`lietotaja_id`),
  CONSTRAINT `darba_grafiks_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE,
  CONSTRAINT `darba_grafiks_ibfk_2` FOREIGN KEY (`izveidoja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `darba_grafiks`
--

LOCK TABLES `darba_grafiks` WRITE;
/*!40000 ALTER TABLE `darba_grafiks` DISABLE KEYS */;
INSERT INTO `darba_grafiks` VALUES
(1,8,'2025-08-04','B',1,'2025-08-05 04:21:05'),
(2,9,'2025-08-04','R',1,'2025-08-05 04:21:05'),
(3,6,'2025-08-04','V',1,'2025-08-05 04:21:05'),
(4,5,'2025-08-04','V',1,'2025-08-05 04:21:05'),
(5,8,'2025-08-05','B',1,'2025-08-05 04:21:05'),
(6,9,'2025-08-05','R',1,'2025-08-05 04:21:05'),
(7,6,'2025-08-05','V',1,'2025-08-05 04:21:05'),
(8,5,'2025-08-05','V',1,'2025-08-05 04:21:05'),
(9,8,'2025-08-06','B',1,'2025-08-05 04:21:05'),
(10,9,'2025-08-06','R',1,'2025-08-05 04:21:05'),
(11,6,'2025-08-06','V',1,'2025-08-05 04:21:05'),
(12,5,'2025-08-06','V',1,'2025-08-05 04:21:05'),
(13,8,'2025-08-07','B',1,'2025-08-05 04:21:05'),
(14,9,'2025-08-07','R',1,'2025-08-05 04:21:05'),
(15,6,'2025-08-07','V',1,'2025-08-05 04:21:05'),
(16,5,'2025-08-07','V',1,'2025-08-05 04:21:05'),
(17,8,'2025-08-08','B',1,'2025-08-05 04:21:05'),
(18,9,'2025-08-08','R',1,'2025-08-05 04:21:05'),
(19,6,'2025-08-08','V',1,'2025-08-05 04:21:05'),
(20,5,'2025-08-08','V',1,'2025-08-05 04:21:05'),
(21,8,'2025-08-19','R',3,'2025-08-19 05:57:14'),
(22,9,'2025-08-19','R',3,'2025-08-19 05:57:14'),
(23,11,'2025-08-19','R',3,'2025-08-19 05:57:14'),
(24,6,'2025-08-19','B',3,'2025-08-19 05:57:14'),
(25,5,'2025-08-19','R',3,'2025-08-19 05:57:14'),
(26,8,'2025-08-20','R',3,'2025-08-19 05:57:15'),
(27,9,'2025-08-20','R',3,'2025-08-19 05:57:15'),
(28,11,'2025-08-20','R',3,'2025-08-19 05:57:15'),
(29,6,'2025-08-20','B',3,'2025-08-19 05:57:15'),
(30,5,'2025-08-20','R',3,'2025-08-19 05:57:15'),
(31,8,'2025-08-21','R',3,'2025-08-19 05:57:15'),
(32,9,'2025-08-21','R',3,'2025-08-19 05:57:15'),
(33,11,'2025-08-21','R',3,'2025-08-19 05:57:15'),
(34,6,'2025-08-21','B',3,'2025-08-19 05:57:15'),
(35,5,'2025-08-21','R',3,'2025-08-19 05:57:15'),
(36,8,'2025-08-22','R',3,'2025-08-19 05:57:15'),
(37,9,'2025-08-22','R',3,'2025-08-19 05:57:15'),
(38,11,'2025-08-22','R',3,'2025-08-19 05:57:15'),
(39,6,'2025-08-22','B',3,'2025-08-19 05:57:15'),
(40,5,'2025-08-22','R',3,'2025-08-19 05:57:15');
/*!40000 ALTER TABLE `darba_grafiks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `darba_laiks`
--

DROP TABLE IF EXISTS `darba_laiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `darba_laiks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotaja_id` int(11) NOT NULL,
  `uzdevuma_id` int(11) NOT NULL,
  `sakuma_laiks` timestamp NULL DEFAULT current_timestamp(),
  `beigu_laiks` timestamp NULL DEFAULT NULL,
  `stundu_skaits` decimal(5,2) DEFAULT NULL,
  `komentars` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lietotaja_id` (`lietotaja_id`),
  KEY `uzdevuma_id` (`uzdevuma_id`),
  CONSTRAINT `darba_laiks_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`),
  CONSTRAINT `darba_laiks_ibfk_2` FOREIGN KEY (`uzdevuma_id`) REFERENCES `uzdevumi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `darba_laiks`
--

LOCK TABLES `darba_laiks` WRITE;
/*!40000 ALTER TABLE `darba_laiks` DISABLE KEYS */;
INSERT INTO `darba_laiks` VALUES
(119,11,147,'2025-08-18 11:21:19','2025-08-18 11:21:48',0.00,NULL),
(121,9,149,'2025-08-19 04:43:30','2025-08-19 04:43:37',0.00,NULL);
/*!40000 ALTER TABLE `darba_laiks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faili`
--

DROP TABLE IF EXISTS `faili`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `faili` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `originalais_nosaukums` varchar(255) NOT NULL,
  `saglabatais_nosaukums` varchar(255) NOT NULL,
  `faila_cels` varchar(500) NOT NULL,
  `faila_tips` varchar(50) DEFAULT NULL,
  `faila_izmers` int(11) DEFAULT NULL,
  `tips` enum('Uzdevums','Problēma') NOT NULL,
  `saistitas_id` int(11) NOT NULL COMMENT 'uzdevuma vai problēmas ID',
  `augšupielādēja_id` int(11) NOT NULL,
  `augšupielādēts` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `augšupielādēja_id` (`augšupielādēja_id`),
  CONSTRAINT `faili_ibfk_1` FOREIGN KEY (`augšupielādēja_id`) REFERENCES `lietotaji` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faili`
--

LOCK TABLES `faili` WRITE;
/*!40000 ALTER TABLE `faili` DISABLE KEYS */;
INSERT INTO `faili` VALUES
(1,'AJM-logo.png','687631a32e58a_2025-07-15_13-46-59.png','uploads/687631a32e58a_2025-07-15_13-46-59.png','image/png',29447,'Uzdevums',3,1,'2025-07-15 10:46:59'),
(2,'Lizums.jpg','6876339c69bdd_2025-07-15_13-55-24.jpg','uploads/6876339c69bdd_2025-07-15_13-55-24.jpg','image/jpeg',49995,'Problēma',2,4,'2025-07-15 10:55:24'),
(3,'TV 3840x2160.jpg','68763c2a158dd_2025-07-15_14-31-54.jpg','uploads/68763c2a158dd_2025-07-15_14-31-54.jpg','image/jpeg',577702,'Uzdevums',4,3,'2025-07-15 11:31:54'),
(4,'2.jpeg','687e2b5003b5f_2025-07-21_14-58-08.jpeg','uploads/687e2b5003b5f_2025-07-21_14-58-08.jpeg','image/jpeg',59772,'Problēma',4,4,'2025-07-21 11:58:08'),
(5,'WhatsApp Image 2025-07-22 at 09.38.37.jpeg','687f32fdcccc0_2025-07-22_09-43-09.jpeg','uploads/687f32fdcccc0_2025-07-22_09-43-09.jpeg','image/jpeg',245738,'Uzdevums',50,1,'2025-07-22 06:43:09'),
(6,'WhatsApp Image 2025-04-01 at 11.25.40.jpeg','6892ed03a2380_2025-08-06_08-49-55.jpeg','uploads/6892ed03a2380_2025-08-06_08-49-55.jpeg','image/jpeg',10184,'Problēma',13,10,'2025-08-06 05:49:55'),
(7,'1000060366.jpg','689499b351e39_2025-08-07_15-18-59.jpg','uploads/689499b351e39_2025-08-07_15-18-59.jpg','image/jpeg',41560,'Problēma',28,10,'2025-08-07 12:18:59'),
(8,'20250311_175225.jpg','68a30b2ade451_2025-08-18_14-14-50.jpg','uploads/68a30b2ade451_2025-08-18_14-14-50.jpg','image/jpeg',677051,'Uzdevums',147,1,'2025-08-18 11:14:50');
/*!40000 ALTER TABLE `faili` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iekartas`
--

DROP TABLE IF EXISTS `iekartas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `iekartas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(100) NOT NULL,
  `apraksts` text DEFAULT NULL,
  `vietas_id` int(11) DEFAULT NULL,
  `aktīvs` tinyint(1) DEFAULT 1,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vietas_id` (`vietas_id`),
  CONSTRAINT `iekartas_ibfk_1` FOREIGN KEY (`vietas_id`) REFERENCES `vietas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iekartas`
--

LOCK TABLES `iekartas` WRITE;
/*!40000 ALTER TABLE `iekartas` DISABLE KEYS */;
INSERT INTO `iekartas` VALUES
(54,'201 - Opti Cut 450','',1,1,'2025-08-05 09:49:32'),
(55,'202 - Opti Cut Elite 200','',1,1,'2025-08-05 09:49:33'),
(56,'203 - Grecon Combipact 4','',1,1,'2025-08-05 09:49:33'),
(58,'204 - Wintersteiger BDS','',1,1,'2025-08-05 09:49:33'),
(59,'307 - Costa - CCC CCC 1350','',2,1,'2025-08-05 09:49:33'),
(60,'309 - Friulmac C-Mat','',2,1,'2025-08-05 09:49:33'),
(61,'310 - Friulmac C-Mat Plus 4291 A','',2,1,'2025-08-05 09:49:33'),
(62,'311 - Friulmac C-Mat 3852-A','',2,1,'2025-08-05 09:49:33'),
(63,'312 - Koch','',2,1,'2025-08-05 09:49:33'),
(64,'313 - Friulmac Idramat 4205A','',2,1,'2025-08-05 09:49:33'),
(65,'314 - Vitap','',2,1,'2025-08-05 09:49:33'),
(66,'315 - Detel','',2,1,'2025-08-05 09:49:33'),
(67,'319 - Homag - OPTIMAT NFL 26/8/25','',2,1,'2025-08-05 09:49:33'),
(68,'320 - Friulmac Quadramat 4206','',2,1,'2025-08-05 09:49:33'),
(69,'320 A - Q-Mat + Biesse','',2,1,'2025-08-05 09:49:33'),
(70,'321 - Biesse Techno FDT 27179','',2,1,'2025-08-05 09:49:33'),
(71,'322 - Biesse Techno FDT 50744','',2,1,'2025-08-05 09:49:33'),
(72,'323 - Biesse Techno N65/88','',2,1,'2025-08-05 09:49:33'),
(73,'324 - Biesse Skipper 130','',2,1,'2025-08-05 09:49:33'),
(74,'325 - CNC Rover A4','',2,1,'2025-08-05 09:49:33'),
(75,'326 - CNC Rover B7.4','',2,1,'2025-08-05 09:49:33'),
(76,'327 - CNC Rover B7.6','',2,1,'2025-08-05 09:49:33'),
(77,'328 - CNC Excel','',2,1,'2025-08-05 09:49:33'),
(78,'331 - Stegher NF450','',2,1,'2025-08-05 09:49:33'),
(79,'335 - Costa - SA CCT 1350','',2,1,'2025-08-05 09:49:33'),
(80,'337 - OMAL','',2,1,'2025-08-05 09:49:33'),
(81,'341 - Bacci Double Jet','',2,1,'2025-08-05 09:49:33'),
(82,'342 - ZAFFARONI MSR','',2,1,'2025-08-05 09:49:33'),
(83,'403 - Cefla Performa','',24,1,'2025-08-05 09:49:33'),
(84,'403 T - Cefla Performa TopLaka','',24,1,'2025-08-05 09:49:33'),
(85,'404 - Makor','',24,1,'2025-08-05 09:49:33'),
(86,'404 T - Makor TopLaka','',24,1,'2025-08-05 09:49:33'),
(87,'405 - Cefla Easy 2000','',24,1,'2025-08-05 09:49:33'),
(88,'405 T - Cefla Easy 2000 TopLaka','',24,1,'2025-08-05 09:49:33'),
(117,'601 - Ēvele - Weinig H1000','',3,1,'2025-08-05 11:08:54'),
(118,'602 - Garināšana Opti Cut 200','',3,1,'2025-08-05 11:08:54'),
(119,'603 - Rilesa X-Line','',3,1,'2025-08-05 11:08:54'),
(120,'604 - Rilesa Sānu montāža','',3,1,'2025-08-05 11:08:54'),
(121,'605 - Labošana (Līnija)','',3,1,'2025-08-05 11:08:54'),
(122,'606 - Butfering','',3,1,'2025-08-05 11:08:54'),
(123,'609 - Pakošana','',3,1,'2025-08-05 11:08:54'),
(124,'610 - Detel MVS 0-0-2-0 HP','',3,1,'2025-08-05 11:08:54'),
(125,'702 - Ēvele - Weinig Hydromat 230','',4,1,'2025-08-05 11:08:54'),
(126,'703 - Šķēlējzāģis - Weinig U23','',4,1,'2025-08-05 11:08:54'),
(127,'704 - Opti Cut S75','',4,1,'2025-08-05 11:08:54'),
(128,'705 - Opti Cut S90','',4,1,'2025-08-05 11:08:54'),
(129,'706 - Galu fāzēšana - Līstes','',4,1,'2025-08-05 11:08:54'),
(130,'707 - Galu dēļu apstrādes līnija (FR+URB)','',4,1,'2025-08-05 11:08:55'),
(131,'708 - Gala dēļu apstrādes līnija','',4,1,'2025-08-05 11:08:55'),
(132,'710 - Montāža','',4,1,'2025-08-05 11:08:55'),
(133,'711 - Urbšana','',4,1,'2025-08-05 11:08:55'),
(134,'712 - Naglošana','',4,1,'2025-08-05 11:08:55'),
(135,'713 - Garināšana','',4,1,'2025-08-05 11:08:55'),
(136,'714 - Netek 1','',4,1,'2025-08-05 11:08:55'),
(137,'715 - Netek 2','',4,1,'2025-08-05 11:08:55'),
(138,'719 - KNAGGLIG pakošana','',4,1,'2025-08-05 11:08:55'),
(139,'720 - Pakošana','',4,1,'2025-08-05 11:08:55'),
(140,'721 - ODDVAR pakošana','',4,1,'2025-08-05 11:08:55'),
(141,'901 - Apaļo Brikešu pakošanas līnija','M3 - Aiz Gultu ceha',3,1,'2025-08-05 11:08:56'),
(142,'902 - Kantaino Brikešu pak. līnija','M4 - pie Knagglig ceha',4,1,'2025-08-05 11:08:56');
/*!40000 ALTER TABLE `iekartas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `jaunās_problēmas`
--

DROP TABLE IF EXISTS `jaunās_problēmas`;
/*!50001 DROP VIEW IF EXISTS `jaunās_problēmas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `jaunās_problēmas` AS SELECT
 1 AS `id`,
  1 AS `nosaukums`,
  1 AS `prioritate`,
  1 AS `statuss`,
  1 AS `izveidots`,
  1 AS `zinotājs`,
  1 AS `vieta`,
  1 AS `iekārta` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `lietotaji`
--

DROP TABLE IF EXISTS `lietotaji`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lietotaji` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotajvards` varchar(50) NOT NULL,
  `parole` varchar(255) NOT NULL,
  `vards` varchar(100) NOT NULL,
  `uzvards` varchar(100) NOT NULL,
  `epasts` varchar(100) DEFAULT NULL,
  `telefons` varchar(20) DEFAULT NULL,
  `telegram_username` varchar(100) DEFAULT NULL,
  `telegram_chat_id` bigint(20) DEFAULT NULL,
  `telegram_registered` tinyint(1) DEFAULT 0,
  `nokluseta_vietas_id` int(11) DEFAULT NULL,
  `noklusetas_iekartas_id` int(11) DEFAULT NULL,
  `loma` enum('Administrators','Menedžeris','Operators','Mehāniķis') NOT NULL,
  `statuss` enum('Aktīvs','Neaktīvs') DEFAULT 'Aktīvs',
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  `pēdējā_pieslēgšanās` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lietotajvards` (`lietotajvards`),
  KEY `nokluseta_vietas_id` (`nokluseta_vietas_id`),
  KEY `noklusetas_iekartas_id` (`noklusetas_iekartas_id`),
  CONSTRAINT `lietotaji_ibfk_1` FOREIGN KEY (`nokluseta_vietas_id`) REFERENCES `vietas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lietotaji_ibfk_2` FOREIGN KEY (`noklusetas_iekartas_id`) REFERENCES `iekartas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lietotaji`
--

LOCK TABLES `lietotaji` WRITE;
/*!40000 ALTER TABLE `lietotaji` DISABLE KEYS */;
INSERT INTO `lietotaji` VALUES
(1,'admin','$2y$10$WxTZqiwagN5qf6ForcAaLOI/CeEDElQSvNH4uWWa6plGYeStqOYoe','Administrators','AVOTI','admin@avoti.lv',NULL,'aizups',7486360988,1,NULL,NULL,'Administrators','Aktīvs','2025-07-15 06:13:35','2025-08-28 04:41:15'),
(3,'menedzers','$2y$10$/JBv84qQr.EfdvdPLtGZ8OKJHmb/oX3CwhjgRGkKxNeFytqLVbrWS','Jānis','Menedžeris','menedzers@avoti.lv',NULL,NULL,NULL,0,NULL,NULL,'Menedžeris','Aktīvs','2025-07-15 09:37:54','2025-08-28 04:41:39'),
(4,'operators','$2y$10$OfGVVd0Z.7A7CJGg9dr9w.6Jg1cEv/1zh8mI4qnDCyK5SKPZwWjGW','Anna','Operatore','operators@avoti.lv',NULL,NULL,NULL,0,3,119,'Operators','Aktīvs','2025-07-15 09:37:54','2025-08-26 07:29:01'),
(5,'mehaniķis1','$2y$10$//ZXvYYELvpB9Ghae54nfue82HesYfN8A4pkEEzIu.MiTYff2N02i','Pēteris','Mehāniķis','mehanikis1@avoti.lv',NULL,NULL,NULL,0,NULL,NULL,'Mehāniķis','Aktīvs','2025-07-15 09:37:54','2025-08-19 09:03:44'),
(6,'mehaniķis2','$2y$10$1XxUBArFr0Otixkqe3r61OgPVuw61aurYC6fM6VlolgKFjeBhEEoi','Māris','Krancis','mehanikis2@avoti.lv',NULL,NULL,NULL,0,NULL,NULL,'Mehāniķis','Aktīvs','2025-07-15 09:37:54','2025-08-19 07:44:25'),
(7,'edzus.kurins','$2y$10$GxHpHwKtfY5/PwHOloiey.de0UDx93/JDoKMDvP/omNAK7N7uzeDy','Edžus','Kūriņš','edzus.kurins@avoti.lv',NULL,NULL,NULL,0,NULL,NULL,'Menedžeris','Aktīvs','2025-07-15 11:34:43',NULL),
(8,'fuksis','$2y$10$t.ti8plsl8pit.h4y2BsOegJdQU1cd55majwzmcHYVtwmlZ1Za4hC','Aivars','Pētersons',NULL,NULL,NULL,NULL,0,NULL,NULL,'Mehāniķis','Aktīvs','2025-07-16 11:49:03','2025-08-28 04:41:54'),
(9,'aizups','$2y$10$gVNn/gCVTqZDdaWKIF392ujncfyX03NYTKCut.N5qrhHZdjDjuEFG','Jānis','Aizupietis','janis.aizupietis@avoti.lv','+371 26891626',NULL,NULL,0,NULL,NULL,'Mehāniķis','Aktīvs','2025-08-04 10:45:09','2025-08-26 09:30:44'),
(10,'evele1','$2y$10$pbB.jlmxD8T3fvOmxxUd.e3FthHKDTO0HXnR6PwYLw3NugiBz6H5.','1','ēvele',NULL,NULL,'+37126891626',NULL,0,2,65,'Operators','Aktīvs','2025-08-05 12:37:10','2025-08-19 10:07:01'),
(11,'mareks','$2y$10$02vI7cYn/JJBPEYbORHv4OFU6/DXFT8zNad6HAW40e0bbWloTmwNS','Mareks','MELNGAILIS','its@avoti.lv',NULL,'Marchoquattro',495733527,1,NULL,NULL,'Mehāniķis','Aktīvs','2025-08-18 08:02:11','2025-08-26 09:04:40');
/*!40000 ALTER TABLE `lietotaji` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_tracking`
--

DROP TABLE IF EXISTS `notification_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(50) NOT NULL,
  `lietotaja_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `status` enum('sent','delivered','clicked','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_id` (`tracking_id`),
  KEY `lietotaja_id` (`lietotaja_id`),
  CONSTRAINT `notification_tracking_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_tracking`
--

LOCK TABLES `notification_tracking` WRITE;
/*!40000 ALTER TABLE `notification_tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pazinojumi`
--

DROP TABLE IF EXISTS `pazinojumi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pazinojumi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotaja_id` int(11) NOT NULL,
  `virsraksts` varchar(200) NOT NULL,
  `zinojums` text NOT NULL,
  `tips` enum('Jauns uzdevums','Jauna problēma','Statusa maiņa','Sistēmas') NOT NULL,
  `skatīts` tinyint(1) DEFAULT 0,
  `saistitas_tips` enum('Uzdevums','Problēma') DEFAULT NULL,
  `saistitas_id` int(11) DEFAULT NULL,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pazinojumi_lietotaja_skatīts` (`lietotaja_id`,`skatīts`),
  KEY `idx_pazinojumi_lietotaja_skatits` (`lietotaja_id`,`skatīts`),
  CONSTRAINT `pazinojumi_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=594 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pazinojumi`
--

LOCK TABLES `pazinojumi` WRITE;
/*!40000 ALTER TABLE `pazinojumi` DISABLE KEYS */;
INSERT INTO `pazinojumi` VALUES
(2,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Uzdevums pēterim','Jauns uzdevums',1,'Uzdevums',3,'2025-07-15 10:46:59'),
(7,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Neiet ēvele','Jauns uzdevums',1,'Uzdevums',4,'2025-07-15 11:31:54'),
(8,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Griezējmašīna M1-001 dara troksni','Jauns uzdevums',1,'Uzdevums',5,'2025-07-15 11:32:24'),
(11,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Griezējmašīna M1-001 dara troksni','Statusa maiņa',0,'Uzdevums',5,'2025-07-15 11:40:05'),
(12,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Asināt asmeņus','Jauns uzdevums',1,'Uzdevums',6,'2025-07-15 11:56:05'),
(15,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Neiet ēvele','Statusa maiņa',0,'Uzdevums',4,'2025-07-15 12:01:57'),
(16,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Nedēļas tīrīšana M2','Jauns uzdevums',1,'Uzdevums',8,'2025-07-16 09:43:44'),
(17,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Mēneša drošības pārbaude','Jauns uzdevums',1,'Uzdevums',9,'2025-07-16 09:43:52'),
(18,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Ceturkšņa kalibrēšana','Jauns uzdevums',1,'Uzdevums',10,'2025-07-16 09:43:55'),
(19,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Uzpīpēt','Jauns uzdevums',1,'Uzdevums',11,'2025-07-16 09:45:09'),
(22,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Ceturkšņa kalibrēšana','Statusa maiņa',0,'Uzdevums',10,'2025-07-16 09:46:39'),
(25,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Mēneša drošības pārbaude','Statusa maiņa',0,'Uzdevums',9,'2025-07-16 09:46:50'),
(28,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Uzpīpēt','Statusa maiņa',0,'Uzdevums',11,'2025-07-16 10:15:06'),
(31,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Asināt asmeņus','Statusa maiņa',0,'Uzdevums',6,'2025-07-16 10:15:13'),
(34,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 15.07.2025','Statusa maiņa',0,'Uzdevums',2,'2025-07-16 10:15:17'),
(37,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 16.07.2025','Statusa maiņa',0,'Uzdevums',7,'2025-07-16 10:15:28'),
(40,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet COSTA','Jauna problēma',0,'Problēma',3,'2025-07-16 11:25:11'),
(41,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Neiet COSTA','Jauns uzdevums',1,'Uzdevums',12,'2025-07-16 11:26:13'),
(44,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Neiet COSTA','Statusa maiņa',0,'Uzdevums',12,'2025-07-16 11:27:08'),
(46,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Skrūvēt lampas','Jauns uzdevums',1,'Uzdevums',42,'2025-07-21 05:31:57'),
(47,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet uz WC','Jauns uzdevums',1,'Uzdevums',43,'2025-07-21 05:32:05'),
(48,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet uz WC','Jauns uzdevums',1,'Uzdevums',44,'2025-07-21 05:32:12'),
(49,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Uzpīpēt','Jauns uzdevums',1,'Uzdevums',45,'2025-07-21 05:32:20'),
(50,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iekārtu ikdienas pārbaude M1','Jauns uzdevums',1,'Uzdevums',46,'2025-07-21 05:32:29'),
(53,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: Uzdevums pēterim','Statusa maiņa',0,'Uzdevums',41,'2025-07-21 05:34:57'),
(55,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Mēneša drošības pārbaude','Jauns uzdevums',1,'Uzdevums',48,'2025-07-21 05:54:38'),
(58,7,'Regulārais uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis regulāro uzdevumu: Mēneša drošības pārbaude','Statusa maiņa',0,'Uzdevums',48,'2025-07-21 09:49:13'),
(61,7,'Regulārais uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis regulāro uzdevumu: Iet uz WC','Statusa maiņa',0,'Uzdevums',44,'2025-07-21 09:49:18'),
(64,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',4,'2025-07-21 11:58:09'),
(66,6,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-07-22 05:21:42'),
(67,6,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-07-22 05:21:44'),
(68,6,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-07-22 05:21:46'),
(70,5,'Testa paziņojums','Šis ir testa paziņojums no cron_scheduler.php - 2025-07-29 13:25:45','Sistēmas',1,NULL,NULL,'2025-07-29 10:25:45'),
(71,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet uz WC','Jauns uzdevums',1,'Uzdevums',59,'2025-07-30 08:02:56'),
(75,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',5,'2025-08-01 04:23:46'),
(81,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: Tests pikties','Statusa maiņa',0,'Uzdevums',61,'2025-08-01 04:29:24'),
(85,7,'Regulārais uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: Iet uz WC - 01.08.2025','Statusa maiņa',0,'Uzdevums',65,'2025-08-01 11:07:21'),
(88,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: Tests push','Statusa maiņa',0,'Uzdevums',66,'2025-08-01 11:07:28'),
(93,7,'Regulārais uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iet uz WC - 02.08.2025','Statusa maiņa',0,'Uzdevums',69,'2025-08-04 04:53:07'),
(94,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: 04.augusts','Jauns uzdevums',1,'Uzdevums',71,'2025-08-04 05:28:12'),
(97,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: 04.augusts','Statusa maiņa',0,'Uzdevums',71,'2025-08-04 05:56:28'),
(101,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: Fuksism pirmdien','Statusa maiņa',0,'Uzdevums',72,'2025-08-04 06:46:21'),
(105,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet pirmdien nekas','Jauna problēma',0,'Problēma',6,'2025-08-04 08:17:40'),
(113,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',8,'2025-08-04 09:59:38'),
(118,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Tests Aizupam','Statusa maiņa',0,'Uzdevums',77,'2025-08-04 11:25:20'),
(120,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet pusdienās','Jauns uzdevums',1,'Uzdevums',79,'2025-08-05 08:07:06'),
(121,5,'Testa paziņojums','Šis ir testa paziņojums no cron_scheduler.php - 2025-08-05 11:08:18','Sistēmas',1,NULL,NULL,'2025-08-05 08:08:18'),
(130,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',10,'2025-08-05 11:22:18'),
(132,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Neiet KOCH','Jauns uzdevums',1,'Uzdevums',81,'2025-08-05 12:20:13'),
(138,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',12,'2025-08-06 05:48:58'),
(141,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Nazis neass','Jauna problēma',0,'Problēma',13,'2025-08-06 05:49:55'),
(143,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet pusdienās','Jauns uzdevums',1,'Uzdevums',83,'2025-08-06 09:15:18'),
(149,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Nazis neass','Statusa maiņa',0,'Uzdevums',84,'2025-08-07 08:07:23'),
(152,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Neiet ēvele','Statusa maiņa',0,'Uzdevums',85,'2025-08-07 08:07:33'),
(158,7,'Regulārais uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Iet pusdienās','Statusa maiņa',0,'Uzdevums',86,'2025-08-07 10:02:25'),
(161,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Neiet KOCH','Statusa maiņa',0,'Uzdevums',88,'2025-08-07 10:02:31'),
(165,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Iet uz WC','Statusa maiņa',0,'Uzdevums',89,'2025-08-07 10:06:42'),
(168,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: tests zinojumiem','Statusa maiņa',0,'Uzdevums',90,'2025-08-07 10:18:57'),
(172,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Nestrādā kamera','Jauna problēma',0,'Problēma',14,'2025-08-07 10:27:31'),
(181,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',17,'2025-08-07 10:42:03'),
(185,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',18,'2025-08-07 10:47:45'),
(188,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',19,'2025-08-07 10:47:50'),
(191,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',20,'2025-08-07 10:48:32'),
(194,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',21,'2025-08-07 10:48:36'),
(197,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: ddf','Jauna problēma',0,'Problēma',22,'2025-08-07 10:48:47'),
(200,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: ddf','Jauna problēma',0,'Problēma',23,'2025-08-07 10:50:26'),
(203,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: ddf','Jauna problēma',0,'Problēma',24,'2025-08-07 10:53:30'),
(207,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Tests ppushiem','Statusa maiņa',0,'Uzdevums',92,'2025-08-07 10:56:50'),
(210,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Nestrādā kamera','Statusa maiņa',0,'Uzdevums',93,'2025-08-07 10:56:54'),
(213,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Tests pushiem veeel','Jauna problēma',0,'Problēma',25,'2025-08-07 11:10:03'),
(217,7,'Regulārais uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iet pusdienās','Statusa maiņa',0,'Uzdevums',83,'2025-08-07 11:18:46'),
(220,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Annas Problāma','Jauna problēma',0,'Problēma',26,'2025-08-07 11:24:05'),
(225,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: tests','Statusa maiņa',0,'Uzdevums',96,'2025-08-07 12:01:13'),
(228,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Tests','Jauna problēma',0,'Problēma',27,'2025-08-07 12:10:48'),
(231,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Vēl.pr','Jauna problēma',0,'Problēma',28,'2025-08-07 12:18:59'),
(237,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Apstājās ēvele','Jauna problēma',0,'Problēma',29,'2025-08-08 04:48:02'),
(240,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Apstājās ēvele nr2','Jauna problēma',0,'Problēma',30,'2025-08-08 04:57:34'),
(243,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Vēl.pr','Statusa maiņa',0,'Uzdevums',98,'2025-08-08 04:58:04'),
(246,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Tests','Statusa maiņa',0,'Uzdevums',97,'2025-08-08 04:58:10'),
(250,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Apstājās ēvele nr3','Jauna problēma',0,'Problēma',31,'2025-08-08 05:10:14'),
(253,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Apstājās ēvele nr3','Jauna problēma',0,'Problēma',32,'2025-08-08 05:10:42'),
(254,10,'Problēma dzēsta','Jūsu ziņotā problēma ir dzēsta','Sistēmas',1,NULL,NULL,'2025-08-08 05:12:23'),
(255,10,'Problēma dzēsta','Jūsu ziņotā problēma ir dzēsta','Sistēmas',1,NULL,NULL,'2025-08-08 05:12:27'),
(258,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Apstājās ēvele nr4','Jauna problēma',0,'Problēma',33,'2025-08-08 05:39:22'),
(262,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',34,'2025-08-08 08:35:35'),
(263,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet pusdienās','Jauns uzdevums',1,'Uzdevums',106,'2025-08-09 08:00:01'),
(264,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Iet pusdienās','Jauns uzdevums',1,'Uzdevums',107,'2025-08-10 08:00:01'),
(266,5,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-08-11 11:19:30'),
(267,6,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-08-11 11:19:33'),
(282,7,'Uzdevums pabeigts','Mehāniķis 9 ir pabeidzis savu daļu uzdevumā: 4.tests kopējam','Statusa maiņa',0,'Uzdevums',113,'2025-08-11 12:09:15'),
(285,7,'Uzdevums pabeigts','Mehāniķis 9 ir pabeidzis savu daļu uzdevumā: 4.tests kopējam','Statusa maiņa',0,'Uzdevums',113,'2025-08-11 12:09:19'),
(290,7,'Uzdevums pabeigts','Mehāniķis 9 ir pabeidzis savu daļu uzdevumā: 5.tests kopejam','Statusa maiņa',0,'Uzdevums',114,'2025-08-11 12:21:57'),
(296,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: testss 6 abiem','Statusa maiņa',0,'Uzdevums',116,'2025-08-11 12:31:38'),
(299,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: testss 6 abiem','Statusa maiņa',0,'Uzdevums',116,'2025-08-11 12:32:00'),
(304,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: kopā otrdien','Statusa maiņa',0,'Uzdevums',117,'2025-08-12 05:07:28'),
(307,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: kopā otrdien','Statusa maiņa',0,'Uzdevums',117,'2025-08-12 05:09:27'),
(312,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: Ordiena nr2.kopā','Statusa maiņa',0,'Uzdevums',118,'2025-08-12 05:19:54'),
(315,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: Ordiena nr2.kopā','Statusa maiņa',0,'Uzdevums',118,'2025-08-12 05:20:18'),
(320,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: otrdiena nr3','Statusa maiņa',0,'Uzdevums',119,'2025-08-12 05:36:59'),
(323,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: otrdiena nr3','Statusa maiņa',0,'Uzdevums',119,'2025-08-12 06:07:57'),
(330,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: otrdiena Nr4','Statusa maiņa',0,'Uzdevums',121,'2025-08-12 06:31:23'),
(334,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: otrdiena Nr4','Statusa maiņa',0,'Uzdevums',121,'2025-08-12 06:43:57'),
(340,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: Grupu tresdien','Statusa maiņa',0,'Uzdevums',125,'2025-08-13 10:24:18'),
(343,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: Grupu tresdien','Statusa maiņa',0,'Uzdevums',125,'2025-08-13 10:26:56'),
(351,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: dedeewewee','Statusa maiņa',0,'Uzdevums',130,'2025-08-13 12:20:56'),
(356,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: grupas','Statusa maiņa',0,'Uzdevums',131,'2025-08-13 12:22:41'),
(359,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: grupas','Statusa maiņa',0,'Uzdevums',131,'2025-08-13 12:23:21'),
(364,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: grupa hsjdjsd','Statusa maiņa',0,'Uzdevums',132,'2025-08-13 12:29:50'),
(367,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: grupa hsjdjsd','Statusa maiņa',0,'Uzdevums',132,'2025-08-13 12:31:12'),
(372,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: grupa hsjdjsdwqwqwqwqwqww','Statusa maiņa',0,'Uzdevums',133,'2025-08-13 12:32:29'),
(375,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: grupa hsjdjsdwqwqwqwqwqww','Statusa maiņa',0,'Uzdevums',133,'2025-08-13 12:33:59'),
(380,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: vlek grupai','Statusa maiņa',0,'Uzdevums',134,'2025-08-13 12:35:14'),
(383,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: vlek grupai','Statusa maiņa',0,'Uzdevums',134,'2025-08-13 12:41:57'),
(388,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis savu daļu uzdevumā: vlek grupaiefeerr','Statusa maiņa',0,'Uzdevums',135,'2025-08-13 12:42:57'),
(391,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: vlek grupaiefeerr','Statusa maiņa',0,'Uzdevums',135,'2025-08-13 12:43:04'),
(395,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: gfggfgfgfg','Statusa maiņa',0,'Uzdevums',136,'2025-08-13 12:43:28'),
(396,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Nedēļas tīrīšana M2','Jauns uzdevums',1,'Uzdevums',137,'2025-08-13 13:00:01'),
(414,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis uzdevumu: Tests Telegrammam','Statusa maiņa',0,'Uzdevums',145,'2025-08-18 11:03:23'),
(420,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis uzdevumu: Nopirkt 2 kameras Rēzeknei','Statusa maiņa',0,'Uzdevums',147,'2025-08-18 11:21:49'),
(424,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis uzdevumu: Pēdejais tests šodien TEV','Statusa maiņa',0,'Uzdevums',148,'2025-08-18 13:43:55'),
(427,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',35,'2025-08-19 04:11:36'),
(430,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',36,'2025-08-19 04:17:18'),
(433,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',37,'2025-08-19 04:21:01'),
(436,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',37,'2025-08-19 04:21:01'),
(439,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',38,'2025-08-19 04:31:36'),
(442,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',39,'2025-08-19 04:40:17'),
(445,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',40,'2025-08-19 04:40:23'),
(448,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',40,'2025-08-19 04:40:24'),
(452,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: Neiet OMĀLS','Statusa maiņa',0,'Uzdevums',149,'2025-08-19 04:43:38'),
(455,7,'Regulārais uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Nedēļas tīrīšana M2','Statusa maiņa',0,'Uzdevums',137,'2025-08-19 04:44:31'),
(458,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: neiet frēze','Jauna problēma',0,'Problēma',41,'2025-08-19 05:03:01'),
(461,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: neiet frēze','Jauna problēma',0,'Problēma',41,'2025-08-19 05:03:01'),
(466,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: neoet costa','Jauna problēma',0,'Problēma',42,'2025-08-19 05:37:39'),
(469,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: neoet costa','Jauna problēma',0,'Problēma',42,'2025-08-19 05:37:39'),
(473,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',43,'2025-08-19 05:52:44'),
(476,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH','Jauna problēma',0,'Problēma',43,'2025-08-19 05:52:45'),
(480,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',44,'2025-08-19 06:45:25'),
(484,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: Neiet OMĀLS. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',1,'Uzdevums',153,'2025-08-19 06:45:25'),
(487,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet OMĀLS','Jauna problēma',0,'Problēma',44,'2025-08-19 06:45:26'),
(490,7,'Uzdevums pabeigts','Mehāniķis Aivars Pētersons ir pabeidzis uzdevumu: 🚨 KRITISKS: Neiet OMĀLS','Statusa maiņa',0,'Uzdevums',150,'2025-08-19 06:46:58'),
(493,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: E&#039;vele neiet','Jauna problēma',0,'Problēma',45,'2025-08-19 07:28:10'),
(497,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: E&#039;vele neiet. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',1,'Uzdevums',157,'2025-08-19 07:28:12'),
(500,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: E&#039;vele neiet','Jauna problēma',0,'Problēma',45,'2025-08-19 07:28:13'),
(503,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis uzdevumu: 🚨 KRITISKS: E&#039;vele neiet','Statusa maiņa',0,'Uzdevums',156,'2025-08-19 07:30:11'),
(506,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH ndbjdbksdjs','Jauna problēma',0,'Problēma',46,'2025-08-19 07:38:13'),
(510,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: Neiet KOCH ndbjdbksdjs. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',1,'Uzdevums',161,'2025-08-19 07:38:13'),
(513,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet KOCH ndbjdbksdjs','Jauna problēma',0,'Problēma',46,'2025-08-19 07:38:14'),
(514,5,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',1,NULL,NULL,'2025-08-19 07:39:06'),
(517,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis uzdevumu: 🚨 KRITISKS: Neiet KOCH ndbjdbksdjs','Statusa maiņa',0,'Uzdevums',160,'2025-08-19 07:41:17'),
(520,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: ēvele neiet','Jauna problēma',0,'Problēma',47,'2025-08-19 08:52:29'),
(524,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: ēvele neiet. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',1,'Uzdevums',165,'2025-08-19 08:52:31'),
(527,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: ēvele neiet','Jauna problēma',0,'Problēma',47,'2025-08-19 08:52:31'),
(530,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: 🚨 KRITISKS: ēvele neiet','Statusa maiņa',0,'Uzdevums',163,'2025-08-19 08:54:59'),
(535,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis savu daļu uzdevumā: Iet pusdienās','Statusa maiņa',0,'Uzdevums',166,'2025-08-19 09:32:07'),
(538,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neoet HONAG','Jauna problēma',0,'Problēma',48,'2025-08-19 09:43:55'),
(542,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: Neoet HONAG. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',0,'Uzdevums',170,'2025-08-19 09:43:56'),
(545,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neoet HONAG','Jauna problēma',0,'Problēma',48,'2025-08-19 09:43:56'),
(548,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: 🚨 KRITISKS: Neoet HONAG','Statusa maiņa',0,'Uzdevums',168,'2025-08-19 09:44:52'),
(551,7,'Uzdevums pabeigts','Mehāniķis Mareks MELNGAILIS ir pabeidzis savu daļu uzdevumā: Iet pusdienās','Statusa maiņa',0,'Uzdevums',166,'2025-08-19 09:48:07'),
(555,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',49,'2025-08-19 09:53:51'),
(559,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: Neiet ēvele. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',0,'Uzdevums',175,'2025-08-19 09:53:51'),
(562,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',0,'Problēma',49,'2025-08-19 09:53:52'),
(565,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: kristisks','Statusa maiņa',0,'Uzdevums',171,'2025-08-19 09:57:56'),
(568,7,'Uzdevums pabeigts','Mehāniķis Jānis Aizupietis ir pabeidzis uzdevumu: 🚨 KRITISKS: Neiet ēvele','Statusa maiņa',0,'Uzdevums',173,'2025-08-19 09:58:02'),
(571,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: dhuscdsfyufyd','Jauna problēma',0,'Problēma',50,'2025-08-19 10:02:51'),
(575,5,'🚨 KRITISKS UZDEVUMS!','Jums piešķirts kritisks uzdevums: dhuscdsfyufyd. TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.','Jauns uzdevums',0,'Uzdevums',179,'2025-08-19 10:02:52'),
(578,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: dhuscdsfyufyd','Jauna problēma',0,'Problēma',50,'2025-08-19 10:02:52'),
(581,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: parahsdjsdhs','Jauna problēma',0,'Problēma',51,'2025-08-19 10:07:14'),
(584,7,'Jauna problēma ziņota','Operators 1 ēvele ir ziņojis jaunu problēmu: parahsdjsdhs','Jauna problēma',0,'Problēma',51,'2025-08-19 10:07:14'),
(586,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Nedēļas tīrīšana M2','Jauns uzdevums',0,'Uzdevums',181,'2025-08-20 13:00:01'),
(588,5,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',0,NULL,NULL,'2025-08-21 06:46:13'),
(592,5,'Uzdevums dzēsts','Jums piešķirtais uzdevums ir dzēsts','Sistēmas',0,NULL,NULL,'2025-08-21 06:46:25'),
(593,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Nedēļas tīrīšana M2','Jauns uzdevums',0,'Uzdevums',182,'2025-08-27 13:00:01');
/*!40000 ALTER TABLE `pazinojumi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `problemas`
--

DROP TABLE IF EXISTS `problemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `problemas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(200) NOT NULL,
  `apraksts` text NOT NULL,
  `vietas_id` int(11) DEFAULT NULL,
  `iekartas_id` int(11) DEFAULT NULL,
  `prioritate` enum('Zema','Vidēja','Augsta','Kritiska') DEFAULT 'Vidēja',
  `sarezgitibas_pakape` enum('Vienkārša','Vidēja','Sarežģīta','Ļoti sarežģīta') DEFAULT 'Vidēja',
  `aptuvenais_ilgums` decimal(5,2) DEFAULT NULL COMMENT 'Stundas',
  `statuss` enum('Jauna','Apskatīta','Pārvērsta uzdevumā','Atcelta') DEFAULT 'Jauna',
  `zinotajs_id` int(11) NOT NULL,
  `apstradasija_id` int(11) DEFAULT NULL,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  `atjaunots` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vietas_id` (`vietas_id`),
  KEY `iekartas_id` (`iekartas_id`),
  KEY `zinotajs_id` (`zinotajs_id`),
  KEY `apstradasija_id` (`apstradasija_id`),
  KEY `idx_problemas_prioritate_statuss` (`prioritate`,`statuss`),
  CONSTRAINT `problemas_ibfk_1` FOREIGN KEY (`vietas_id`) REFERENCES `vietas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `problemas_ibfk_2` FOREIGN KEY (`iekartas_id`) REFERENCES `iekartas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `problemas_ibfk_3` FOREIGN KEY (`zinotajs_id`) REFERENCES `lietotaji` (`id`),
  CONSTRAINT `problemas_ibfk_4` FOREIGN KEY (`apstradasija_id`) REFERENCES `lietotaji` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `problemas`
--

LOCK TABLES `problemas` WRITE;
/*!40000 ALTER TABLE `problemas` DISABLE KEYS */;
INSERT INTO `problemas` VALUES
(38,'Neiet OMĀLS','PC beigts laikam',2,80,'Kritiska','Vidēja',NULL,'Pārvērsta uzdevumā',4,3,'2025-08-19 04:31:36','2025-08-19 04:43:19');
/*!40000 ALTER TABLE `problemas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotaja_id` int(11) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh_key` varchar(255) NOT NULL,
  `auth_key` varchar(255) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subscription` (`lietotaja_id`,`endpoint`(255)),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_subscriptions`
--

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `regularo_uzdevumu_sabloni`
--

DROP TABLE IF EXISTS `regularo_uzdevumu_sabloni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regularo_uzdevumu_sabloni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(200) NOT NULL,
  `apraksts` text DEFAULT NULL,
  `vietas_id` int(11) DEFAULT NULL,
  `iekartas_id` int(11) DEFAULT NULL,
  `kategorijas_id` int(11) DEFAULT NULL,
  `prioritate` enum('Zema','Vidēja','Augsta','Kritiska') DEFAULT 'Vidēja',
  `paredzamais_ilgums` decimal(5,2) DEFAULT NULL COMMENT 'Stundas',
  `periodicitate` enum('Katru dienu','Katru nedēļu','Reizi mēnesī','Reizi ceturksnī','Reizi gadā') NOT NULL,
  `periodicitas_dienas` longtext DEFAULT NULL COMMENT 'Nedēļas dienas vai mēneša dienas' CHECK (json_valid(`periodicitas_dienas`)),
  `laiks` time DEFAULT NULL COMMENT 'Kad izveidot uzdevumu',
  `aktīvs` tinyint(1) DEFAULT 1,
  `izveidoja_id` int(11) NOT NULL,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  `atjaunots` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vietas_id` (`vietas_id`),
  KEY `iekartas_id` (`iekartas_id`),
  KEY `kategorijas_id` (`kategorijas_id`),
  KEY `izveidoja_id` (`izveidoja_id`),
  CONSTRAINT `regularo_uzdevumu_sabloni_ibfk_1` FOREIGN KEY (`vietas_id`) REFERENCES `vietas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regularo_uzdevumu_sabloni_ibfk_2` FOREIGN KEY (`iekartas_id`) REFERENCES `iekartas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regularo_uzdevumu_sabloni_ibfk_3` FOREIGN KEY (`kategorijas_id`) REFERENCES `uzdevumu_kategorijas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regularo_uzdevumu_sabloni_ibfk_4` FOREIGN KEY (`izveidoja_id`) REFERENCES `lietotaji` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `regularo_uzdevumu_sabloni`
--

LOCK TABLES `regularo_uzdevumu_sabloni` WRITE;
/*!40000 ALTER TABLE `regularo_uzdevumu_sabloni` DISABLE KEYS */;
INSERT INTO `regularo_uzdevumu_sabloni` VALUES
(2,'Nedēļas tīrīšana M2','Nedēļas generāltīrīšana M2 cehā',2,NULL,4,'Vidēja',NULL,'Katru nedēļu','[\"3\"]','16:00:00',1,1,'2025-07-15 09:37:55','2025-07-21 09:43:24'),
(3,'Mēneša drošības pārbaude','Mēneša darba drošības pārbaude visos cehos',NULL,NULL,5,'Augsta',NULL,'Reizi mēnesī',NULL,'09:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(4,'Ceturkšņa kalibrēšana','Ceturkšņa instrumentu kalibrēšana',NULL,NULL,7,'Augsta',NULL,'Reizi ceturksnī',NULL,'10:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(9,'Iet pusdienās','LAi būtu spēks',NULL,NULL,NULL,'Vidēja',0.50,'Katru dienu',NULL,'11:00:00',0,1,'2025-08-05 04:19:14','2025-08-11 11:17:13');
/*!40000 ALTER TABLE `regularo_uzdevumu_sabloni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_users`
--

DROP TABLE IF EXISTS `telegram_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lietotaja_id` int(11) NOT NULL,
  `telegram_chat_id` varchar(50) NOT NULL,
  `telegram_username` varchar(100) DEFAULT NULL,
  `telegram_first_name` varchar(100) DEFAULT NULL,
  `telegram_last_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `registered_at` timestamp NULL DEFAULT current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_chat` (`lietotaja_id`,`telegram_chat_id`),
  KEY `idx_lietotaja_id` (`lietotaja_id`),
  KEY `idx_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_users`
--

LOCK TABLES `telegram_users` WRITE;
/*!40000 ALTER TABLE `telegram_users` DISABLE KEYS */;
INSERT INTO `telegram_users` VALUES
(1,1,'JŪSU_TELEGRAM_CHAT_ID','JŪSU_TELEGRAM_USERNAME','Admin','User',1,'2025-08-18 06:51:35',NULL),
(4,1,'7486360988','aizups','Janis','A',1,'2025-08-18 10:51:07','2025-08-19 10:07:14'),
(9,11,'495733527','Marchoquattro','Mareks','Melli',1,'2025-08-18 11:05:21','2025-08-19 10:02:52');
/*!40000 ALTER TABLE `telegram_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uzdevumi`
--

DROP TABLE IF EXISTS `uzdevumi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uzdevumi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(200) NOT NULL,
  `apraksts` text DEFAULT NULL,
  `veids` enum('Ikdienas','Regulārais') NOT NULL,
  `vietas_id` int(11) DEFAULT NULL,
  `iekartas_id` int(11) DEFAULT NULL,
  `kategorijas_id` int(11) DEFAULT NULL,
  `prioritate` enum('Zema','Vidēja','Augsta','Kritiska') DEFAULT 'Vidēja',
  `statuss` enum('Jauns','Procesā','Pabeigts','Atcelts','Atlikts') DEFAULT 'Jauns',
  `piešķirts_id` int(11) NOT NULL COMMENT 'Mehāniķis',
  `daudziem_mehāniķiem` tinyint(1) DEFAULT 0,
  `izveidoja_id` int(11) NOT NULL,
  `sakuma_datums` timestamp NULL DEFAULT current_timestamp(),
  `jabeidz_lidz` timestamp NULL DEFAULT NULL,
  `paredzamais_ilgums` decimal(5,2) DEFAULT NULL COMMENT 'Stundas',
  `faktiskais_ilgums` decimal(5,2) DEFAULT NULL COMMENT 'Stundas',
  `sakuma_laiks` timestamp NULL DEFAULT NULL,
  `beigu_laiks` timestamp NULL DEFAULT NULL,
  `problemas_id` int(11) DEFAULT NULL COMMENT 'Ja uzdevums izveidots no problēmas',
  `regulara_uzdevuma_id` int(11) DEFAULT NULL COMMENT 'Ja regulārs uzdevums',
  `atbildes_komentars` text DEFAULT NULL,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  `atjaunots` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vietas_id` (`vietas_id`),
  KEY `iekartas_id` (`iekartas_id`),
  KEY `kategorijas_id` (`kategorijas_id`),
  KEY `izveidoja_id` (`izveidoja_id`),
  KEY `problemas_id` (`problemas_id`),
  KEY `regulara_uzdevuma_id` (`regulara_uzdevuma_id`),
  KEY `idx_uzdevumi_prioritate_statuss` (`prioritate`,`statuss`),
  KEY `idx_uzdevumi_piešķirts_statuss` (`piešķirts_id`,`statuss`),
  CONSTRAINT `uzdevumi_ibfk_1` FOREIGN KEY (`vietas_id`) REFERENCES `vietas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uzdevumi_ibfk_2` FOREIGN KEY (`iekartas_id`) REFERENCES `iekartas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uzdevumi_ibfk_3` FOREIGN KEY (`kategorijas_id`) REFERENCES `uzdevumu_kategorijas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uzdevumi_ibfk_4` FOREIGN KEY (`piešķirts_id`) REFERENCES `lietotaji` (`id`),
  CONSTRAINT `uzdevumi_ibfk_5` FOREIGN KEY (`izveidoja_id`) REFERENCES `lietotaji` (`id`),
  CONSTRAINT `uzdevumi_ibfk_6` FOREIGN KEY (`problemas_id`) REFERENCES `problemas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uzdevumi_ibfk_7` FOREIGN KEY (`regulara_uzdevuma_id`) REFERENCES `regularo_uzdevumu_sabloni` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumi`
--

LOCK TABLES `uzdevumi` WRITE;
/*!40000 ALTER TABLE `uzdevumi` DISABLE KEYS */;
INSERT INTO `uzdevumi` VALUES
(147,'Nopirkt 2 kameras Rēzeknei','Vēl testējam.............','Ikdienas',30,NULL,5,'Vidēja','Pabeigts',11,0,1,'2025-08-18 11:14:50','2025-08-21 11:14:00',NULL,0.00,'2025-08-18 11:21:19','2025-08-18 11:21:48',NULL,NULL,NULL,'2025-08-18 11:14:50','2025-08-18 11:21:48'),
(149,'Neiet OMĀLS','PC beigts laikam','Ikdienas',2,80,NULL,'Kritiska','Pabeigts',9,0,3,'2025-08-19 04:43:19',NULL,NULL,2.00,'2025-08-19 04:43:30','2025-08-19 04:43:37',38,NULL,'','2025-08-19 04:43:19','2025-08-19 04:43:37'),
(182,'Nedēļas tīrīšana M2','Nedēļas generāltīrīšana M2 cehā','Regulārais',2,NULL,4,'Vidēja','Jauns',5,0,1,'2025-08-27 13:00:01',NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,'2025-08-27 13:00:01','2025-08-27 13:00:01');
/*!40000 ALTER TABLE `uzdevumi` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`adminer`@`localhost`*/ /*!50003 TRIGGER tr_uzdevums_statusa_maiņa
AFTER UPDATE ON uzdevumi
FOR EACH ROW
BEGIN
    IF OLD.statuss != NEW.statuss THEN
        INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, mainīja_id)
        VALUES (NEW.id, OLD.statuss, NEW.statuss, NEW.piešķirts_id);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `uzdevumu_kategorijas`
--

DROP TABLE IF EXISTS `uzdevumu_kategorijas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uzdevumu_kategorijas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(100) NOT NULL,
  `apraksts` text DEFAULT NULL,
  `aktīvs` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumu_kategorijas`
--

LOCK TABLES `uzdevumu_kategorijas` WRITE;
/*!40000 ALTER TABLE `uzdevumu_kategorijas` DISABLE KEYS */;
INSERT INTO `uzdevumu_kategorijas` VALUES
(1,'Apkope','Regulārā iekārtu apkope',1),
(2,'Remonts','Iekārtu remonti',1),
(3,'Tīrīšana','Darbavietu un iekārtu tīrīšana',1),
(4,'Pārbaude','Plānveida pārbaudes',1),
(5,'Uzstādīšana','Jaunu iekārtu uzstādīšana',1),
(6,'Kalibrēšana','Instrumentu kalibrēšana',1),
(7,'Drošība','Darba drošības pasākumi',1),
(8,'Profilaktiskā apkope','Regulārā iekārtu profilaktiskā apkope un pārbaude',1),
(9,'Plānveida remonts','Plānveida iekārtu remonti un nomaiņa',1),
(10,'Avārijas remonts','Steidzami avārijas remonti un traucējumu novēršana',1),
(15,'Modernizācija-Projekti','Iekārtu modernizācija un uzlabošana',1),
(16,'Dokumentācija','Dokumentācijas sagatavošana un atjaunošana',1),
(17,'Apmācība','Personāla apmācība un instruktāža',1);
/*!40000 ALTER TABLE `uzdevumu_kategorijas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uzdevumu_piešķīrumi`
--

DROP TABLE IF EXISTS `uzdevumu_piešķīrumi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uzdevumu_piešķīrumi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzdevuma_id` int(11) NOT NULL,
  `mehāniķa_id` int(11) NOT NULL,
  `piešķirts` timestamp NULL DEFAULT current_timestamp(),
  `statuss` enum('Piešķirts','Sākts','Pabeigts','Noņemts') DEFAULT 'Piešķirts',
  `sākts` timestamp NULL DEFAULT NULL,
  `pabeigts` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`uzdevuma_id`,`mehāniķa_id`),
  KEY `idx_uzdevumu_piešķīrumi_uzdevuma_id` (`uzdevuma_id`),
  KEY `idx_uzdevumu_piešķīrumi_mehāniķa_id` (`mehāniķa_id`),
  KEY `idx_uzdevumu_piešķīrumi_statuss` (`statuss`),
  CONSTRAINT `uzdevumu_piešķīrumi_ibfk_1` FOREIGN KEY (`uzdevuma_id`) REFERENCES `uzdevumi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `uzdevumu_piešķīrumi_ibfk_2` FOREIGN KEY (`mehāniķa_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumu_piešķīrumi`
--

LOCK TABLES `uzdevumu_piešķīrumi` WRITE;
/*!40000 ALTER TABLE `uzdevumu_piešķīrumi` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzdevumu_piešķīrumi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uzdevumu_vesture`
--

DROP TABLE IF EXISTS `uzdevumu_vesture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uzdevumu_vesture` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzdevuma_id` int(11) NOT NULL,
  `iepriekšējais_statuss` varchar(50) DEFAULT NULL,
  `jaunais_statuss` varchar(50) DEFAULT NULL,
  `komentars` text DEFAULT NULL,
  `mainīja_id` int(11) NOT NULL,
  `mainīts` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uzdevuma_id` (`uzdevuma_id`),
  KEY `mainīja_id` (`mainīja_id`),
  CONSTRAINT `uzdevumu_vesture_ibfk_1` FOREIGN KEY (`uzdevuma_id`) REFERENCES `uzdevumi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `uzdevumu_vesture_ibfk_2` FOREIGN KEY (`mainīja_id`) REFERENCES `lietotaji` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumu_vesture`
--

LOCK TABLES `uzdevumu_vesture` WRITE;
/*!40000 ALTER TABLE `uzdevumu_vesture` DISABLE KEYS */;
INSERT INTO `uzdevumu_vesture` VALUES
(531,147,NULL,'Jauns','Uzdevums izveidots',1,'2025-08-18 11:14:50'),
(532,147,'Jauns','Procesā',NULL,11,'2025-08-18 11:21:19'),
(533,147,'Jauns','Procesā','Darbs sākts',11,'2025-08-18 11:21:19'),
(534,147,'Procesā','Pabeigts',NULL,11,'2025-08-18 11:21:48'),
(535,147,'Procesā','Pabeigts','Uzdevums pabeigts',11,'2025-08-18 11:21:48'),
(541,149,NULL,'Jauns','Uzdevums izveidots',3,'2025-08-19 04:43:19'),
(542,149,'Jauns','Procesā',NULL,9,'2025-08-19 04:43:30'),
(543,149,'Jauns','Procesā','Darbs sākts no sākumlapas',9,'2025-08-19 04:43:30'),
(544,149,'Procesā','Pabeigts',NULL,9,'2025-08-19 04:43:37'),
(545,149,'Procesā','Pabeigts','Uzdevums pabeigts no sākumlapas. Komentārs: ',9,'2025-08-19 04:43:37'),
(584,182,NULL,'Jauns','Regulārais uzdevums izveidots automātiski',1,'2025-08-27 13:00:01');
/*!40000 ALTER TABLE `uzdevumu_vesture` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vietas`
--

DROP TABLE IF EXISTS `vietas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vietas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nosaukums` varchar(100) NOT NULL,
  `apraksts` text DEFAULT NULL,
  `aktīvs` tinyint(1) DEFAULT 1,
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vietas`
--

LOCK TABLES `vietas` WRITE;
/*!40000 ALTER TABLE `vietas` DISABLE KEYS */;
INSERT INTO `vietas` VALUES
(1,'M1 - Optimizācija','Mašīnbūves cehs Nr.1 - Optimizācija',1,'2025-07-15 06:13:35'),
(2,'M2 - Mašīnapstrāde','Mašīnbūves cehs Nr.2 - Galvenā mašīnapstrāde',1,'2025-07-15 06:13:35'),
(3,'M3 - Gultu cehs','Mašīnbūves cehs Nr.3 - Gultu cehs',1,'2025-07-15 06:13:35'),
(4,'M4 - Knagglig cehs','Mašīnbūves cehs Nr.4 - Knagglig',1,'2025-07-15 06:13:35'),
(5,'Galdniecība','Galdniecības darbnīca',1,'2025-07-15 06:13:35'),
(7,'Pakotava','Pakošanas cehs',1,'2025-07-15 06:13:35'),
(8,'Granulas','Granulu ražošana',1,'2025-07-15 06:13:35'),
(9,'Birojs','Administratīvās telpas',1,'2025-07-15 06:13:35'),
(10,'Noliktava pie Rairu','Gatavo izstrādājumu noliktava',1,'2025-07-15 06:13:35'),
(24,'Lakotava','Abās lakotavās',1,'2025-07-15 11:53:37'),
(25,'Kaltes','',1,'2025-07-15 11:53:56'),
(26,'Optimas placis','Zāģmateriālu placis',1,'2025-07-15 11:54:07'),
(29,'Mehāniskās darbnīcas','Mehu darbnīcas',1,'2025-08-05 07:54:37'),
(30,'Rēzekne','Ja kāds liels remonts (komandējums) Rēzeknē',1,'2025-08-05 07:55:43'),
(31,'Pinkas','Jaunās telpas Pinkās',1,'2025-08-05 07:58:09'),
(32,'Noliktava pie Knagglig','',1,'2025-08-05 07:58:43'),
(33,'Projekti','Darbs pie dažādiem projektiem',1,'2025-08-07 11:14:36');
/*!40000 ALTER TABLE `vietas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'mehu_uzd'
--
/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;
/*!50106 DROP EVENT IF EXISTS `ev_regulārie_uzdevumi` */;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = 'SYSTEM' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`adminer`@`localhost`*/ /*!50106 EVENT `ev_regulārie_uzdevumi` ON SCHEDULE EVERY 1 DAY STARTS '2025-07-15 12:38:15' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    
    
    INSERT INTO uzdevumi (nosaukums, apraksts, veids, kategorijas_id, prioritate, piešķirts_id, izveidoja_id, paredzamais_ilgums, regulara_uzdevuma_id)
    SELECT 
        CONCAT(s.nosaukums, ' - ', DATE_FORMAT(NOW(), '%d.%m.%Y')),
        s.apraksts,
        'Regulārais',
        s.kategorijas_id,
        s.prioritate,
        (SELECT id FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' ORDER BY RAND() LIMIT 1),
        1, 
        s.paredzamais_ilgums,
        s.id
    FROM regularo_uzdevumu_sabloni s
    WHERE s.aktīvs = 1 
    AND s.periodicitate = 'Katru dienu'
    AND TIME(NOW()) >= s.laiks
    AND NOT EXISTS (
        SELECT 1 FROM uzdevumi u 
        WHERE u.regulara_uzdevuma_id = s.id 
        AND DATE(u.izveidots) = CURDATE()
    );
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
DELIMITER ;
/*!50106 SET TIME_ZONE= @save_time_zone */ ;

--
-- Dumping routines for database 'mehu_uzd'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `create_notification` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`adminer`@`localhost` FUNCTION `create_notification`(p_lietotaja_id INT,
    p_virsraksts VARCHAR(200),
    p_zinojums TEXT,
    p_tips VARCHAR(50),
    p_saistitas_tips VARCHAR(50),
    p_saistitas_id INT
) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    INSERT INTO pazinojumi (lietotaja_id, virsraksts, zinojums, tips, saistitas_tips, saistitas_id)
    VALUES (p_lietotaja_id, p_virsraksts, p_zinojums, p_tips, p_saistitas_tips, p_saistitas_id);
    
    RETURN LAST_INSERT_ID();
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `get_user_statistics` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`adminer`@`localhost` PROCEDURE `get_user_statistics`(IN p_lietotaja_id INT, IN p_loma VARCHAR(50))
BEGIN
    IF p_loma = 'Mehāniķis' THEN
        SELECT 
            COUNT(*) as kopā_uzdevumi,
            SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
            SUM(CASE WHEN statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_uzdevumi,
            AVG(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums END) as vidējais_ilgums
        FROM uzdevumi 
        WHERE piešķirts_id = p_lietotaja_id;
        
    ELSEIF p_loma = 'Operators' THEN
        SELECT 
            COUNT(*) as kopā_problēmas,
            SUM(CASE WHEN statuss = 'Jauna' THEN 1 ELSE 0 END) as jaunas_problēmas,
            SUM(CASE WHEN statuss = 'Pārvērsta uzdevumā' THEN 1 ELSE 0 END) as pārvērstas_problēmas
        FROM problemas 
        WHERE zinotajs_id = p_lietotaja_id;
        
    ELSE
        SELECT 
            (SELECT COUNT(*) FROM uzdevumi) as kopā_uzdevumi,
            (SELECT COUNT(*) FROM problemas) as kopā_problēmas,
            (SELECT COUNT(*) FROM lietotaji WHERE statuss = 'Aktīvs') as aktīvi_lietotāji;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `aktīvie_uzdevumi`
--

/*!50001 DROP VIEW IF EXISTS `aktīvie_uzdevumi`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `aktīvie_uzdevumi` AS select `u`.`id` AS `id`,`u`.`nosaukums` AS `nosaukums`,`u`.`prioritate` AS `prioritate`,`u`.`statuss` AS `statuss`,`u`.`jabeidz_lidz` AS `jabeidz_lidz`,`u`.`izveidots` AS `izveidots`,concat(`l`.`vards`,' ',`l`.`uzvards`) AS `mehaniķis`,`v`.`nosaukums` AS `vieta`,`i`.`nosaukums` AS `iekārta` from (((`uzdevumi` `u` left join `lietotaji` `l` on(`u`.`piešķirts_id` = `l`.`id`)) left join `vietas` `v` on(`u`.`vietas_id` = `v`.`id`)) left join `iekartas` `i` on(`u`.`iekartas_id` = `i`.`id`)) where `u`.`statuss` in ('Jauns','Procesā') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `jaunās_problēmas`
--

/*!50001 DROP VIEW IF EXISTS `jaunās_problēmas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `jaunās_problēmas` AS select `p`.`id` AS `id`,`p`.`nosaukums` AS `nosaukums`,`p`.`prioritate` AS `prioritate`,`p`.`statuss` AS `statuss`,`p`.`izveidots` AS `izveidots`,concat(`l`.`vards`,' ',`l`.`uzvards`) AS `zinotājs`,`v`.`nosaukums` AS `vieta`,`i`.`nosaukums` AS `iekārta` from (((`problemas` `p` left join `lietotaji` `l` on(`p`.`zinotajs_id` = `l`.`id`)) left join `vietas` `v` on(`p`.`vietas_id` = `v`.`id`)) left join `iekartas` `i` on(`p`.`iekartas_id` = `i`.`id`)) where `p`.`statuss` = 'Jauna' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-28 10:45:05
