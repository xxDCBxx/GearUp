-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gearup
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

CREATE DATABASE IF NOT EXISTS `gearup` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gearup`;

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
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deletion_requests`
--

DROP TABLE IF EXISTS `deletion_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deletion_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reason` text NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dr_status` (`status`),
  KEY `idx_dr_user` (`user_id`),
  CONSTRAINT `fk_dr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deletion_requests`
--

LOCK TABLES `deletion_requests` WRITE;
/*!40000 ALTER TABLE `deletion_requests` DISABLE KEYS */;
INSERT INTO `deletion_requests` (`id`, `user_id`, `reason`, `status`, `created_at`) VALUES (1,5,'quit w','approved','2026-05-05 17:01:53');
/*!40000 ALTER TABLE `deletion_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `games`
--

LOCK TABLES `games` WRITE;
/*!40000 ALTER TABLE `games` DISABLE KEYS */;
/*!40000 ALTER TABLE `games` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `wear_rating` varchar(100) DEFAULT NULL,
  `rarity` varchar(100) DEFAULT NULL,
  `float_value` decimal(10,8) DEFAULT NULL,
  `game` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'AK-47 | Ice Coaled','item_images/ak47_icecoaled.png','Factory New','Classified',0.02000000,'cs2'),(2,'M4A4 | Howl','item_images/m4a4_howl.png','Minimal Wear','Contraband',0.09000000,'cs2'),(3,'Karambit | Case Hardened','item_images/karambit_casehardened.png','Factory New','Covert',0.00100000,'cs2'),(4,'AWP | Dragon Lore','item_images/awp_dragonlore.png','Factory New','Covert',0.01000000,'cs2'),(5,'Desert Eagle | Blaze','item_images/deserteagle_blaze.png','Factory New','Restricted',0.03000000,'cs2'),(6,'Dragonclaw Hook','item_images/dragonclaw_hook.png','Standard','Immortal',NULL,'dota2'),(7,'Timebreaker','item_images/timebreaker.png','Standard','Immortal',NULL,'dota2'),(8,'Kantusa the Script Sword','item_images/kantusa.png','Standard','Legendary',NULL,'dota2'),(9,'Fiery Soul of the Slayer','item_images/arcana_lina.png','Standard','Arcana',NULL,'dota2'),(10,'Mace of Aeons','item_images/mace_aeons.png','Standard','Immortal',NULL,'dota2'),(11,'Alien Red','item_images/alien_red.png','Standard','Legendary',NULL,'rust'),(12,'Big Grin','item_images/big_grin.png','Standard','Legendary',NULL,'rust'),(13,'Glory AK47','item_images/glory_ak47.png','Standard','High Quality',NULL,'rust'),(14,'Fireman Jacket','item_images/fireman_jacket.png','Standard','Rare',NULL,'rust'),(15,'Tempered Mask','item_images/tempered_mask.png','Standard','High Quality',NULL,'rust'),(16,'Australium Rocket Launcher','item_images/australium_rocket_launcher.png','Strange','Legendary',NULL,'tf2'),(17,'Team Captain','item_images/team_captain.png','Unique','Rare',NULL,'tf2'),(18,'Max\'s Severed Head','item_images/max_severedhead.png','Unique','Legendary',NULL,'tf2'),(19,'Golden Frying Pan','item_images/golden_fryingpan.png','Strange','Exotic',NULL,'tf2'),(20,'Bill\'s Hat','item_images/bill_hat.png','Unique','Rare',NULL,'tf2'),(21,'Placeholder Duplicate Item',NULL,NULL,NULL,NULL,'unknown');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_history`
--

DROP TABLE IF EXISTS `market_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `seller_id` (`seller_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `market_history_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `market_history_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `market_history_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_history`
--

LOCK TABLES `market_history` WRITE;
/*!40000 ALTER TABLE `market_history` DISABLE KEYS */;
INSERT INTO `market_history` VALUES (1,2,1,1,300.00,'2026-05-05 16:48:40'),(2,2,1,1,5.00,'2026-05-05 17:37:42'),(3,2,1,4,5000.00,'2026-05-05 17:52:02');
/*!40000 ALTER TABLE `market_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_listings`
--

DROP TABLE IF EXISTS `market_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','sold','cancelled','paused') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_ml_status` (`status`),
  KEY `idx_ml_user_id` (`user_id`),
  CONSTRAINT `fk_market_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_market_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_listings`
--

LOCK TABLES `market_listings` WRITE;
/*!40000 ALTER TABLE `market_listings` DISABLE KEYS */;
INSERT INTO `market_listings` (`id`, `user_id`, `item_id`, `price`, `created_at`) VALUES (48,1,15,240.00,'2026-04-28 18:28:14'),(49,1,19,5000.00,'2026-04-28 18:28:14'),(58,1,3,10000.00,'2026-05-05 15:15:32');
/*!40000 ALTER TABLE `market_listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `revert_requests`
--

DROP TABLE IF EXISTS `revert_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revert_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `type` enum('market','trade') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `reason` text NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rr_status` (`status`),
  KEY `idx_rr_user` (`user_id`),
  CONSTRAINT `fk_rr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revert_requests`
--

LOCK TABLES `revert_requests` WRITE;
/*!40000 ALTER TABLE `revert_requests` DISABLE KEYS */;
INSERT INTO `revert_requests` (`id`, `user_id`, `type`, `reference_id`, `reason`, `status`, `created_at`) VALUES (1,2,'market',1,'Accidental buy','approved','2026-05-05 16:49:34'),(2,2,'trade',1,'accidental','approved','2026-05-05 17:30:50'),(3,2,'market',1,'accidental','denied','2026-05-05 17:33:23'),(4,2,'market',2,'accidental buy','approved','2026-05-05 17:37:54'),(5,1,'trade',2,'accidental','approved','2026-05-05 17:49:24'),(6,2,'market',3,'accidental buy','pending','2026-05-05 17:52:11'),(7,1,'trade',3,'accidental','pending','2026-05-05 17:57:55'),(8,1,'trade',3,'accidental','pending','2026-05-05 18:03:32');
/*!40000 ALTER TABLE `revert_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_history`
--

DROP TABLE IF EXISTS `trade_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `user1_items` varchar(255) NOT NULL,
  `user2_items` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_history`
--

LOCK TABLES `trade_history` WRITE;
/*!40000 ALTER TABLE `trade_history` DISABLE KEYS */;
INSERT INTO `trade_history` VALUES (1,2,1,'14','20','2026-05-05 17:30:09'),(2,2,1,'5','18','2026-05-05 17:49:13'),(3,2,1,'8,5','10','2026-05-05 17:57:47');
/*!40000 ALTER TABLE `trade_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_offers`
--

DROP TABLE IF EXISTS `trade_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `sender_item_id` varchar(255) NOT NULL,
  `receiver_item_id` varchar(255) NOT NULL,
  `status` enum('pending','accepted','declined','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_offers`
--

LOCK TABLES `trade_offers` WRITE;
/*!40000 ALTER TABLE `trade_offers` DISABLE KEYS */;
INSERT INTO `trade_offers` VALUES (1,1,1,'1','2','declined','2026-04-28 17:42:53'),(2,2,1,'3','4','cancelled','2026-04-28 19:08:23'),(3,2,1,'3','4','cancelled','2026-04-28 19:15:45'),(4,2,1,'9,10,3','4','cancelled','2026-05-04 12:07:08'),(5,2,1,'9,10,3','4','accepted','2026-05-04 13:36:25'),(6,2,1,'3','4','accepted','2026-05-05 14:34:17'),(7,2,1,'3','4','accepted','2026-05-05 14:59:30'),(8,2,1,'10','4','accepted','2026-05-05 15:00:05'),(9,2,1,'9','4','accepted','2026-05-05 15:13:36'),(10,2,1,'14','20','accepted','2026-05-05 17:29:25'),(11,2,1,'5','18','accepted','2026-05-05 17:49:03'),(12,2,1,'8,5','4','cancelled','2026-05-05 17:51:55'),(13,2,1,'8,5','10','accepted','2026-05-05 17:57:23');
/*!40000 ALTER TABLE `trade_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_items`
--

DROP TABLE IF EXISTS `user_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_inventory_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_items`
--

LOCK TABLES `user_items` WRITE;
/*!40000 ALTER TABLE `user_items` DISABLE KEYS */;
INSERT INTO `user_items` VALUES (42,1,2),(43,1,6),(44,1,7),(45,1,11),(46,1,12),(47,1,13),(48,1,16),(54,1,9),(59,1,17),(63,1,5),(64,1,8),(65,2,14),(67,1,20),(68,1,1),(69,1,18),(70,2,4),(71,2,10);
/*!40000 ALTER TABLE `user_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `picture` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `credits` int(11) NOT NULL DEFAULT 10000,
  `is_admin` tinyint(1) DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `name`, `picture`, `email`, `password`, `credits`, `is_admin`) VALUES (1,'user1','uploads/user_1_1778001172.jpg','user1@gmail.com','$2y$10$abcdefghijklmnopqrstuup2sflzdS6S5FvdNjAk9faR2QjpMgeuO',12257,0),(2,'user2','','user@gmail.com','$2y$10$faYBeLLjauYW0ISq.zdE7u0LayhsjiF.cN089kPgqhN0oETHYulia',7743,0),(3,'admin','','admin@gearup.com','$2y$10$W6hU1Q8OpJTfJc1uNI0pAesBUprhIJq5yG4XJsBHwJxUgrEP7CGmK',10000,1),(6,'user3','','user3@gmail.com','$2y$10$y7YyV7iGFDK4N8.GRxeLLuEyQjGAI9iOAh3NcloCDCMv9MaKr9m3S',99999,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-06  2:07:18

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('sale','offer','credit') NOT NULL DEFAULT 'sale',
  `buyer_id` int(11) NULL,
  `seller_id` int(11) NULL,
  `item_id` int(11) NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_buyer` (`buyer_id`),
  KEY `idx_tx_seller` (`seller_id`),
  KEY `idx_tx_type` (`type`),
  KEY `idx_tx_created` (`created_at`),
  CONSTRAINT `fk_tx_buyer`  FOREIGN KEY (`buyer_id`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `code` varchar(6) NOT NULL,
    `expiration` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;