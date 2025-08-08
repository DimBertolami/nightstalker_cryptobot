-- Additional database schema for Night Stalker

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `exchange_id` VARCHAR(50) NOT NULL,
  `symbol` VARCHAR(50) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `side` VARCHAR(10) NOT NULL,
  `price` DECIMAL(30,15) NULL,
  `stop_price` DECIMAL(30,15) NULL,
  `amount` DECIMAL(30,15) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `client_order_id` VARCHAR(255) NULL,
  `order_id` VARCHAR(255) NULL,
  `filled` DECIMAL(30,15) DEFAULT 0,
  `remaining` DECIMAL(30,15) DEFAULT 0,
  `cost` DECIMAL(30,15) NULL,
  `fee` DECIMAL(30,15) NULL,
  `fee_currency` VARCHAR(10) NULL,
  `trades` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wallets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT 1,
  `exchange_id` VARCHAR(50) NOT NULL,
  `currency` VARCHAR(10) NOT NULL,
  `available_balance` DECIMAL(30,15) NOT NULL,
  `in_orders` DECIMAL(30,15) NOT NULL,
  `total_balance` DECIMAL(30,15) NOT NULL,
  `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_exchange_currency` (`user_id`, `exchange_id`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `wallet_id` INT NOT NULL,
  `exchange_id` VARCHAR(50) NOT NULL,
  `currency` VARCHAR(10) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(30,15) NOT NULL,
  `balance_before` DECIMAL(30,15) NOT NULL,
  `balance_after` DECIMAL(30,15) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
