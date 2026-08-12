-- MySQL dump 10.13  Distrib 8.4.9, for Linux (x86_64)
--
-- Host: localhost    Database: app_db
-- ------------------------------------------------------
-- Server version	8.4.9

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `client_types`
--

DROP TABLE IF EXISTS `client_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_types`
--

LOCK TABLES `client_types` WRITE;
/*!40000 ALTER TABLE `client_types` DISABLE KEYS */;
INSERT INTO `client_types` VALUES (1,'PR','2026-07-06 14:47:13'),(2,'CO','2026-07-06 14:47:13');
/*!40000 ALTER TABLE `client_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type_id` int unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `client_code` varchar(30) NOT NULL,
  `city` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `province` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_code` (`client_code`),
  KEY `idx_clients_type` (`type_id`),
  CONSTRAINT `fk_clients_client_type` FOREIGN KEY (`type_id`) REFERENCES `client_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (30,'Blue Cedar Studio',2,1,'BLUECEDAR','London','ENG','2026-08-12 16:14:06'),(31,'Fluffy Nina Corp',2,1,'FLUFFYNINA','Cork','C','2026-08-12 16:14:06'),(32,'Green Harbor Labs',2,0,'GREENHARBOR','Boston','MA','2026-08-12 16:14:06'),(33,'Kumo Medical Group',2,1,'KUMOMED','Osaka','27','2026-08-12 16:14:06'),(34,'Maison Lune Sante',2,1,'MAISONLUNE','Paris','IDF','2026-08-12 16:14:06'),(35,'Nordstern Physio GmbH',2,1,'NORDSTERN','Munich','BY','2026-08-12 16:14:06'),(36,'Sakura Health Lab',2,1,'SAKURAHEALTH','Yokohama','14','2026-08-12 16:14:06'),(37,'Velvet Fox Design',2,0,'VELVETFOX','Portland','OR','2026-08-12 16:14:06'),(38,'Aisling Byrne',1,1,'AISLINGBYRNE','Dublin','D','2026-08-12 16:14:06'),(39,'Camille Moreau',1,1,'CAMILLEMORE','Paris','IDF','2026-08-12 16:14:06'),(40,'Emilie Laurent',1,1,'EMILIELAUR','Lyon','ARA','2026-08-12 16:14:06'),(41,'Jonas Weber',1,1,'JONASWEBER','Berlin','BE','2026-08-12 16:14:06'),(42,'Kenji Mori',1,1,'KENJIMORI','Kyoto','26','2026-08-12 16:14:06'),(43,'Luca Ferri',1,1,'LUCAFERRI','Verona','VR','2026-08-12 16:14:06'),(44,'Maya Collins',1,1,'MAYACOLLINS','New York','NY','2026-08-12 16:14:06'),(45,'Mei Aoki',1,1,'MEIAOKI','Osaka','27','2026-08-12 16:14:06'),(46,'Nina Bellori',1,1,'NINABEL','Bassano del Grappa','VI','2026-08-12 16:14:06'),(47,'Nora Lind',1,1,'NORALIND','Hamburg','HH','2026-08-12 16:14:06'),(48,'Oliver Grant',1,0,'OLIVERGRANT','Bristol','ENG','2026-08-12 16:14:06'),(49,'Sora Tanaka',1,1,'SORATANAKA','Tokyo','13','2026-08-12 16:14:06');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `discount_rule_id` int unsigned NOT NULL,
  `usage_cap` int DEFAULT NULL,
  `exp_date` datetime DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `uq_client_rule` (`client_id`,`discount_rule_id`),
  KEY `idx_coupon_client` (`client_id`),
  KEY `idx_coupon_rule` (`discount_rule_id`),
  CONSTRAINT `fk_coupon_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_coupon_rule` FOREIGN KEY (`discount_rule_id`) REFERENCES `discount_rules` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (33,46,84,NULL,'2027-06-30 23:59:59','MED-ALL-15-NINABEL',1,'2026-08-12 16:14:06'),(34,38,84,NULL,'2027-02-28 23:59:59','MED-ALL-15-AISLINGBYRNE',0,'2026-08-12 16:14:06'),(35,42,84,NULL,'2027-08-31 23:59:59','MED-ALL-15-KENJIMORI',1,'2026-08-12 16:14:06'),(36,36,84,NULL,'2028-06-30 23:59:59','MED-ALL-15-SAKURAHEALTH',1,'2026-08-12 16:14:06'),(37,34,84,NULL,'2027-09-30 23:59:59','MED-ALL-15-MAISONLUNE',1,'2026-08-12 16:14:06'),(38,40,85,NULL,'2028-01-31 23:59:59','PHYS-ALL-10-EMILIELAUR',1,'2026-08-12 16:14:06'),(39,36,85,NULL,'2027-07-31 23:59:59','PHYS-ALL-10-SAKURAHEALTH',1,'2026-08-12 16:14:06'),(40,34,85,NULL,'2027-04-30 23:59:59','PHYS-ALL-10-MAISONLUNE',1,'2026-08-12 16:14:06'),(41,49,86,NULL,'2027-12-31 23:59:59','ALL-ALL-12-SORATANAKA',1,'2026-08-12 16:14:06'),(42,44,86,NULL,'2027-10-31 23:59:59','ALL-ALL-12-MAYACOLLINS',1,'2026-08-12 16:14:06'),(43,31,86,NULL,'2028-03-31 23:59:59','ALL-ALL-12-FLUFFYNINA',1,'2026-08-12 16:14:06'),(44,41,87,2,'2027-04-30 23:59:59','MED-FIRST-20-JONASWEBER',1,'2026-08-12 16:14:06'),(45,31,87,5,'2027-11-30 23:59:59','MED-FIRST-20-FLUFFYNINA',1,'2026-08-12 16:14:06'),(46,37,87,15,'2027-01-31 23:59:59','MED-FIRST-20-VELVETFOX',0,'2026-08-12 16:14:06'),(47,46,88,1,'2027-03-31 23:59:59','PHYS-FIRST-25-NINABEL',1,'2026-08-12 16:14:06'),(48,39,88,3,'2027-05-31 23:59:59','PHYS-FIRST-25-CAMILLEMORE',1,'2026-08-12 16:14:06'),(49,35,88,25,'2027-12-31 23:59:59','PHYS-FIRST-25-NORDSTERN',1,'2026-08-12 16:14:06'),(50,45,89,1,'2027-09-30 23:59:59','ALL-FIRST-30-MEIAOKI',1,'2026-08-12 16:14:06'),(64,33,84,NULL,'2027-01-01 23:59:59','MED-ALL-15-KUMOMED',1,'2026-08-12 16:15:18'),(65,30,87,5,'2026-12-31 23:59:59','MED-FIRST-20-BLUECEDAR',0,'2026-08-12 16:17:15');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discount_rules`
--

DROP TABLE IF EXISTS `discount_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_category_id` int unsigned NOT NULL,
  `visit_type_id` int unsigned NOT NULL,
  `discount_perc` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_discount_rule` (`service_category_id`,`visit_type_id`,`discount_perc`),
  KEY `idx_discount_service` (`service_category_id`),
  KEY `idx_discount_visit` (`visit_type_id`),
  CONSTRAINT `fk_discount_rules_service_category` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `fk_discount_rules_visit_type` FOREIGN KEY (`visit_type_id`) REFERENCES `visit_types` (`id`),
  CONSTRAINT `chk_discount_perc` CHECK ((`discount_perc` between 0 and 100))
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discount_rules`
--

LOCK TABLES `discount_rules` WRITE;
/*!40000 ALTER TABLE `discount_rules` DISABLE KEYS */;
INSERT INTO `discount_rules` VALUES (84,2,2,15,'2026-08-12 16:14:06'),(85,1,2,10,'2026-08-12 16:14:06'),(86,3,2,12,'2026-08-12 16:14:06'),(87,2,1,20,'2026-08-12 16:14:06'),(88,1,1,25,'2026-08-12 16:14:06'),(89,3,1,30,'2026-08-12 16:14:06');
/*!40000 ALTER TABLE `discount_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_categories`
--

DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES (1,'Physiotherapy','2026-07-06 14:47:13'),(2,'Medical','2026-07-06 14:47:13'),(3,'All services','2026-07-06 14:47:13');
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'to check whether user is still an employee or not',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (16,'Demo','Admin','admin@companydomain.com','$2y$12$a0OELYl9Xd2lxuYPlltr/el0Z2gbn21XPFg92lv0h0b5jrH94rJge','2026-08-12 16:14:06','admin',1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visit_types`
--

DROP TABLE IF EXISTS `visit_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visit_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visit` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visit_types`
--

LOCK TABLES `visit_types` WRITE;
/*!40000 ALTER TABLE `visit_types` DISABLE KEYS */;
INSERT INTO `visit_types` VALUES (1,'First visit only','2026-07-06 14:47:13'),(2,'All visits','2026-07-06 14:47:13');
/*!40000 ALTER TABLE `visit_types` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-12 20:16:18
