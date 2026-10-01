-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: morrow_and_blade_php
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Current Database: `morrow_and_blade_php`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `morrow_and_blade_php` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `morrow_and_blade_php`;

--
-- Table structure for table `ai_conversations`
--

DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversations` (
  `id` char(36) NOT NULL,
  `customer_id` char(36) DEFAULT NULL,
  `session_key` varchar(64) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ai_conversations_session_idx` (`session_key`,`updated_at`),
  KEY `ai_conversations_customer_id_fkey` (`customer_id`),
  CONSTRAINT `ai_conversations_customer_id_fkey` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assistant threads, keyed by browser session when signed out.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_conversations`
--

LOCK TABLES `ai_conversations` WRITE;
/*!40000 ALTER TABLE `ai_conversations` DISABLE KEYS */;
INSERT INTO `ai_conversations` VALUES ('ef4dd866-1262-4f93-ac24-fcede67dd503',NULL,'bjsjkgkvnmvkd9be8hvph6q8rg','hey','2026-09-29 18:19:50','2026-09-29 19:33:04');
/*!40000 ALTER TABLE `ai_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_messages`
--

DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ai_messages_conversation_idx` (`conversation_id`,`id`),
  CONSTRAINT `ai_messages_conversation_id_fkey` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assistant transcript.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_messages`
--

LOCK TABLES `ai_messages` WRITE;
/*!40000 ALTER TABLE `ai_messages` DISABLE KEYS */;
INSERT INTO `ai_messages` VALUES (27,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','hey','2026-09-29 18:19:50'),(28,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','I can help with prices, what is available today, our team, opening hours and how to find us. For anything medical or for a complaint, the quickest route is +44 20 7946 0188.','2026-09-29 18:19:50'),(29,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','What time do you close on Saturday?','2026-09-29 19:32:28'),(30,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Our hours are: Monday 09:00 - 19:00, Tuesday 09:00 - 20:00, Wednesday 09:00 - 20:00, Thursday 09:00 - 20:00, Friday 09:00 - 20:00, Saturday 09:00 - 18:00, Sunday Closed. If you arrive close to closing time, it is worth ringing first.','2026-09-29 19:32:28'),(31,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','What time do you close on Saturday?','2026-09-29 19:32:28'),(32,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Our hours are: Monday 09:00 - 19:00, Tuesday 09:00 - 20:00, Wednesday 09:00 - 20:00, Thursday 09:00 - 20:00, Friday 09:00 - 20:00, Saturday 09:00 - 18:00, Sunday Closed. If you arrive close to closing time, it is worth ringing first.','2026-09-29 19:32:28'),(33,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','Who is free this afternoon?','2026-09-29 19:32:30'),(34,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Free right now: Jay Morrow, David Chen, Jordan Reed, Amara Diallo, Lena Petrova. Otherwise: Mike Ellis (currently with a customer). You can take any of those slots on the booking page.','2026-09-29 19:32:30'),(35,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','Who is free this afternoon?','2026-09-29 19:32:30'),(36,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Free right now: Jay Morrow, David Chen, Jordan Reed, Amara Diallo, Lena Petrova. Otherwise: Mike Ellis (currently with a customer). You can take any of those slots on the booking page.','2026-09-29 19:32:30'),(37,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','Where is your nearest salon?','2026-09-29 19:32:32'),(38,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','We have 3 London branches: Morrow & Blade Soho at 18 Mercer Street, London WC2H 9QJ; Morrow & Blade Shoreditch at 42 Redchurch Street, London E2 7DP; Morrow & Blade Canary Wharf at 12 Cabot Square, London E14 4QQ. The Visit us page has a map and a one-tap directions link for each one.','2026-09-29 19:32:32'),(39,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','Where is your nearest salon?','2026-09-29 19:32:32'),(40,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','We have 3 London branches: Morrow & Blade Soho at 18 Mercer Street, London WC2H 9QJ; Morrow & Blade Shoreditch at 42 Redchurch Street, London E2 7DP; Morrow & Blade Canary Wharf at 12 Cabot Square, London E14 4QQ. The Visit us page has a map and a one-tap directions link for each one.','2026-09-29 19:32:32'),(41,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','How much is a skin fade?','2026-09-29 19:32:34'),(42,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Skin Fade is GBP 52.00 (50 min). Prices are the same at all three branches.','2026-09-29 19:32:34'),(43,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','How much is a skin fade?','2026-09-29 19:32:34'),(44,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Skin Fade is GBP 52.00 (50 min). Prices are the same at all three branches.','2026-09-29 19:32:34'),(45,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','anyone available?','2026-09-29 19:32:44'),(46,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','Free right now: Jay Morrow, David Chen, Jordan Reed, Amara Diallo, Lena Petrova. Otherwise: Mike Ellis (currently with a customer). You can take any of those slots on the booking page.','2026-09-29 19:32:44'),(47,'ef4dd866-1262-4f93-ac24-fcede67dd503','user','im new what can you tell me about the salon','2026-09-29 19:33:04'),(48,'ef4dd866-1262-4f93-ac24-fcede67dd503','assistant','I can help with prices, what is available today, our team, opening hours and how to find us. For anything medical or for a complaint, the quickest route is +44 20 7946 0188.','2026-09-29 19:33:04');
/*!40000 ALTER TABLE `ai_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` char(36) NOT NULL ,
  `reference` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `public_token` char(36) NOT NULL,
  `customer_id` char(36) NOT NULL,
  `barber_id` char(36) NOT NULL,
  `service_id` char(36) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `price_pence_snapshot` int(11) NOT NULL,
  `service_name_snapshot` varchar(150) NOT NULL,
  `notes` varchar(2000) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointments_reference_key` (`reference`),
  KEY `appointments_barber_starts_idx` (`barber_id`,`starts_at`),
  KEY `appointments_customer_idx` (`customer_id`,`starts_at`),
  KEY `appointments_status_starts_idx` (`status`,`starts_at`),
  KEY `appointments_service_id_idx` (`service_id`),
  CONSTRAINT `appointments_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`),
  CONSTRAINT `appointments_customer_id_fkey` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `appointments_service_id_fkey` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `appointments_time_check` CHECK (`ends_at` > `starts_at`),
  CONSTRAINT `appointments_price_check` CHECK (`price_pence_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bookings. Overlap protection comes from the appointments_no_overlap triggers.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES ('139d0cd9-bc43-11f1-890c-380025435a7f','MB-33604','6378115d-96b6-49c2-8e77-974999c85e19','3dff6bd5-761f-429d-bb45-dd1255df8424','55555555-5555-4555-8555-555555555555','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa7','2026-09-30 08:30:00','2026-09-30 09:30:00','confirmed',5500,'Relaxing Massage','','2026-09-29 21:19:30','2026-09-29 21:19:30',NULL),('b0000000-0000-4000-8000-000000000001','MB-10001','d0000000-0000-4000-8000-000000000001','c0ffee00-0000-4000-8000-000000000001','11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1','2026-09-29 10:00:00','2026-09-29 10:45:00','confirmed',4800,'Signature Haircut','','2026-09-29 16:36:33','2026-09-29 16:36:33',NULL),('b0000000-0000-4000-8000-000000000002','MB-10002','d0000000-0000-4000-8000-000000000002','c0ffee00-0000-4000-8000-000000000002','22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2','2026-09-29 11:00:00','2026-09-29 11:50:00','confirmed',5200,'Skin Fade','Prefers a low fade.','2026-09-29 16:36:33','2026-09-29 16:36:33',NULL),('b0000000-0000-4000-8000-000000000003','MB-10003','d0000000-0000-4000-8000-000000000003','c0ffee00-0000-4000-8000-000000000003','33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3','2026-09-29 14:00:00','2026-09-29 14:30:00','pending',3200,'Beard Sculpt','','2026-09-29 16:36:33','2026-09-29 16:36:33',NULL),('b0000000-0000-4000-8000-000000000004','MB-10004','d0000000-0000-4000-8000-000000000004','c0ffee00-0000-4000-8000-000000000001','11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4','2026-09-28 15:00:00','2026-09-28 16:15:00','completed',7200,'Hair + Beard','','2026-09-29 16:36:33','2026-09-29 16:36:33',NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `appointments_no_overlap_insert`
BEFORE INSERT ON `appointments`
FOR EACH ROW
BEGIN
  IF NEW.`status` IN ('pending','confirmed','in_progress') THEN
    IF EXISTS (
      SELECT 1
      FROM `appointments` `a`
      WHERE `a`.`barber_id` = NEW.`barber_id`
        AND `a`.`status` IN ('pending','confirmed','in_progress')
        AND NEW.`starts_at` < `a`.`ends_at`
        AND NEW.`ends_at`   > `a`.`starts_at`
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SLOT_UNAVAILABLE';
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `appointments_emit_availability_ins`
AFTER INSERT ON `appointments`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (NEW.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `appointments_no_overlap_update`
BEFORE UPDATE ON `appointments`
FOR EACH ROW
BEGIN
  IF NEW.`status` IN ('pending','confirmed','in_progress') THEN
    IF EXISTS (
      SELECT 1
      FROM `appointments` `a`
      WHERE `a`.`barber_id` = NEW.`barber_id`
        AND `a`.`id` <> NEW.`id`
        AND `a`.`status` IN ('pending','confirmed','in_progress')
        AND NEW.`starts_at` < `a`.`ends_at`
        AND NEW.`ends_at`   > `a`.`starts_at`
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SLOT_UNAVAILABLE';
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `appointments_emit_availability_upd`
AFTER UPDATE ON `appointments`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (NEW.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `appointments_emit_availability_del`
AFTER DELETE ON `appointments`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (OLD.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `availability_events`
--

DROP TABLE IF EXISTS `availability_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `availability_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `barber_id` char(36) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `availability_events_changed_idx` (`changed_at`),
  KEY `availability_events_barber_id_idx` (`barber_id`),
  CONSTRAINT `availability_events_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Written by trigger on appointment / override changes.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `availability_events`
--

LOCK TABLES `availability_events` WRITE;
/*!40000 ALTER TABLE `availability_events` DISABLE KEYS */;
INSERT INTO `availability_events` VALUES (1,'11111111-1111-4111-8111-111111111111','2026-09-29 16:36:33'),(2,'22222222-2222-4222-8222-222222222222','2026-09-29 16:36:33'),(3,'33333333-3333-4333-8333-333333333333','2026-09-29 16:36:33'),(4,'11111111-1111-4111-8111-111111111111','2026-09-29 16:36:33'),(5,'22222222-2222-4222-8222-222222222222','2026-09-29 16:36:33'),(6,'11111111-1111-4111-8111-111111111111','2026-09-29 17:47:48'),(7,'11111111-1111-4111-8111-111111111111','2026-09-29 17:49:03'),(8,'11111111-1111-4111-8111-111111111111','2026-09-29 18:06:09'),(9,'11111111-1111-4111-8111-111111111111','2026-09-29 18:06:17'),(10,'11111111-1111-4111-8111-111111111111','2026-09-29 18:08:07'),(11,'11111111-1111-4111-8111-111111111111','2026-09-29 18:08:42'),(12,'55555555-5555-4555-8555-555555555555','2026-09-29 21:19:30'),(13,'11111111-1111-4111-8111-111111111111','2026-09-29 21:37:31'),(14,'11111111-1111-4111-8111-111111111111','2026-09-29 21:37:37'),(15,'11111111-1111-4111-8111-111111111111','2026-09-29 21:38:16'),(16,'11111111-1111-4111-8111-111111111111','2026-09-29 21:38:16'),(17,'22222222-2222-4222-8222-222222222222','2026-09-29 21:39:49'),(18,'22222222-2222-4222-8222-222222222222','2026-09-29 21:39:49'),(19,'11111111-1111-4111-8111-111111111111','2026-09-29 21:39:55'),(20,'11111111-1111-4111-8111-111111111111','2026-09-29 21:39:55'),(21,'11111111-1111-4111-8111-111111111111','2026-09-29 21:40:16'),(22,'11111111-1111-4111-8111-111111111111','2026-09-29 21:40:17'),(23,'11111111-1111-4111-8111-111111111111','2026-09-29 21:40:51'),(24,'11111111-1111-4111-8111-111111111111','2026-09-29 21:52:27'),(25,'11111111-1111-4111-8111-111111111111','2026-09-29 21:52:27'),(26,'11111111-1111-4111-8111-111111111111','2026-09-29 21:52:27'),(27,'22222222-2222-4222-8222-222222222222','2026-09-29 21:52:27'),(28,'11111111-1111-4111-8111-111111111111','2026-09-29 21:52:27'),(29,'11111111-1111-4111-8111-111111111111','2026-09-29 21:52:27');
/*!40000 ALTER TABLE `availability_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barber_services`
--

DROP TABLE IF EXISTS `barber_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barber_services` (
  `barber_id` char(36) NOT NULL,
  `service_id` char(36) NOT NULL,
  PRIMARY KEY (`barber_id`,`service_id`),
  KEY `barber_services_service_id_idx` (`service_id`),
  CONSTRAINT `barber_services_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barber_services_service_id_fkey` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Join table: services each barber offers.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barber_services`
--

LOCK TABLES `barber_services` WRITE;
/*!40000 ALTER TABLE `barber_services` DISABLE KEYS */;
INSERT INTO `barber_services` VALUES ('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'),('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'),('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'),('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4'),('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5'),('11111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa6'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5'),('22222222-2222-4222-8222-222222222222','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa6'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5'),('33333333-3333-4333-8333-333333333333','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa6'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5'),('44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa6'),('55555555-5555-4555-8555-555555555555','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa7'),('66666666-6666-4666-8666-666666666666','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa8'),('66666666-6666-4666-8666-666666666666','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa9');
/*!40000 ALTER TABLE `barber_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barber_status_overrides`
--

DROP TABLE IF EXISTS `barber_status_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barber_status_overrides` (
  `id` char(36) NOT NULL ,
  `barber_id` char(36) NOT NULL,
  `status` enum('available','busy','offline','booked') NOT NULL,
  `reason` varchar(500) NOT NULL DEFAULT '',
  `valid_until` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barber_status_overrides_barber_id_key` (`barber_id`),
  KEY `barber_status_overrides_created_by_idx` (`created_by`),
  CONSTRAINT `barber_status_overrides_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barber_status_overrides_created_by_fkey` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manual status override that outranks the schedule.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barber_status_overrides`
--

LOCK TABLES `barber_status_overrides` WRITE;
/*!40000 ALTER TABLE `barber_status_overrides` DISABLE KEYS */;
INSERT INTO `barber_status_overrides` VALUES ('e0000000-0000-4000-8000-000000000001','22222222-2222-4222-8222-222222222222','busy','Currently with a customer',NULL,NULL,'2026-09-29 16:36:33','2026-09-29 16:36:33');
/*!40000 ALTER TABLE `barber_status_overrides` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `overrides_emit_availability_ins`
AFTER INSERT ON `barber_status_overrides`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (NEW.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `overrides_emit_availability_upd`
AFTER UPDATE ON `barber_status_overrides`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (NEW.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `overrides_emit_availability_del`
AFTER DELETE ON `barber_status_overrides`
FOR EACH ROW
BEGIN
  INSERT INTO `availability_events` (`barber_id`) VALUES (OLD.`barber_id`);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `barbers`
--

DROP TABLE IF EXISTS `barbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barbers` (
  `id` char(36) NOT NULL ,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(150) NOT NULL,
  `role` varchar(120) NOT NULL DEFAULT 'Barber',
  `staff_kind` enum('barber','beautician','nail_technician','therapist') NOT NULL DEFAULT 'barber',
  `salon_id` char(36) DEFAULT NULL,
  `bio` varchar(2000) NOT NULL DEFAULT '',
  `photo_url` varchar(500) NOT NULL DEFAULT '',
  `specialties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`specialties`)),
  `years_experience` int(11) NOT NULL DEFAULT 0,
  `rating` decimal(2,1) NOT NULL DEFAULT 5.0,
  `review_count` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barbers_slug_key` (`slug`),
  KEY `barbers_salon_id_fkey` (`salon_id`),
  CONSTRAINT `barbers_salon_id_fkey` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barbers_years_experience_check` CHECK (`years_experience` >= 0),
  CONSTRAINT `barbers_rating_check` CHECK (`rating` between 0 and 5),
  CONSTRAINT `barbers_review_count_check` CHECK (`review_count` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barbers shown on the public site. specialties holds a JSON array of strings.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barbers`
--

LOCK TABLES `barbers` WRITE;
/*!40000 ALTER TABLE `barbers` DISABLE KEYS */;
INSERT INTO `barbers` VALUES ('11111111-1111-4111-8111-111111111111','jay-morrow','Jay Morrow','Founder & Master Barber','barber','a1000000-0000-4000-8000-000000000001','Known for quietly precise fades, sculpted texture, and a chair-side experience built around the person.','https://images.unsplash.com/photo-1618077360395-f3068be8e001?auto=format&fit=crop&w=1200&q=88','[\"Skin fades\", \"Textured crops\", \"Restyles\"]',14,4.9,186,1,1,'2026-09-29 16:36:33','2026-09-29 17:55:41'),('22222222-2222-4222-8222-222222222222','mike-ellis','Mike Ellis','Senior Barber','barber','a1000000-0000-4000-8000-000000000001','A classic barber with a modern eye, specialising in clean silhouettes and beard architecture.','https://images.unsplash.com/photo-1531384441138-2736e62e0919?auto=format&fit=crop&w=1200&q=88','[\"Beard design\", \"Classic cuts\", \"Scissor work\"]',11,4.8,142,1,2,'2026-09-29 16:36:33','2026-09-29 17:55:41'),('33333333-3333-4333-8333-333333333333','david-chen','David Chen','Barber & Grooming Specialist','barber','a1000000-0000-4000-8000-000000000002','Calm detail for longer shapes, precision tapering, and restorative grooming rituals.','https://images.unsplash.com/photo-1506277886164-e25aa3f4ef7f?auto=format&fit=crop&w=1200&q=88','[\"Long hair\", \"Tapers\", \"Hot towel shaves\"]',9,4.9,119,1,3,'2026-09-29 16:36:33','2026-09-29 17:55:41'),('44444444-4444-4444-8444-444444444444','jordan-reed','Jordan Reed','Style Barber','barber','a1000000-0000-4000-8000-000000000002','Sharp technical work with an editorial eye for expressive, wearable cuts.','https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1200&q=88','[\"Creative cuts\", \"Curly hair\", \"Colour styling\"]',7,4.7,96,1,4,'2026-09-29 16:36:33','2026-09-29 17:55:41'),('55555555-5555-4555-8555-555555555555','amara-diallo','Amara Diallo','Massage Therapist','therapist','a1000000-0000-4000-8000-000000000003','Works slowly and deliberately, with a focus on releasing tension in the neck, shoulders and scalp.','https://images.unsplash.com/photo-1594744803329-e58b31de8bf5?auto=format&fit=crop&w=1200&q=88','[\"Relaxing massage\", \"Head and neck\", \"Deep tissue\"]',8,4.9,74,1,5,'2026-09-29 17:37:07','2026-09-29 17:55:41'),('66666666-6666-4666-8666-666666666666','lena-petrova','Lena Petrova','Nail Technician','nail_technician','a1000000-0000-4000-8000-000000000003','Precise, gentle nail care with a clean finish and a strong eye for colour.','https://images.unsplash.com/photo-1580618672591-eb180b1a973f?auto=format&fit=crop&w=1200&q=88','[\"Manicure\", \"Pedicure\", \"Gel polish\"]',6,4.8,58,1,6,'2026-09-29 17:37:07','2026-09-29 17:55:41');
/*!40000 ALTER TABLE `barbers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `id` char(36) NOT NULL ,
  `customer_id` char(36) NOT NULL,
  `barber_id` char(36) NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `last_message_at` datetime DEFAULT NULL,
  `customer_unread` int(11) NOT NULL DEFAULT 0,
  `barber_unread` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_pair_key` (`customer_id`,`barber_id`),
  KEY `conversations_barber_idx` (`barber_id`,`last_message_at`),
  CONSTRAINT `conversations_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_customer_id_fkey` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One thread per customer/barber pair.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
INSERT INTO `conversations` VALUES ('8087309c-5bd1-440e-82db-fb6677ea1d1f','3dff6bd5-761f-429d-bb45-dd1255df8424','55555555-5555-4555-8555-555555555555','hello','open','2026-09-29 21:25:31',1,0,'2026-09-29 21:18:34','2026-09-29 21:25:31');
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` char(36) NOT NULL ,
  `name` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `preferred_barber_id` char(36) DEFAULT NULL,
  `notes` varchar(1000) NOT NULL DEFAULT '',
  `marketing_opt_in` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email_key` (`email`),
  KEY `customers_preferred_barber_id_fkey` (`preferred_barber_id`),
  CONSTRAINT `customers_preferred_barber_id_fkey` FOREIGN KEY (`preferred_barber_id`) REFERENCES `barbers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Booking customers. No account is required to book.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES ('3dff6bd5-761f-429d-bb45-dd1255df8424','Tomiwa Johnson','giwaolabamiji41@gmail.com','07344393646','$2y$10$RuWq9RlapjrBYLEI7bACQO.sb92h0sujZxQmCDShQqtplbhMB2ioe',NULL,'',1,NULL,'2026-09-29 21:18:07','2026-09-29 21:19:30'),('c0ffee00-0000-4000-8000-000000000001','Daniel Okafor','daniel.okafor@example.com','+44 7700 900101',NULL,NULL,'',1,NULL,'2026-09-29 16:36:33','2026-09-29 16:36:33'),('c0ffee00-0000-4000-8000-000000000002','Priya Sharma','priya.sharma@example.com','+44 7700 900102',NULL,NULL,'',0,NULL,'2026-09-29 16:36:33','2026-09-29 16:36:33'),('c0ffee00-0000-4000-8000-000000000003','Tom Whitfield','tom.whitfield@example.com','+44 7700 900103',NULL,NULL,'',1,NULL,'2026-09-29 16:36:33','2026-09-29 16:36:33');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) NOT NULL,
  `sender_type` enum('customer','barber') NOT NULL,
  `sender_customer_id` char(36) DEFAULT NULL,
  `sender_profile_id` char(36) DEFAULT NULL,
  `body` text NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `messages_conversation_idx` (`conversation_id`,`id`),
  KEY `messages_sender_customer_id_fkey` (`sender_customer_id`),
  KEY `messages_sender_profile_id_fkey` (`sender_profile_id`),
  CONSTRAINT `messages_conversation_id_fkey` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_customer_id_fkey` FOREIGN KEY (`sender_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_sender_profile_id_fkey` FOREIGN KEY (`sender_profile_id`) REFERENCES `profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat messages. Body is plain text, escaped on output.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (3,'8087309c-5bd1-440e-82db-fb6677ea1d1f','customer','3dff6bd5-761f-429d-bb45-dd1255df8424',NULL,'hello','2026-09-29 21:25:00','2026-09-29 21:18:42'),(4,'8087309c-5bd1-440e-82db-fb6677ea1d1f','barber',NULL,'9a7b3355-bc26-11f1-890c-380025435a7f','Hi, I\'m available tomorrow',NULL,'2026-09-29 21:25:31');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profiles` (
  `id` char(36) NOT NULL,
  `full_name` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(254) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `role` enum('pending','admin','manager','barber','staff') NOT NULL DEFAULT 'pending',
  `barber_id` char(36) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `profiles_email_key` (`email`),
  KEY `profiles_barber_id_fkey` (`barber_id`),
  CONSTRAINT `profiles_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dashboard users. Role pending until an owner promotes them.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profiles`
--

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` VALUES ('9a7ae457-bc26-11f1-890c-380025435a7f','Salon Owner','admin@morrowandblade.co.uk','$2y$10$JLNNY5DP5XUMX3bvxmhB/Ok58MX88mzTJpc05aBPDqU0FcGgIYBUC','+44 20 7946 0188','admin',NULL,1,'2026-09-29 21:11:53','2026-09-29 17:55:41','2026-09-29 21:11:53'),('9a7b3099-bc26-11f1-890c-380025435a7f','Jay Morrow','jay-morrow@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','11111111-1111-4111-8111-111111111111',1,'2026-09-29 21:40:51','2026-09-29 17:55:41','2026-09-29 21:40:51'),('9a7b3212-bc26-11f1-890c-380025435a7f','Mike Ellis','mike-ellis@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','22222222-2222-4222-8222-222222222222',1,NULL,'2026-09-29 17:55:41','2026-09-29 17:55:41'),('9a7b3290-bc26-11f1-890c-380025435a7f','David Chen','david-chen@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','33333333-3333-4333-8333-333333333333',1,NULL,'2026-09-29 17:55:41','2026-09-29 17:55:41'),('9a7b32f3-bc26-11f1-890c-380025435a7f','Jordan Reed','jordan-reed@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','44444444-4444-4444-8444-444444444444',1,NULL,'2026-09-29 17:55:41','2026-09-29 17:55:41'),('9a7b3355-bc26-11f1-890c-380025435a7f','Amara Diallo','amara-diallo@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','55555555-5555-4555-8555-555555555555',1,'2026-09-29 21:23:41','2026-09-29 17:55:41','2026-09-29 21:23:41'),('9a7b33b0-bc26-11f1-890c-380025435a7f','Lena Petrova','lena-petrova@morrowandblade.co.uk','$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK','','barber','66666666-6666-4666-8666-666666666666',1,NULL,'2026-09-29 17:55:41','2026-09-29 17:55:41');
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salon_settings`
--

DROP TABLE IF EXISTS `salon_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salon_settings` (
  `singleton` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(150) NOT NULL,
  `tagline` varchar(255) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) NOT NULL,
  `city` varchar(120) NOT NULL DEFAULT '',
  `postcode` varchar(20) NOT NULL DEFAULT '',
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `phone` varchar(40) NOT NULL,
  `email` varchar(254) NOT NULL,
  `instagram` varchar(100) NOT NULL DEFAULT '',
  `timezone` varchar(64) NOT NULL DEFAULT 'Europe/London',
  `opening_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`opening_hours`)),
  `updated_by` char(36) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`singleton`),
  KEY `salon_settings_updated_by_idx` (`updated_by`),
  CONSTRAINT `salon_settings_updated_by_fkey` FOREIGN KEY (`updated_by`) REFERENCES `profiles` (`id`),
  CONSTRAINT `salon_settings_singleton_check` CHECK (`singleton` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Exactly one row. Address, contact details and opening hours.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salon_settings`
--

LOCK TABLES `salon_settings` WRITE;
/*!40000 ALTER TABLE `salon_settings` DISABLE KEYS */;
INSERT INTO `salon_settings` VALUES (1,'Morrow & Blade','Modern craft. Personal service.','18 Mercer Street','London, WC2H 9QJ','London','WC2H 9QJ',51.513600,-0.129100,'+44 20 7946 0188','hello@morrowandblade.co.uk','@morrowandblade','Europe/London','[\n     {\"day\":\"Monday\",\"shortDay\":\"Mon\",\"hours\":\"09:00 - 19:00\",\"isOpen\":true},\n     {\"day\":\"Tuesday\",\"shortDay\":\"Tue\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},\n     {\"day\":\"Wednesday\",\"shortDay\":\"Wed\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},\n     {\"day\":\"Thursday\",\"shortDay\":\"Thu\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},\n     {\"day\":\"Friday\",\"shortDay\":\"Fri\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},\n     {\"day\":\"Saturday\",\"shortDay\":\"Sat\",\"hours\":\"09:00 - 18:00\",\"isOpen\":true},\n     {\"day\":\"Sunday\",\"shortDay\":\"Sun\",\"hours\":\"Closed\",\"isOpen\":false}\n   ]',NULL,'2026-09-29 17:37:07');
/*!40000 ALTER TABLE `salon_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salons`
--

DROP TABLE IF EXISTS `salons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salons` (
  `id` char(36) NOT NULL ,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(150) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL,
  `postcode` varchar(20) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `email` varchar(254) NOT NULL DEFAULT '',
  `opening_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`opening_hours`)),
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `salons_slug_key` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical branches. Used for the closest-salon finder.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salons`
--

LOCK TABLES `salons` WRITE;
/*!40000 ALTER TABLE `salons` DISABLE KEYS */;
INSERT INTO `salons` VALUES ('a1000000-0000-4000-8000-000000000001','soho','Morrow & Blade Soho','18 Mercer Street','','London','WC2H 9QJ',51.513600,-0.129100,'+44 20 7946 0188','soho@morrowandblade.co.uk','[{\"day\":\"Monday\",\"shortDay\":\"Mon\",\"hours\":\"09:00 - 19:00\",\"isOpen\":true},{\"day\":\"Tuesday\",\"shortDay\":\"Tue\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},{\"day\":\"Wednesday\",\"shortDay\":\"Wed\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},{\"day\":\"Thursday\",\"shortDay\":\"Thu\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},{\"day\":\"Friday\",\"shortDay\":\"Fri\",\"hours\":\"09:00 - 20:00\",\"isOpen\":true},{\"day\":\"Saturday\",\"shortDay\":\"Sat\",\"hours\":\"09:00 - 18:00\",\"isOpen\":true},{\"day\":\"Sunday\",\"shortDay\":\"Sun\",\"hours\":\"Closed\",\"isOpen\":false}]',1,1,1,'2026-09-29 17:55:41','2026-09-29 17:55:41'),('a1000000-0000-4000-8000-000000000002','shoreditch','Morrow & Blade Shoreditch','42 Redchurch Street','','London','E2 7DP',51.524700,-0.075500,'+44 20 7946 0241','shoreditch@morrowandblade.co.uk','[{\"day\":\"Monday\",\"shortDay\":\"Mon\",\"hours\":\"10:00 - 19:00\",\"isOpen\":true},{\"day\":\"Tuesday\",\"shortDay\":\"Tue\",\"hours\":\"10:00 - 20:00\",\"isOpen\":true},{\"day\":\"Wednesday\",\"shortDay\":\"Wed\",\"hours\":\"10:00 - 20:00\",\"isOpen\":true},{\"day\":\"Thursday\",\"shortDay\":\"Thu\",\"hours\":\"10:00 - 21:00\",\"isOpen\":true},{\"day\":\"Friday\",\"shortDay\":\"Fri\",\"hours\":\"10:00 - 21:00\",\"isOpen\":true},{\"day\":\"Saturday\",\"shortDay\":\"Sat\",\"hours\":\"09:00 - 19:00\",\"isOpen\":true},{\"day\":\"Sunday\",\"shortDay\":\"Sun\",\"hours\":\"11:00 - 17:00\",\"isOpen\":true}]',0,1,2,'2026-09-29 17:55:41','2026-09-29 17:55:41'),('a1000000-0000-4000-8000-000000000003','canary-wharf','Morrow & Blade Canary Wharf','12 Cabot Square','','London','E14 4QQ',51.505100,-0.023300,'+44 20 7946 0319','canarywharf@morrowandblade.co.uk','[{\"day\":\"Monday\",\"shortDay\":\"Mon\",\"hours\":\"08:00 - 18:00\",\"isOpen\":true},{\"day\":\"Tuesday\",\"shortDay\":\"Tue\",\"hours\":\"08:00 - 18:00\",\"isOpen\":true},{\"day\":\"Wednesday\",\"shortDay\":\"Wed\",\"hours\":\"08:00 - 18:00\",\"isOpen\":true},{\"day\":\"Thursday\",\"shortDay\":\"Thu\",\"hours\":\"08:00 - 19:00\",\"isOpen\":true},{\"day\":\"Friday\",\"shortDay\":\"Fri\",\"hours\":\"08:00 - 19:00\",\"isOpen\":true},{\"day\":\"Saturday\",\"shortDay\":\"Sat\",\"hours\":\"09:00 - 16:00\",\"isOpen\":true},{\"day\":\"Sunday\",\"shortDay\":\"Sun\",\"hours\":\"Closed\",\"isOpen\":false}]',0,1,3,'2026-09-29 17:55:41','2026-09-29 17:55:41');
/*!40000 ALTER TABLE `salons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_exceptions`
--

DROP TABLE IF EXISTS `schedule_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_exceptions` (
  `id` char(36) NOT NULL ,
  `barber_id` char(36) NOT NULL,
  `exception_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `schedule_exceptions_barber_date_idx` (`barber_id`,`exception_date`),
  CONSTRAINT `schedule_exceptions_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedule_exceptions_time_check` CHECK (`start_time` is null and `end_time` is null or `start_time` is not null and `end_time` is not null and `end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Date specific overrides of the working hours rota.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_exceptions`
--

LOCK TABLES `schedule_exceptions` WRITE;
/*!40000 ALTER TABLE `schedule_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedule_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_categories`
--

DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_categories` (
  `id` char(36) NOT NULL ,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_categories_slug_key` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES ('c0000000-0000-4000-8000-000000000001','hair','Hair','Cuts, fades and restyles.',1,1),('c0000000-0000-4000-8000-000000000002','beard','Beard','Shaping, line work and beard care.',2,1),('c0000000-0000-4000-8000-000000000003','grooming','Grooming','Facial cleanses and finishing rituals.',3,1),('c0000000-0000-4000-8000-000000000004','massage','Massage','Relaxing head, neck, shoulder and full body massage.',4,1),('c0000000-0000-4000-8000-000000000005','manicure','Manicure','Hand care, shaping and polish.',5,1),('c0000000-0000-4000-8000-000000000006','pedicure','Pedicure','Foot care, shaping and polish.',6,1);
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` char(36) NOT NULL ,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `category_id` char(36) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `price_pence` int(11) NOT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_slug_key` (`slug`),
  KEY `services_category_idx` (`category_id`),
  CONSTRAINT `services_category_fkey` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `services_duration_minutes_check` CHECK (`duration_minutes` between 10 and 240),
  CONSTRAINT `services_price_pence_check` CHECK (`price_pence` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Treatment menu. Prices are stored in pence.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1','signature-cut','c0000000-0000-4000-8000-000000000001','Signature Haircut','Consultation, tailored cut, wash, finish, and simple styling plan.',45,4800,1,1,1,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2','skin-fade','c0000000-0000-4000-8000-000000000001','Skin Fade','Precision fade with detailed blending, shape-up, wash, and finish.',50,5200,1,1,2,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3','beard-sculpt','c0000000-0000-4000-8000-000000000002','Beard Sculpt','Beard consultation, shape, line work, hot towel, and conditioning.',30,3200,0,1,3,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4','cut-and-beard','c0000000-0000-4000-8000-000000000001','Hair + Beard','A complete haircut and beard service with a considered, balanced finish.',75,7200,1,1,4,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5','junior-cut','c0000000-0000-4000-8000-000000000001','Junior Cut','A patient, polished haircut for clients aged 12 and under.',35,3400,0,1,5,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa6','premium-grooming','c0000000-0000-4000-8000-000000000003','Premium Grooming','Haircut, beard sculpt, facial cleanse, hot towel, and finishing ritual.',95,9800,1,1,6,'2026-09-29 16:36:33','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa7','relaxing-massage','c0000000-0000-4000-8000-000000000004','Relaxing Massage','Head, neck and shoulder massage with warm oils to release tension.',60,5500,1,1,7,'2026-09-29 17:37:07','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa8','classic-manicure','c0000000-0000-4000-8000-000000000005','Classic Manicure','Nail shaping, cuticle care, hand massage and a finish of your choice.',45,2800,0,1,8,'2026-09-29 17:37:07','2026-09-29 17:37:07'),('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa9','classic-pedicure','c0000000-0000-4000-8000-000000000006','Classic Pedicure','Foot soak, hard skin removal, nail shaping, massage and polish.',50,3200,0,1,9,'2026-09-29 17:37:07','2026-09-29 17:37:07');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `working_hours`
--

DROP TABLE IF EXISTS `working_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `working_hours` (
  `id` char(36) NOT NULL ,
  `barber_id` char(36) NOT NULL,
  `weekday` smallint(6) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_working` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `working_hours_barber_id_weekday_key` (`barber_id`,`weekday`),
  CONSTRAINT `working_hours_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `working_hours_weekday_check` CHECK (`weekday` between 0 and 6),
  CONSTRAINT `working_hours_time_check` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recurring weekly working hours per barber.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `working_hours`
--

LOCK TABLES `working_hours` WRITE;
/*!40000 ALTER TABLE `working_hours` DISABLE KEYS */;
INSERT INTO `working_hours` VALUES ('02ad7ce2-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',1,'09:00:00','20:00:00',1),('02ad8d01-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',1,'09:00:00','20:00:00',1),('02ad8d82-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',2,'09:00:00','20:00:00',1),('02ad8ddd-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',2,'09:00:00','20:00:00',1),('02ad8e51-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',3,'09:00:00','20:00:00',1),('02ad8eac-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',3,'09:00:00','20:00:00',1),('02ad8eff-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',4,'09:00:00','20:00:00',1),('02ad8f55-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',4,'09:00:00','20:00:00',1),('02add455-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',5,'09:00:00','20:00:00',1),('02add535-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',5,'09:00:00','20:00:00',1),('02add5f8-bc24-11f1-890c-380025435a7f','55555555-5555-4555-8555-555555555555',6,'09:00:00','18:00:00',1),('02add6a6-bc24-11f1-890c-380025435a7f','66666666-6666-4666-8666-666666666666',6,'09:00:00','18:00:00',1),('ee29cad3-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',1,'09:00:00','20:00:00',1),('ee29cc1f-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',1,'09:00:00','20:00:00',1),('ee29ccc7-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',1,'09:00:00','20:00:00',1),('ee29cd51-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',1,'09:00:00','20:00:00',1),('ee29cdbf-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',2,'09:00:00','20:00:00',1),('ee29ce36-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',2,'09:00:00','20:00:00',1),('ee29ce9f-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',2,'09:00:00','20:00:00',1),('ee29cf0d-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',2,'09:00:00','20:00:00',1),('ee29cf7f-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',3,'09:00:00','20:00:00',1),('ee29cfee-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',3,'09:00:00','20:00:00',1),('ee29d05b-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',3,'09:00:00','20:00:00',1),('ee29d0cd-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',3,'09:00:00','20:00:00',1),('ee29d137-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',4,'09:00:00','20:00:00',1),('ee29d1b0-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',4,'09:00:00','20:00:00',1),('ee29d227-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',4,'09:00:00','20:00:00',1),('ee29d29f-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',4,'09:00:00','20:00:00',1),('ee29d312-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',5,'09:00:00','20:00:00',1),('ee29d388-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',5,'09:00:00','20:00:00',1),('ee29d408-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',5,'09:00:00','20:00:00',1),('ee29d489-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',5,'09:00:00','20:00:00',1),('ee29d507-bc23-11f1-890c-380025435a7f','33333333-3333-4333-8333-333333333333',6,'09:00:00','18:00:00',1),('ee29d585-bc23-11f1-890c-380025435a7f','11111111-1111-4111-8111-111111111111',6,'09:00:00','18:00:00',1),('ee29d60d-bc23-11f1-890c-380025435a7f','44444444-4444-4444-8444-444444444444',6,'09:00:00','18:00:00',1),('ee29d694-bc23-11f1-890c-380025435a7f','22222222-2222-4222-8222-222222222222',6,'09:00:00','18:00:00',1);
/*!40000 ALTER TABLE `working_hours` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-29 21:52:42

