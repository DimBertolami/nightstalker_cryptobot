-- Create wallets table
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exchange_id` VARCHAR(50) NOT NULL COMMENT 'Exchange identifier (e.g., binance, bitvavo)',
    `currency` VARCHAR(10) NOT NULL COMMENT 'Currency symbol (e.g., BTC, USDT)',
    `available_balance` DECIMAL(24, 8) DEFAULT 0 COMMENT 'Balance available for trading',
    `in_orders` DECIMAL(24, 8) DEFAULT 0 COMMENT 'Amount locked in open orders',
    `total_balance` DECIMAL(24, 8) GENERATED ALWAYS AS (available_balance + in_orders) STORED COMMENT 'Total balance (available + in orders)',
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `exchange_currency` (`exchange_id`, `currency`),
    INDEX `idx_exchange` (`exchange_id`),
    INDEX `idx_currency` (`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create wallet transactions table
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wallet_id` INT NOT NULL,
    `exchange_id` VARCHAR(50) NOT NULL,
    `currency` VARCHAR(10) NOT NULL,
    `type` ENUM('deposit', 'withdrawal', 'trade', 'fee', 'transfer') NOT NULL,
    `amount` DECIMAL(24, 8) NOT NULL,
    `balance_before` DECIMAL(24, 8) NOT NULL,
    `balance_after` DECIMAL(24, 8) NOT NULL,
    `related_id` VARCHAR(100) DEFAULT NULL COMMENT 'Related order ID or transaction ID',
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE,
    INDEX `idx_wallet` (`wallet_id`),
    INDEX `idx_exchange_currency` (`exchange_id`, `currency`),
    INDEX `idx_related` (`related_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create orders table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exchange_id` VARCHAR(50) NOT NULL,
    `order_id` VARCHAR(100) NOT NULL COMMENT 'Exchange order ID',
    `client_order_id` VARCHAR(100) DEFAULT NULL,
    `symbol` VARCHAR(20) NOT NULL,
    `type` ENUM('market', 'limit', 'stop_loss', 'take_profit', 'stop_loss_limit', 'take_profit_limit') NOT NULL,
    `side` ENUM('buy', 'sell') NOT NULL,
    `price` DECIMAL(24, 8) DEFAULT NULL,
    `stop_price` DECIMAL(24, 8) DEFAULT NULL,
    `amount` DECIMAL(24, 8) NOT NULL,
    `cost` DECIMAL(24, 8) DEFAULT NULL COMMENT 'Quote currency cost (price * amount)',
    `filled` DECIMAL(24, 8) DEFAULT 0,
    `remaining` DECIMAL(24, 8) DEFAULT NULL,
    `status` ENUM('open', 'closed', 'canceled', 'expired', 'rejected') NOT NULL,
    `time_in_force` VARCHAR(10) DEFAULT 'GTC',
    `fee` DECIMAL(24, 8) DEFAULT 0,
    `fee_currency` VARCHAR(10) DEFAULT NULL,
    `trades` JSON DEFAULT NULL,
    `params` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `exchange_order` (`exchange_id`, `order_id`),
    INDEX `idx_exchange` (`exchange_id`),
    INDEX `idx_symbol` (`symbol`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
