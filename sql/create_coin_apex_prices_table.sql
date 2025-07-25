-- File: sql/create_coin_apex_prices_table.sql

CREATE TABLE IF NOT EXISTS `coin_apex_prices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coin_id` VARCHAR(50) NOT NULL UNIQUE, -- Assuming coin_id is the symbol (e.g., BTC, ETH)
    `apex_price` DECIMAL(32, 12) NOT NULL,
    `apex_timestamp` DATETIME NOT NULL,
    `drop_start_timestamp` DATETIME NULL, -- When price first dropped below apex and stayed there
    `status` ENUM('monitoring', 'dropping', 'sold') NOT NULL DEFAULT 'monitoring',
    `last_checked` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`coin_id`) REFERENCES `cryptocurrencies`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
