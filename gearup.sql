-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2026 at 05:58 PM
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
-- Database: `gearup`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deletion_requests`
--

CREATE TABLE `deletion_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deletion_requests`
--

INSERT INTO `deletion_requests` (`id`, `user_id`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 5, 'quit w', 'approved', NULL, NULL, '2026-05-05 17:01:53');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `wear_rating` varchar(100) DEFAULT NULL,
  `rarity` varchar(100) DEFAULT NULL,
  `float_value` decimal(10,8) DEFAULT NULL,
  `game` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `image`, `wear_rating`, `rarity`, `float_value`, `game`) VALUES
(1, 'AK-47 | Ice Coaled', 'item_images/ak47_icecoaled.png', 'Factory New', 'Classified', 0.02000000, 'cs2'),
(2, 'M4A4 | Howl', 'item_images/m4a4_howl.png', 'Minimal Wear', 'Contraband', 0.09000000, 'cs2'),
(3, 'Karambit | Case Hardened', 'item_images/karambit_casehardened.png', 'Factory New', 'Covert', 0.00100000, 'cs2'),
(4, 'AWP | Dragon Lore', 'item_images/awp_dragonlore.png', 'Factory New', 'Covert', 0.01000000, 'cs2'),
(5, 'Desert Eagle | Blaze', 'item_images/deserteagle_blaze.png', 'Factory New', 'Restricted', 0.03000000, 'cs2'),
(6, 'Dragonclaw Hook', 'item_images/dragonclaw_hook.png', 'Standard', 'Immortal', NULL, 'dota2'),
(7, 'Timebreaker', 'item_images/timebreaker.png', 'Standard', 'Immortal', NULL, 'dota2'),
(8, 'Kantusa the Script Sword', 'item_images/kantusa.png', 'Standard', 'Legendary', NULL, 'dota2'),
(9, 'Fiery Soul of the Slayer', 'item_images/arcana_lina.png', 'Standard', 'Arcana', NULL, 'dota2'),
(10, 'Mace of Aeons', 'item_images/mace_aeons.png', 'Standard', 'Immortal', NULL, 'dota2'),
(11, 'Alien Red', 'item_images/alien_red.png', 'Standard', 'Legendary', NULL, 'rust'),
(12, 'Big Grin', 'item_images/big_grin.png', 'Standard', 'Legendary', NULL, 'rust'),
(13, 'Glory AK47', 'item_images/glory_ak47.png', 'Standard', 'High Quality', NULL, 'rust'),
(14, 'Fireman Jacket', 'item_images/fireman_jacket.png', 'Standard', 'Rare', NULL, 'rust'),
(15, 'Tempered Mask', 'item_images/tempered_mask.png', 'Standard', 'High Quality', NULL, 'rust'),
(16, 'Australium Rocket Launcher', 'item_images/australium_rocket_launcher.png', 'Strange', 'Legendary', NULL, 'tf2'),
(17, 'Team Captain', 'item_images/team_captain.png', 'Unique', 'Rare', NULL, 'tf2'),
(18, 'Max\'s Severed Head', 'item_images/max_severedhead.png', 'Unique', 'Legendary', NULL, 'tf2'),
(19, 'Golden Frying Pan', 'item_images/golden_fryingpan.png', 'Strange', 'Exotic', NULL, 'tf2'),
(20, 'Bill\'s Hat', 'item_images/bill_hat.png', 'Unique', 'Rare', NULL, 'tf2'),
(21, 'Placeholder Duplicate Item', NULL, NULL, NULL, NULL, 'unknown');

-- --------------------------------------------------------

--
-- Table structure for table `market_history`
--

CREATE TABLE `market_history` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_history`
--

INSERT INTO `market_history` (`id`, `buyer_id`, `seller_id`, `item_id`, `price`, `created_at`) VALUES
(1, 2, 1, 1, 300.00, '2026-05-05 16:48:40'),
(2, 2, 1, 1, 5.00, '2026-05-05 17:37:42'),
(3, 2, 1, 4, 5000.00, '2026-05-05 17:52:02'),
(4, 7, 1, 3, 10000.00, '2026-05-06 11:22:32');

-- --------------------------------------------------------

--
-- Table structure for table `market_listings`
--

CREATE TABLE `market_listings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','sold','cancelled','paused') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_listings`
--

