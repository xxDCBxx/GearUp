-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2026 at 03:50 PM
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
(20, 'Bill\'s Hat', 'item_images/bill_hat.png', 'Unique', 'Rare', NULL, 'tf2');

-- --------------------------------------------------------

--
-- Table structure for table `market_listings`
--

CREATE TABLE `market_listings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_listings`
--

INSERT INTO `market_listings` (`id`, `user_id`, `item_id`, `price`, `created_at`) VALUES
(42, 1, 4, 8500.00, '2026-04-28 18:28:14'),
(43, 1, 5, 450.00, '2026-04-28 18:28:14'),
(44, 1, 8, 180.00, '2026-04-28 18:28:14'),
(47, 1, 14, 120.00, '2026-04-28 18:28:14'),
(48, 1, 15, 240.00, '2026-04-28 18:28:14'),
(49, 1, 19, 5000.00, '2026-04-28 18:28:14');

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
(3, 2, 1, '3', '4', 'pending', '2026-04-28 19:15:45'),
(4, 2, 1, '9,10,3', '4', 'cancelled', '2026-05-04 12:07:08'),
(5, 2, 1, '9,10,3', '4', 'pending', '2026-05-04 13:36:25');

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
  `credits` int(11) NOT NULL DEFAULT 10000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `picture`, `email`, `password`, `credits`) VALUES
(1, 'user1', '', 'user1@gmail.com', '$2y$10$abcdefghijklmnopqrstuup2sflzdS6S5FvdNjAk9faR2QjpMgeuO', 11507),
(2, 'user2', '', 'user@gmail.com', '$2y$10$faYBeLLjauYW0ISq.zdE7u0LayhsjiF.cN089kPgqhN0oETHYulia', 8493);

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
(41, 1, 1),
(42, 1, 2),
(43, 1, 6),
(44, 1, 7),
(45, 1, 11),
(46, 1, 12),
(47, 1, 13),
(48, 1, 16),
(50, 1, 18),
(53, 1, 20),
(54, 2, 9),
(56, 2, 10),
(58, 2, 3),
(59, 1, 17);

--
-- Indexes for dumped tables
--

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
-- Indexes for table `market_listings`
--
ALTER TABLE `market_listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `trade_offers`
--
ALTER TABLE `trade_offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `email` (`email`);

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
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `market_listings`
--
ALTER TABLE `market_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `trade_offers`
--
ALTER TABLE `trade_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_items`
--
ALTER TABLE `user_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `market_listings`
--
ALTER TABLE `market_listings`
  ADD CONSTRAINT `fk_market_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_market_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
