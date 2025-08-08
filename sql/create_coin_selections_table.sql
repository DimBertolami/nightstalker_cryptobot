CREATE TABLE IF NOT EXISTS coin_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    action VARCHAR(10) NOT NULL,
    score DECIMAL(10, 8) NOT NULL,
    risk_score DECIMAL(10, 8) NOT NULL,
    price DECIMAL(18, 8) NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);