INSERT INTO `market_listings` (`id`, `user_id`, `item_id`, `price`, `status`, `created_at`) VALUES
(48, 1, 15, 240.00, 'active', '2026-04-28 18:28:14'),
(49, 1, 19, 5000.00, 'active', '2026-04-28 18:28:14'),
(65, 1, 18, 500.00, 'active', '2026-05-06 14:39:33'),
(66, 1, 1, 350.00, 'active', '2026-05-06 14:39:37'),
(67, 1, 20, 100.00, 'active', '2026-05-06 14:39:44'),
(68, 1, 8, 750.00, 'active', '2026-05-06 14:39:49'),
(69, 1, 5, 1500.00, 'active', '2026-05-06 14:39:58'),
(70, 1, 17, 400.00, 'active', '2026-05-06 14:40:10');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expiration` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `revert_requests`
--

CREATE TABLE `revert_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `type` enum('market','trade') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `revert_requests`
--

INSERT INTO `revert_requests` (`id`, `user_id`, `transaction_id`, `amount`, `type`, `reference_id`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 2, NULL, 0.00, 'market', 1, 'Accidental buy', 'approved', NULL, NULL, '2026-05-05 16:49:34'),
(2, 2, NULL, 0.00, 'trade', 1, 'accidental', 'approved', NULL, NULL, '2026-05-05 17:30:50'),
(3, 2, NULL, 0.00, 'market', 1, 'accidental', 'denied', NULL, NULL, '2026-05-05 17:33:23'),
(4, 2, NULL, 0.00, 'market', 2, 'accidental buy', 'approved', NULL, NULL, '2026-05-05 17:37:54'),
(5, 1, NULL, 0.00, 'trade', 2, 'accidental', 'approved', NULL, NULL, '2026-05-05 17:49:24'),
(6, 2, NULL, 0.00, 'market', 3, 'accidental buy', 'pending', NULL, NULL, '2026-05-05 17:52:11'),
(7, 1, NULL, 0.00, 'trade', 3, 'accidental', 'pending', NULL, NULL, '2026-05-05 17:57:55'),
(8, 1, NULL, 0.00, 'trade', 3, 'accidental', 'pending', NULL, NULL, '2026-05-05 18:03:32'),
(9, 7, NULL, 0.00, 'market', 4, 'quit w', 'approved', 3, '2026-05-06 19:24:06', '2026-05-06 11:23:41');

-- --------------------------------------------------------

--
-- Table structure for table `topup_requests`
--

CREATE TABLE `topup_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `verification_code` varchar(50) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `topup_requests`
--

INSERT INTO `topup_requests` (`id`, `user_id`, `amount`, `verification_code`, `email_verified`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 7, 1.00, '176575', 1, 'approved', 3, '2026-05-06 23:56:54', '2026-05-06 15:50:36'),
(2, 7, 5000.00, '227574', 0, 'pending', NULL, NULL, '2026-05-06 15:57:13'),
(3, 7, 5000.00, '879862', 1, 'approved', 3, '2026-05-06 23:58:05', '2026-05-06 15:57:18');

-- --------------------------------------------------------

--
-- Table structure for table `trade_history`
--

CREATE TABLE `trade_history` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `user1_items` varchar(255) NOT NULL,
  `user2_items` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trade_history`
--

INSERT INTO `trade_history` (`id`, `user1_id`, `user2_id`, `user1_items`, `user2_items`, `created_at`) VALUES
(1, 2, 1, '14', '20', '2026-05-05 17:30:09'),
(2, 2, 1, '5', '18', '2026-05-05 17:49:13'),
(3, 2, 1, '8,5', '10', '2026-05-05 17:57:47');

-- --------------------------------------------------------

--
-- Table structure for table `trade_offers`
--

