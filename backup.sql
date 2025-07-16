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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `darba_laiks`
--

LOCK TABLES `darba_laiks` WRITE;
/*!40000 ALTER TABLE `darba_laiks` DISABLE KEYS */;
INSERT INTO `darba_laiks` VALUES
(1,5,3,'2025-07-15 11:01:25','2025-07-15 11:01:38',0.00,NULL),
(2,6,2,'2025-07-15 11:36:33','2025-07-16 10:15:17',22.63,NULL),
(3,6,5,'2025-07-15 11:36:38','2025-07-15 11:37:15',0.00,NULL),
(4,5,4,'2025-07-15 12:01:45','2025-07-15 12:01:56',0.00,NULL),
(5,5,9,'2025-07-16 09:46:26','2025-07-16 09:46:50',0.00,NULL),
(6,5,10,'2025-07-16 09:46:31','2025-07-16 09:46:39',0.00,NULL),
(7,6,11,'2025-07-16 10:14:55','2025-07-16 10:15:06',0.00,NULL),
(8,6,6,'2025-07-16 10:15:09','2025-07-16 10:15:13',0.00,NULL),
(9,6,7,'2025-07-16 10:15:21','2025-07-16 10:15:28',0.00,NULL),
(10,6,12,'2025-07-16 11:26:57','2025-07-16 11:27:08',0.00,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faili`
--

LOCK TABLES `faili` WRITE;
/*!40000 ALTER TABLE `faili` DISABLE KEYS */;
INSERT INTO `faili` VALUES
(1,'AJM-logo.png','687631a32e58a_2025-07-15_13-46-59.png','uploads/687631a32e58a_2025-07-15_13-46-59.png','image/png',29447,'Uzdevums',3,1,'2025-07-15 10:46:59'),
(2,'Lizums.jpg','6876339c69bdd_2025-07-15_13-55-24.jpg','uploads/6876339c69bdd_2025-07-15_13-55-24.jpg','image/jpeg',49995,'Problēma',2,4,'2025-07-15 10:55:24'),
(3,'TV 3840x2160.jpg','68763c2a158dd_2025-07-15_14-31-54.jpg','uploads/68763c2a158dd_2025-07-15_14-31-54.jpg','image/jpeg',577702,'Uzdevums',4,3,'2025-07-15 11:31:54');
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
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iekartas`
--

LOCK TABLES `iekartas` WRITE;
/*!40000 ALTER TABLE `iekartas` DISABLE KEYS */;
INSERT INTO `iekartas` VALUES
(1,'Iekārta M1-001','Griezējmašīna M1 cehā',1,1,'2025-07-15 06:13:35'),
(2,'Iekārta M1-002','Virpamašīna M1 cehā',1,1,'2025-07-15 06:13:35'),
(3,'Iekārta M2-001','Frēzmašīna M2 cehā',2,1,'2025-07-15 06:13:35'),
(4,'Iekārta M2-002','Presformu M2 cehā',2,1,'2025-07-15 06:13:35'),
(5,'Iekārta G-001','Zāģmašīna galdniecībā',5,1,'2025-07-15 06:13:35'),
(6,'Iekārta G-002','Ēvelmašīna galdniecībā',5,1,'2025-07-15 06:13:35'),
(7,'Iekārta L-001','Krāsošanas kamera lakotavā',6,1,'2025-07-15 06:13:35'),
(8,'Iekārta P-001','Pakošanas līnija',7,1,'2025-07-15 06:13:35'),
(9,'Iekārta GR-001','Granulu press',8,1,'2025-07-15 06:13:35'),
(10,'Griezējmašīna M1-001','CNC griezējmašīna metāla apstrādei',1,1,'2025-07-15 09:37:54'),
(11,'Virpamašīna M1-002','Universāla virpamašīna precīzai apstrādei',1,1,'2025-07-15 09:37:54'),
(12,'Frēzmašīna M1-003','CNC frēzmašīna sarežģītiem detaļām',1,1,'2025-07-15 09:37:54'),
(13,'Kompresors M1-004','Galvenais gaisa kompresors M1 ceham',1,1,'2025-07-15 09:37:54'),
(14,'Presformu M2-001','Hidrauliskā presformu metāla veidošanai',2,1,'2025-07-15 09:37:54'),
(15,'Metināšanas stacija M2-002','Automātiskā metināšanas stacija',2,1,'2025-07-15 09:37:54'),
(16,'Celtnis M2-003','5 tonnu tilts celtnis',2,1,'2025-07-15 09:37:54'),
(17,'Ventilācijas sistēma M2-004','Centrālā ventilācijas sistēma',2,1,'2025-07-15 09:37:54'),
(18,'Speciālā mašīna M3-001','Speciāla mašīna unikāliem uzdevumiem',3,1,'2025-07-15 09:37:54'),
(19,'Testēšanas stends M3-002','Izstrādājumu kvalitātes testēšanas stends',3,1,'2025-07-15 09:37:54'),
(20,'Mērīšanas komplekss M3-003','Precīzas mērīšanas aprīkojums',3,1,'2025-07-15 09:37:54'),
(21,'Automātiskā līnija M4-001','Pilnībā automātiskā ražošanas līnija',4,1,'2025-07-15 09:37:54'),
(22,'Roboti M4-002','Industriālie roboti montāžai',4,1,'2025-07-15 09:37:54'),
(23,'Konveijers M4-003','Galvenais konveijers sistēma',4,1,'2025-07-15 09:37:54'),
(24,'Kontroles sistēma M4-004','Centrālā kontroles un uzraudzības sistēma',4,1,'2025-07-15 09:37:54'),
(25,'Zāģmašīna G-001','Ripu zāģmašīna kokmateriālu griešanai',5,1,'2025-07-15 09:37:54'),
(26,'Ēvelmašīna G-002','Rindas ēvelmašīna virsmu apstrādei',5,1,'2025-07-15 09:37:54'),
(27,'Frēzmašīna G-003','Koka frēzmašīna profilēšanai',5,1,'2025-07-15 09:37:54'),
(28,'Slīpmašīna G-004','Lentes slīpmašīna finišai apstrādei',5,1,'2025-07-15 09:37:54'),
(29,'Pressos G-005','Hidrauliskais press līmēšanai',5,1,'2025-07-15 09:37:54'),
(30,'Krāsošanas kamera L-001','Automātiskā krāsošanas kamera',6,1,'2025-07-15 09:37:54'),
(31,'Žāvēšanas krāsns L-002','Infrasarkanā žāvēšanas krāsns',6,1,'2025-07-15 09:37:54'),
(32,'Kompresors L-003','Krāsošanas kompresors',6,1,'2025-07-15 09:37:54'),
(33,'Ventilācijas sistēma L-004','Lakotavas ventilācijas sistēma',6,1,'2025-07-15 09:37:54'),
(34,'Pakošanas līnija P-001','Automātiskā pakošanas līnija',7,1,'2025-07-15 09:37:54'),
(35,'Etikešu aplikators P-002','Automātiskais etikešu aplikators',7,1,'2025-07-15 09:37:54'),
(36,'Svari P-003','Precīzie svari produktu svēršanai',7,1,'2025-07-15 09:37:54'),
(37,'Konveijers P-004','Pakotavas konveijers sistēma',7,1,'2025-07-15 09:37:54'),
(38,'Granulu press GR-001','Galvenais granulu press',8,1,'2025-07-15 09:37:54'),
(39,'Žāvētājs GR-002','Materiālu žāvētājs',8,1,'2025-07-15 09:37:54'),
(40,'Malējmašīna GR-003','Materiālu malējmašīna',8,1,'2025-07-15 09:37:54'),
(41,'Sijātājs GR-004','Materiālu sijātājs un šķirotājs',8,1,'2025-07-15 09:37:54'),
(42,'Serveru skapja B-001','IT serveru skapja',9,1,'2025-07-15 09:37:54'),
(43,'Tīkla aprīkojums B-002','Tīkla slēdži un maršrutētāji',9,1,'2025-07-15 09:37:54'),
(44,'Kondicionieris B-003','Biroju kondicionēšanas sistēma',9,1,'2025-07-15 09:37:54'),
(45,'Kravas celtnis N-001','Elektrisks kravas celtnis',10,1,'2025-07-15 09:37:54'),
(46,'Regālu sistēma N-002','Automātiskā regālu sistēma',10,1,'2025-07-15 09:37:54'),
(47,'Konveijers N-003','Noliktavas konveijers sistēma',10,1,'2025-07-15 09:37:54'),
(48,'Sildītājs S-001','Centrālais sildītājs',NULL,1,'2025-07-15 09:37:54'),
(49,'Ūdens sūknis S-002','Galvenais ūdens sūknis',NULL,1,'2025-07-15 09:37:54'),
(50,'Elektro sadalītājs S-003','Galvenais elektro sadalītājs',NULL,1,'2025-07-15 09:37:54'),
(51,'Vārti A-001','Automātiskie vārti',NULL,1,'2025-07-15 09:37:54'),
(52,'Apgaismojums A-002','Ārējais apgaismojums',NULL,1,'2025-07-15 09:37:54'),
(53,'Stāvlaukuma sistēma A-003','Stāvlaukuma barjeras sistēma',NULL,1,'2025-07-15 09:37:54');
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
  `loma` enum('Administrators','Menedžeris','Operators','Mehāniķis') NOT NULL,
  `statuss` enum('Aktīvs','Neaktīvs') DEFAULT 'Aktīvs',
  `izveidots` timestamp NULL DEFAULT current_timestamp(),
  `pēdējā_pieslēgšanās` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lietotajvards` (`lietotajvards`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lietotaji`
--

LOCK TABLES `lietotaji` WRITE;
/*!40000 ALTER TABLE `lietotaji` DISABLE KEYS */;
INSERT INTO `lietotaji` VALUES
(1,'admin','$2y$10$WxTZqiwagN5qf6ForcAaLOI/CeEDElQSvNH4uWWa6plGYeStqOYoe','Administrators','AVOTI','admin@avoti.lv',NULL,'Administrators','Aktīvs','2025-07-15 06:13:35','2025-07-16 11:27:52'),
(3,'menedzers','$2y$10$/JBv84qQr.EfdvdPLtGZ8OKJHmb/oX3CwhjgRGkKxNeFytqLVbrWS','Jānis','Menedžeris','menedzers@avoti.lv',NULL,'Menedžeris','Aktīvs','2025-07-15 09:37:54','2025-07-16 11:25:31'),
(4,'operators','$2y$10$OfGVVd0Z.7A7CJGg9dr9w.6Jg1cEv/1zh8mI4qnDCyK5SKPZwWjGW','Anna','Operatore','operators@avoti.lv',NULL,'Operators','Aktīvs','2025-07-15 09:37:54','2025-07-16 11:24:30'),
(5,'mehaniķis1','$2y$10$//ZXvYYELvpB9Ghae54nfue82HesYfN8A4pkEEzIu.MiTYff2N02i','Pēteris','Mehāniķis','mehanikis1@avoti.lv',NULL,'Mehāniķis','Aktīvs','2025-07-15 09:37:54','2025-07-16 11:14:00'),
(6,'mehaniķis2','$2y$10$1XxUBArFr0Otixkqe3r61OgPVuw61aurYC6fM6VlolgKFjeBhEEoi','Māris','Krancis','mehanikis2@avoti.lv',NULL,'Mehāniķis','Aktīvs','2025-07-15 09:37:54','2025-07-16 11:26:39'),
(7,'edzus.kurins','$2y$10$GxHpHwKtfY5/PwHOloiey.de0UDx93/JDoKMDvP/omNAK7N7uzeDy','Edžus','Kūriņš','edzus.kurins@avoti.lv',NULL,'Menedžeris','Aktīvs','2025-07-15 11:34:43',NULL),
(8,'fuksis','$2y$10$t.ti8plsl8pit.h4y2BsOegJdQU1cd55majwzmcHYVtwmlZ1Za4hC','Aivars','Pētersons',NULL,NULL,'Mehāniķis','Aktīvs','2025-07-16 11:49:03',NULL);
/*!40000 ALTER TABLE `lietotaji` ENABLE KEYS */;
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
  CONSTRAINT `pazinojumi_ibfk_1` FOREIGN KEY (`lietotaja_id`) REFERENCES `lietotaji` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pazinojumi`
--

LOCK TABLES `pazinojumi` WRITE;
/*!40000 ALTER TABLE `pazinojumi` DISABLE KEYS */;
INSERT INTO `pazinojumi` VALUES
(1,4,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Pārbaudīt griezējmašīnas M1-001 troksni','Jauns uzdevums',1,'Uzdevums',1,'2025-07-15 09:37:55'),
(2,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Uzdevums pēterim','Jauns uzdevums',1,'Uzdevums',3,'2025-07-15 10:46:59'),
(3,1,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',1,'Problēma',2,'2025-07-15 10:55:24'),
(4,3,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet ēvele','Jauna problēma',1,'Problēma',2,'2025-07-15 10:55:24'),
(5,1,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Uzdevums pēterim','Statusa maiņa',1,'Uzdevums',3,'2025-07-15 11:01:39'),
(6,3,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Uzdevums pēterim','Statusa maiņa',1,'Uzdevums',3,'2025-07-15 11:01:39'),
(7,5,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Neiet ēvele','Jauns uzdevums',1,'Uzdevums',4,'2025-07-15 11:31:54'),
(8,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Griezējmašīna M1-001 dara troksni','Jauns uzdevums',1,'Uzdevums',5,'2025-07-15 11:32:24'),
(9,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Griezējmašīna M1-001 dara troksni','Statusa maiņa',1,'Uzdevums',5,'2025-07-15 11:40:05'),
(10,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Griezējmašīna M1-001 dara troksni','Statusa maiņa',1,'Uzdevums',5,'2025-07-15 11:40:05'),
(11,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Griezējmašīna M1-001 dara troksni','Statusa maiņa',0,'Uzdevums',5,'2025-07-15 11:40:05'),
(12,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Asināt asmeņus','Jauns uzdevums',1,'Uzdevums',6,'2025-07-15 11:56:05'),
(13,1,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Neiet ēvele','Statusa maiņa',1,'Uzdevums',4,'2025-07-15 12:01:57'),
(14,3,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Neiet ēvele','Statusa maiņa',1,'Uzdevums',4,'2025-07-15 12:01:57'),
(15,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Neiet ēvele','Statusa maiņa',0,'Uzdevums',4,'2025-07-15 12:01:57'),
(16,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Nedēļas tīrīšana M2','Jauns uzdevums',1,'Uzdevums',8,'2025-07-16 09:43:44'),
(17,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Mēneša drošības pārbaude','Jauns uzdevums',1,'Uzdevums',9,'2025-07-16 09:43:52'),
(18,5,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Ceturkšņa kalibrēšana','Jauns uzdevums',1,'Uzdevums',10,'2025-07-16 09:43:55'),
(19,6,'Jauns regulārais uzdevums','Jums ir piešķirts regulārais uzdevums: Uzpīpēt','Jauns uzdevums',1,'Uzdevums',11,'2025-07-16 09:45:09'),
(20,1,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Ceturkšņa kalibrēšana','Statusa maiņa',1,'Uzdevums',10,'2025-07-16 09:46:39'),
(21,3,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Ceturkšņa kalibrēšana','Statusa maiņa',1,'Uzdevums',10,'2025-07-16 09:46:39'),
(22,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Ceturkšņa kalibrēšana','Statusa maiņa',0,'Uzdevums',10,'2025-07-16 09:46:39'),
(23,1,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Mēneša drošības pārbaude','Statusa maiņa',1,'Uzdevums',9,'2025-07-16 09:46:50'),
(24,3,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Mēneša drošības pārbaude','Statusa maiņa',1,'Uzdevums',9,'2025-07-16 09:46:50'),
(25,7,'Uzdevums pabeigts','Mehāniķis Pēteris Mehāniķis ir pabeidzis uzdevumu: Mēneša drošības pārbaude','Statusa maiņa',0,'Uzdevums',9,'2025-07-16 09:46:50'),
(26,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Uzpīpēt','Statusa maiņa',1,'Uzdevums',11,'2025-07-16 10:15:06'),
(27,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Uzpīpēt','Statusa maiņa',1,'Uzdevums',11,'2025-07-16 10:15:06'),
(28,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Uzpīpēt','Statusa maiņa',0,'Uzdevums',11,'2025-07-16 10:15:06'),
(29,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Asināt asmeņus','Statusa maiņa',1,'Uzdevums',6,'2025-07-16 10:15:13'),
(30,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Asināt asmeņus','Statusa maiņa',1,'Uzdevums',6,'2025-07-16 10:15:13'),
(31,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Asināt asmeņus','Statusa maiņa',0,'Uzdevums',6,'2025-07-16 10:15:13'),
(32,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 15.07.2025','Statusa maiņa',1,'Uzdevums',2,'2025-07-16 10:15:17'),
(33,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 15.07.2025','Statusa maiņa',1,'Uzdevums',2,'2025-07-16 10:15:17'),
(34,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 15.07.2025','Statusa maiņa',0,'Uzdevums',2,'2025-07-16 10:15:17'),
(35,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 16.07.2025','Statusa maiņa',1,'Uzdevums',7,'2025-07-16 10:15:28'),
(36,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 16.07.2025','Statusa maiņa',1,'Uzdevums',7,'2025-07-16 10:15:28'),
(37,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Iekārtu ikdienas pārbaude M1 - 16.07.2025','Statusa maiņa',0,'Uzdevums',7,'2025-07-16 10:15:28'),
(38,1,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet COSTA','Jauna problēma',1,'Problēma',3,'2025-07-16 11:25:11'),
(39,3,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet COSTA','Jauna problēma',1,'Problēma',3,'2025-07-16 11:25:11'),
(40,7,'Jauna problēma ziņota','Operators Anna Operatore ir ziņojis jaunu problēmu: Neiet COSTA','Jauna problēma',0,'Problēma',3,'2025-07-16 11:25:11'),
(41,6,'Jauns uzdevums piešķirts','Jums ir piešķirts jauns uzdevums: Neiet COSTA','Jauns uzdevums',1,'Uzdevums',12,'2025-07-16 11:26:13'),
(42,1,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Neiet COSTA','Statusa maiņa',1,'Uzdevums',12,'2025-07-16 11:27:08'),
(43,3,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Neiet COSTA','Statusa maiņa',0,'Uzdevums',12,'2025-07-16 11:27:08'),
(44,7,'Uzdevums pabeigts','Mehāniķis Māris Krancis ir pabeidzis uzdevumu: Neiet COSTA','Statusa maiņa',0,'Uzdevums',12,'2025-07-16 11:27:08');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `problemas`
--

LOCK TABLES `problemas` WRITE;
/*!40000 ALTER TABLE `problemas` DISABLE KEYS */;
INSERT INTO `problemas` VALUES
(1,'Griezējmašīna M1-001 dara troksni','Griezējmašīna M1-001 izdara neparastu troksni darba laikā. Iespējams, problēma ar gultņiem vai pārnesumkārbu.',1,1,'Vidēja','Vidēja',2.00,'Pārvērsta uzdevumā',3,3,'2025-07-15 09:37:55','2025-07-15 11:32:24'),
(2,'Neiet ēvele','dsdsdsdsdsd',2,14,'Vidēja','Sarežģīta',4.00,'Pārvērsta uzdevumā',4,3,'2025-07-15 10:55:24','2025-07-15 11:31:54'),
(3,'Neiet COSTA','Iestrēga lenta',3,18,'Kritiska','Sarežģīta',4.00,'Pārvērsta uzdevumā',4,3,'2025-07-16 11:25:11','2025-07-16 11:26:13');
/*!40000 ALTER TABLE `problemas` ENABLE KEYS */;
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
  `periodicitas_dienas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Nedēļas dienas vai mēneša dienas' CHECK (json_valid(`periodicitas_dienas`)),
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `regularo_uzdevumu_sabloni`
--

LOCK TABLES `regularo_uzdevumu_sabloni` WRITE;
/*!40000 ALTER TABLE `regularo_uzdevumu_sabloni` DISABLE KEYS */;
INSERT INTO `regularo_uzdevumu_sabloni` VALUES
(1,'Iekārtu ikdienas pārbaude M1','Ikdienas iekārtu stāvokļa pārbaude M1 cehā',1,NULL,1,'Vidēja',NULL,'Katru dienu',NULL,'08:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(2,'Nedēļas tīrīšana M2','Nedēļas generāltīrīšana M2 cehā',2,NULL,4,'Vidēja',NULL,'Katru nedēļu',NULL,'16:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(3,'Mēneša drošības pārbaude','Mēneša darba drošības pārbaude visos cehos',NULL,NULL,5,'Augsta',NULL,'Reizi mēnesī',NULL,'09:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(4,'Ceturkšņa kalibrēšana','Ceturkšņa instrumentu kalibrēšana',NULL,NULL,7,'Augsta',NULL,'Reizi ceturksnī',NULL,'10:00:00',1,1,'2025-07-15 09:37:55','2025-07-16 11:42:35'),
(5,'Uzpīpēt','Pīpēc cigārus',26,NULL,NULL,'Vidēja',0.50,'Katru dienu',NULL,'09:00:00',1,1,'2025-07-16 09:43:32','2025-07-16 11:42:35'),
(6,'Iet uz WC','Neapčurāt malas',28,NULL,3,'Augsta',2.00,'Katru dienu',NULL,'09:00:00',1,1,'2025-07-16 10:02:53','2025-07-16 11:42:35'),
(7,'Skrūvēt lampas','skrūvēt',1,12,5,'Vidēja',1.00,'Katru dienu',NULL,'09:00:00',1,1,'2025-07-16 11:49:40','2025-07-16 11:49:40');
/*!40000 ALTER TABLE `regularo_uzdevumu_sabloni` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumi`
--

LOCK TABLES `uzdevumi` WRITE;
/*!40000 ALTER TABLE `uzdevumi` DISABLE KEYS */;
INSERT INTO `uzdevumi` VALUES
(2,'Iekārtu ikdienas pārbaude M1 - 15.07.2025','Ikdienas iekārtu stāvokļa pārbaude M1 cehā','Regulārais',NULL,NULL,1,'Vidēja','Pabeigts',6,1,'2025-07-15 09:38:16',NULL,NULL,22.63,'2025-07-15 11:36:33','2025-07-16 10:15:17',NULL,1,'','2025-07-15 09:38:16','2025-07-16 10:15:17'),
(3,'Uzdevums pēterim','sdssdsd','Ikdienas',2,16,4,'Vidēja','Pabeigts',5,1,'2025-07-15 10:46:58','2025-07-17 10:46:00',5.00,5.00,'2025-07-15 11:01:25','2025-07-15 11:01:38',NULL,NULL,'','2025-07-15 10:46:58','2025-07-15 11:01:38'),
(4,'Neiet ēvele','dsdsdsdsdsd','Ikdienas',2,14,NULL,'Vidēja','Pabeigts',5,3,'2025-07-15 11:31:54','2025-07-16 11:31:00',4.00,0.00,'2025-07-15 12:01:45','2025-07-15 12:01:56',2,NULL,'','2025-07-15 11:31:54','2025-07-15 12:01:56'),
(5,'Griezējmašīna M1-001 dara troksni','Griezējmašīna M1-001 izdara neparastu troksni darba laikā. Iespējams, problēma ar gultņiem vai pārnesumkārbu.','Ikdienas',1,1,NULL,'Vidēja','Pabeigts',6,3,'2025-07-15 11:32:24','2025-07-17 11:32:00',2.00,1.00,'2025-07-15 11:36:38','2025-07-15 11:40:05',1,NULL,'viss ok','2025-07-15 11:32:24','2025-07-15 11:40:05'),
(6,'Asināt asmeņus','fdfdfdf','Regulārais',2,3,1,'Vidēja','Pabeigts',6,1,'2025-07-15 11:56:04',NULL,NULL,2.00,'2025-07-16 10:15:09','2025-07-16 10:15:13',NULL,NULL,'','2025-07-15 11:56:04','2025-07-16 10:15:13'),
(7,'Iekārtu ikdienas pārbaude M1 - 16.07.2025','Ikdienas iekārtu stāvokļa pārbaude M1 cehā','Regulārais',NULL,NULL,1,'Vidēja','Pabeigts',6,1,'2025-07-16 09:38:15',NULL,NULL,3.00,'2025-07-16 10:15:21','2025-07-16 10:15:28',NULL,1,'','2025-07-16 09:38:15','2025-07-16 10:15:28'),
(8,'Nedēļas tīrīšana M2','Nedēļas generāltīrīšana M2 cehā','Regulārais',2,NULL,4,'Vidēja','Jauns',5,1,'2025-07-16 09:43:43',NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,'2025-07-16 09:43:43','2025-07-16 09:43:43'),
(9,'Mēneša drošības pārbaude','Mēneša darba drošības pārbaude visos cehos','Regulārais',NULL,NULL,5,'Augsta','Pabeigts',5,1,'2025-07-16 09:43:52',NULL,NULL,5.00,'2025-07-16 09:46:26','2025-07-16 09:46:50',NULL,3,'','2025-07-16 09:43:52','2025-07-16 09:46:50'),
(10,'Ceturkšņa kalibrēšana','Ceturkšņa instrumentu kalibrēšana','Regulārais',NULL,NULL,7,'Augsta','Pabeigts',5,1,'2025-07-16 09:43:54',NULL,NULL,1.50,'2025-07-16 09:46:31','2025-07-16 09:46:39',NULL,4,'','2025-07-16 09:43:54','2025-07-16 09:46:39'),
(11,'Uzpīpēt','Pīpēc cigārus','Regulārais',26,NULL,NULL,'Vidēja','Pabeigts',6,1,'2025-07-16 09:45:09',NULL,0.50,1.00,'2025-07-16 10:14:55','2025-07-16 10:15:06',NULL,5,'','2025-07-16 09:45:09','2025-07-16 10:15:06'),
(12,'Neiet COSTA','Iestrēga lenta','Ikdienas',3,18,2,'Kritiska','Pabeigts',6,3,'2025-07-16 11:26:13','2025-07-17 11:26:00',4.00,2.00,'2025-07-16 11:26:57','2025-07-16 11:27:08',3,NULL,'Sataisiju','2025-07-16 11:26:13','2025-07-16 11:27:08');
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
(11,'Tīrīšana','Darbavietu un iekārtu tīrīšana',1),
(12,'Drošības pārbaude','Darba drošības pārbaudes un kontrole',1),
(13,'Uzstādīšana','Jaunu iekārtu uzstādīšana un konfigurācija',1),
(14,'Kalibrēšana','Instrumentu un iekārtu kalibrēšana',1),
(15,'Modernizācija','Iekārtu modernizācija un uzlabošana',1),
(16,'Dokumentācija','Dokumentācijas sagatavošana un atjaunošana',1),
(17,'Apmācība','Personāla apmācība un instruktāža',1);
/*!40000 ALTER TABLE `uzdevumu_kategorijas` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzdevumu_vesture`
--

LOCK TABLES `uzdevumu_vesture` WRITE;
/*!40000 ALTER TABLE `uzdevumu_vesture` DISABLE KEYS */;
INSERT INTO `uzdevumu_vesture` VALUES
(2,3,NULL,'Jauns','Uzdevums izveidots',1,'2025-07-15 10:46:59'),
(3,3,'Jauns','Procesā',NULL,5,'2025-07-15 11:01:25'),
(4,3,'Jauns','Procesā','Darbs sākts',5,'2025-07-15 11:01:25'),
(5,3,'Procesā','Pabeigts',NULL,5,'2025-07-15 11:01:38'),
(6,3,'Procesā','Pabeigts','Uzdevums pabeigts',5,'2025-07-15 11:01:38'),
(7,4,NULL,'Jauns','Uzdevums izveidots',3,'2025-07-15 11:31:54'),
(8,5,NULL,'Jauns','Uzdevums izveidots',3,'2025-07-15 11:32:24'),
(9,2,'Jauns','Procesā',NULL,6,'2025-07-15 11:36:33'),
(10,2,'Jauns','Procesā','Darbs sākts',6,'2025-07-15 11:36:33'),
(11,5,'Jauns','Procesā',NULL,6,'2025-07-15 11:36:38'),
(12,5,'Jauns','Procesā','Darbs sākts',6,'2025-07-15 11:36:38'),
(13,5,'Procesā','Pabeigts',NULL,6,'2025-07-15 11:40:05'),
(14,5,'Procesā','Pabeigts','Uzdevums pabeigts: viss ok',6,'2025-07-15 11:40:05'),
(15,6,NULL,'Jauns','Uzdevums izveidots',1,'2025-07-15 11:56:05'),
(16,4,'Jauns','Procesā',NULL,5,'2025-07-15 12:01:45'),
(17,4,'Jauns','Procesā','Darbs sākts',5,'2025-07-15 12:01:45'),
(18,4,'Procesā','Pabeigts',NULL,5,'2025-07-15 12:01:56'),
(19,4,'Procesā','Pabeigts','Uzdevums pabeigts',5,'2025-07-15 12:01:57'),
(20,8,NULL,'Jauns','Regulārais uzdevums izveidots automātiski',1,'2025-07-16 09:43:44'),
(21,9,NULL,'Jauns','Regulārais uzdevums izveidots automātiski',1,'2025-07-16 09:43:52'),
(22,10,NULL,'Jauns','Regulārais uzdevums izveidots automātiski',1,'2025-07-16 09:43:55'),
(23,11,NULL,'Jauns','Regulārais uzdevums izveidots automātiski',1,'2025-07-16 09:45:09'),
(24,9,'Jauns','Procesā',NULL,5,'2025-07-16 09:46:26'),
(25,9,'Jauns','Procesā','Darbs sākts',5,'2025-07-16 09:46:26'),
(26,10,'Jauns','Procesā',NULL,5,'2025-07-16 09:46:31'),
(27,10,'Jauns','Procesā','Darbs sākts',5,'2025-07-16 09:46:31'),
(28,10,'Procesā','Pabeigts',NULL,5,'2025-07-16 09:46:39'),
(29,10,'Procesā','Pabeigts','Uzdevums pabeigts',5,'2025-07-16 09:46:39'),
(30,9,'Procesā','Pabeigts',NULL,5,'2025-07-16 09:46:50'),
(31,9,'Procesā','Pabeigts','Uzdevums pabeigts',5,'2025-07-16 09:46:50'),
(32,11,'Jauns','Procesā',NULL,6,'2025-07-16 10:14:55'),
(33,11,'Jauns','Procesā','Darbs sākts',6,'2025-07-16 10:14:55'),
(34,11,'Procesā','Pabeigts',NULL,6,'2025-07-16 10:15:06'),
(35,11,'Procesā','Pabeigts','Uzdevums pabeigts',6,'2025-07-16 10:15:06'),
(36,6,'Jauns','Procesā',NULL,6,'2025-07-16 10:15:09'),
(37,6,'Jauns','Procesā','Darbs sākts',6,'2025-07-16 10:15:09'),
(38,6,'Procesā','Pabeigts',NULL,6,'2025-07-16 10:15:13'),
(39,6,'Procesā','Pabeigts','Uzdevums pabeigts',6,'2025-07-16 10:15:13'),
(40,2,'Procesā','Pabeigts',NULL,6,'2025-07-16 10:15:17'),
(41,2,'Procesā','Pabeigts','Uzdevums pabeigts',6,'2025-07-16 10:15:17'),
(42,7,'Jauns','Procesā',NULL,6,'2025-07-16 10:15:21'),
(43,7,'Jauns','Procesā','Darbs sākts',6,'2025-07-16 10:15:21'),
(44,7,'Procesā','Pabeigts',NULL,6,'2025-07-16 10:15:28'),
(45,7,'Procesā','Pabeigts','Uzdevums pabeigts',6,'2025-07-16 10:15:28'),
(46,12,NULL,'Jauns','Uzdevums izveidots',3,'2025-07-16 11:26:13'),
(47,12,'Jauns','Procesā',NULL,6,'2025-07-16 11:26:57'),
(48,12,'Jauns','Procesā','Darbs sākts',6,'2025-07-16 11:26:57'),
(49,12,'Procesā','Pabeigts',NULL,6,'2025-07-16 11:27:08'),
(50,12,'Procesā','Pabeigts','Uzdevums pabeigts: Sataisiju',6,'2025-07-16 11:27:08');
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vietas`
--

LOCK TABLES `vietas` WRITE;
/*!40000 ALTER TABLE `vietas` DISABLE KEYS */;
INSERT INTO `vietas` VALUES
(1,'M1 Cehs','Mašīnbūves cehs Nr.1',1,'2025-07-15 06:13:35'),
(2,'M2 Cehs','Mašīnbūves cehs Nr.2',1,'2025-07-15 06:13:35'),
(3,'M3 Cehs','Mašīnbūves cehs Nr.3',1,'2025-07-15 06:13:35'),
(4,'M4 Cehs','Mašīnbūves cehs Nr.4',1,'2025-07-15 06:13:35'),
(5,'Galdniecība','Galdniecības darbnīca',1,'2025-07-15 06:13:35'),
(6,'Lakotava Jaunā','Lakošanas jaunais cehs pie Noliktavas',1,'2025-07-15 06:13:35'),
(7,'Pakotava','Pakošanas iecirknis',1,'2025-07-15 06:13:35'),
(8,'Granulas','Granulu ražošana',1,'2025-07-15 06:13:35'),
(9,'Birojs','Administratīvās telpas',0,'2025-07-15 06:13:35'),
(10,'Noliktava','Gatavo izstrādājumu noliktava',1,'2025-07-15 06:13:35'),
(24,'Lakotava Vecā','Vecās Lakotavas cehs M2',1,'2025-07-15 11:53:37'),
(25,'Kaltes','',1,'2025-07-15 11:53:56'),
(26,'Optimas placis','',1,'2025-07-15 11:54:07'),
(27,'Brikešu (apaļo) cehs','',1,'2025-07-15 11:54:33'),
(28,'Brikešu (Kantaino) cehs','',1,'2025-07-15 11:54:41');
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

-- Dump completed on 2025-07-16 14:50:50
