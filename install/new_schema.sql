-- Database schema for Night Stalker
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS `price_history`;
DROP TABLE IF EXISTS `trades`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `cryptocurrencies`;

-- Create tables with proper constraints
CREATE TABLE `cryptocurrencies` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `created_at` datetime NOT NULL,
  `added_to_system` datetime DEFAULT current_timestamp(),
  `age_hours` int(11) DEFAULT 0,
  `market_cap` decimal(30,2) DEFAULT 0,
  `volume` decimal(30,2) DEFAULT 0,
  `price` decimal(20,8) DEFAULT 0,
  `price_change_24h` decimal(10,2) DEFAULT 0,
  `last_updated` datetime DEFAULT NULL,
  `is_trending` tinyint(1) DEFAULT 0,
  `volume_spike` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trending` (`is_trending`),
  KEY `idx_volume_spike` (`volume_spike`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `price_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coin_id` varchar(50) NOT NULL,
  `price` decimal(20,8) NOT NULL,
  `volume` decimal(30,2) NOT NULL,
  `market_cap` decimal(30,2) NOT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_coin_id` (`coin_id`),
  KEY `idx_recorded_at` (`recorded_at`),
  CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`coin_id`) REFERENCES `cryptocurrencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_level` enum('info','warning','error','critical') NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_level` (`log_level`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `trades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coin_id` varchar(50) NOT NULL,
  `trade_type` enum('buy','sell') NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `price` decimal(20,8) NOT NULL,
  `total_value` decimal(30,2) NOT NULL,
  `trade_time` datetime DEFAULT current_timestamp(),
  `profit_loss` decimal(30,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_coin_id` (`coin_id`),
  KEY `idx_trade_time` (`trade_time`),
  CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`coin_id`) REFERENCES `cryptocurrencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