CREATE TABLE `trade_offers` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `sender_item_id` varchar(255) NOT NULL,
  `receiver_item_id` varchar(255) NOT NULL,
  `status` enum('pending','accepted','declined','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trade_offers`
--

INSERT INTO `trade_offers` (`id`, `sender_id`, `receiver_id`, `sender_item_id`, `receiver_item_id`, `status`, `created_at`) VALUES
(1, 1, 1, '1', '2', 'declined', '2026-04-28 17:42:53'),
(2, 2, 1, '3', '4', 'cancelled', '2026-04-28 19:08:23'),
(3, 2, 1, '3', '4', 'cancelled', '2026-04-28 19:15:45'),
(4, 2, 1, '9,10,3', '4', 'cancelled', '2026-05-04 12:07:08'),
(5, 2, 1, '9,10,3', '4', 'accepted', '2026-05-04 13:36:25'),
(6, 2, 1, '3', '4', 'accepted', '2026-05-05 14:34:17'),
(7, 2, 1, '3', '4', 'accepted', '2026-05-05 14:59:30'),
(8, 2, 1, '10', '4', 'accepted', '2026-05-05 15:00:05'),
(9, 2, 1, '9', '4', 'accepted', '2026-05-05 15:13:36'),
(10, 2, 1, '14', '20', 'accepted', '2026-05-05 17:29:25'),
(11, 2, 1, '5', '18', 'accepted', '2026-05-05 17:49:03'),
(12, 2, 1, '8,5', '4', 'cancelled', '2026-05-05 17:51:55'),
(13, 2, 1, '8,5', '10', 'accepted', '2026-05-05 17:57:23');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `type` enum('sale','offer','credit') NOT NULL DEFAULT 'sale',
  `buyer_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `picture` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `credits` int(11) NOT NULL DEFAULT 10000,
  `is_admin` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `picture`, `email`, `password`, `credits`, `is_admin`, `deleted_at`) VALUES
(1, 'user1', 'uploads/user_1_1778001172.jpg', 'user1@gmail.com', '$2y$10$abcdefghijklmnopqrstuup2sflzdS6S5FvdNjAk9faR2QjpMgeuO', 22257, 0, NULL),
(2, 'user2', '', 'user@gmail.com', '$2y$10$faYBeLLjauYW0ISq.zdE7u0LayhsjiF.cN089kPgqhN0oETHYulia', 7743, 0, NULL),
(3, 'admin', '', 'admin@gearup.com', '$2y$10$W6hU1Q8OpJTfJc1uNI0pAesBUprhIJq5yG4XJsBHwJxUgrEP7CGmK', 10000, 1, NULL),
(6, 'user3', '', 'user3@gmail.com', '$2y$10$y7YyV7iGFDK4N8.GRxeLLuEyQjGAI9iOAh3NcloCDCMv9MaKr9m3S', 99999, 0, NULL),
(7, 'brevin', '', 'brevincortez03@gmail.com', '$2y$10$gDK9E1gUgzDiLI051aueke8OmqDIVMfw0AgEkt3ORSALTkmydMW/W', 7001, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_items`
--

CREATE TABLE `user_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_items`
--

INSERT INTO `user_items` (`id`, `user_id`, `item_id`) VALUES
(42, 1, 2),
(43, 1, 6),
(44, 1, 7),
(45, 1, 11),
(46, 1, 12),
(47, 1, 13),
(48, 1, 16),
(54, 1, 9),
(65, 2, 14),
(70, 2, 4),
(71, 2, 10),
(72, 7, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dr_status` (`status`),
  ADD KEY `idx_dr_user` (`user_id`),
  ADD KEY `fk_dr_reviewer` (`reviewed_by`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `market_history`
--
ALTER TABLE `market_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `market_listings`
--
ALTER TABLE `market_listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_ml_status` (`status`),
  ADD KEY `idx_ml_user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `revert_requests`
--
ALTER TABLE `revert_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rr_status` (`status`),
  ADD KEY `idx_rr_user` (`user_id`),
  ADD KEY `fk_rr_reviewer` (`reviewed_by`);

--
-- Indexes for table `topup_requests`
--
ALTER TABLE `topup_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trade_history`
--
ALTER TABLE `trade_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trade_offers`
--
ALTER TABLE `trade_offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tx_buyer` (`buyer_id`),
  ADD KEY `idx_tx_seller` (`seller_id`),
  ADD KEY `idx_tx_type` (`type`),
  ADD KEY `idx_tx_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `user_items`
--
ALTER TABLE `user_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `market_history`
--
ALTER TABLE `market_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `market_listings`
--
ALTER TABLE `market_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `revert_requests`
--
ALTER TABLE `revert_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `topup_requests`
--
ALTER TABLE `topup_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trade_history`
--
ALTER TABLE `trade_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trade_offers`
--
ALTER TABLE `trade_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_items`
--
ALTER TABLE `user_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  ADD CONSTRAINT `fk_dr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_history`
--
ALTER TABLE `market_history`
  ADD CONSTRAINT `market_history_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `market_history_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `market_history_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_listings`
--
ALTER TABLE `market_listings`
  ADD CONSTRAINT `fk_market_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_market_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `revert_requests`
--
ALTER TABLE `revert_requests`
  ADD CONSTRAINT `fk_rr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topup_requests`
--
ALTER TABLE `topup_requests`
  ADD CONSTRAINT `fk_topup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tx_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_items`
--
ALTER TABLE `user_items`
  ADD CONSTRAINT `fk_inventory_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
