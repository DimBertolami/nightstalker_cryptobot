CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exchange_id VARCHAR(50) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    available_balance DECIMAL(32,12) NOT NULL,
    in_orders DECIMAL(32,12) NOT NULL,
    total_balance DECIMAL(32,12) NOT NULL,
    last_updated DATETIME NOT NULL,
    UNIQUE KEY `exchange_currency` (`exchange_id`, `currency`)
);