-- Database schema for Night Stalker
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `volume_spike` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `price_history` (
  `id` int(11) NOT NULL,
  `coin_id` varchar(50) NOT NULL,
  `price` decimal(20,8) NOT NULL,
  `volume` decimal(30,2) NOT NULL,
  `market_cap` decimal(30,2) NOT NULL,
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_level` enum('info','warning','error','critical') NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `trades` (
  `id` int(11) NOT NULL,
  `coin_id` varchar(50) NOT NULL,
  `trade_type` enum('buy','sell') NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `price` decimal(20,8) NOT NULL,
  `total_value` decimal(30,2) NOT NULL,
  `trade_time` datetime DEFAULT current_timestamp(),
  `profit_loss` decimal(30,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `cryptocurrencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trending` (`is_trending`),
  ADD KEY `idx_volume_spike` (`volume_spike`);

ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coin_id` (`coin_id`),
  ADD KEY `idx_recorded_at` (`recorded_at`);

ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_level` (`log_level`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coin_id` (`coin_id`),
  ADD KEY `idx_trade_time` (`trade_time`);

ALTER TABLE `price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `price_history`
  ADD CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`coin_id`) REFERENCES `cryptocurrencies` (`id`);

ALTER TABLE `trades`
  ADD CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`coin_id`) REFERENCES `cryptocurrencies` (`id`);

CREATE TABLE IF NOT EXISTS coins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    current_price DECIMAL(24,8),
    price_change_24h DECIMAL(8,4),
    market_cap DECIMAL(32,8),
    volume_24h DECIMAL(32,8),
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_trending BOOLEAN DEFAULT FALSE,
    volume_spike BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
