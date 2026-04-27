-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 05:31 PM
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
(3, 'Dragonclaw Hook', 'item_images/dragonclaw_hook.png', 'Standard', 'Immortal', NULL, 'dota2'),
(4, 'Timebreaker', 'item_images/timebreaker.png', 'Standard', 'Immortal', NULL, 'dota2'),
(5, 'Alien Red', 'item_images/alien_red.png', 'Standard', 'Legendary', NULL, 'rust'),
(6, 'Big Grin', 'item_images/big_grin.png', 'Standard', 'Legendary', NULL, 'rust'),
(7, 'Australium Rocket Launcher', 'item_images/australium_rocket_launcher.png', 'Strange', 'Legendary', NULL, 'tf2'),
(8, 'Team Captain', 'item_images/team_captain.png', 'Unique', 'Rare', NULL, 'tf2');

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
(1, 1, 1, 15.50, '2026-04-27 15:10:37'),
(2, 1, 2, 2500.00, '2026-04-27 15:10:37'),
(3, 1, 3, 180.00, '2026-04-27 15:10:37'),
(4, 1, 4, 35.25, '2026-04-27 15:10:37'),
(5, 1, 5, 65.00, '2026-04-27 15:10:37'),
(6, 1, 6, 450.00, '2026-04-27 15:10:37'),
(7, 1, 7, 120.00, '2026-04-27 15:10:37'),
(8, 1, 8, 5.00, '2026-04-27 15:10:37');

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
  `credits` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `picture`, `email`, `password`, `credits`) VALUES
(1, 'test', '', 'test@gmail.com', '$2y$10$MRDTBbmZNUs6u5O2jXbky.0E.W6IF29umhOK/vDOVzK213bzZ/9Uq', 0);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `email` (`email`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `market_listings`
--
ALTER TABLE `market_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `market_listings`
--
ALTER TABLE `market_listings`
  ADD CONSTRAINT `fk_market_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_market_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
