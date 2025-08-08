CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    exchange_id VARCHAR(50) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(32,12) NOT NULL,
    balance_before DECIMAL(32,12) NOT NULL,
    balance_after DECIMAL(32,12) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id)
);