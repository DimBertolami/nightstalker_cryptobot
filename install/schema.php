<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

$db = connectDB();

// Create tables
$queries = [
    "CREATE TABLE IF NOT EXISTS cryptocurrencies (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        symbol VARCHAR(10) NOT NULL,
        created_at DATETIME NOT NULL,
        added_to_system DATETIME DEFAULT CURRENT_TIMESTAMP,
        age_hours INT DEFAULT 0,
        market_cap DECIMAL(30,2) DEFAULT 0,
        volume DECIMAL(30,2) DEFAULT 0,
        price DECIMAL(20,8) DEFAULT 0,
        price_change_24h DECIMAL(10,2) DEFAULT 0,
        last_updated DATETIME,
        is_trending BOOLEAN DEFAULT FALSE,
        volume_spike BOOLEAN DEFAULT FALSE,
        INDEX idx_trending (is_trending),
        INDEX idx_volume_spike (volume_spike)
    )",
    
    "CREATE TABLE IF NOT EXISTS trades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coin_id VARCHAR(50) NOT NULL,
        trade_type ENUM('buy', 'sell') NOT NULL,
        amount DECIMAL(20,8) NOT NULL,
        price DECIMAL(20,8) NOT NULL,
        total_value DECIMAL(30,2) NOT NULL,
        trade_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        profit_loss DECIMAL(30,2) DEFAULT NULL,
        FOREIGN KEY (coin_id) REFERENCES cryptocurrencies(id),
        INDEX idx_coin_id (coin_id),
        INDEX idx_trade_time (trade_time)
    )",
    
    "CREATE TABLE IF NOT EXISTS price_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coin_id VARCHAR(50) NOT NULL,
        price DECIMAL(20,8) NOT NULL,
        volume DECIMAL(30,2) NOT NULL,
        market_cap DECIMAL(30,2) NOT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (coin_id) REFERENCES cryptocurrencies(id),
        INDEX idx_coin_id (coin_id),
        INDEX idx_recorded_at (recorded_at)
    )",
    
    "CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_level ENUM('info', 'warning', 'error', 'critical') NOT NULL,
        message TEXT NOT NULL,
        context TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_log_level (log_level),
        INDEX idx_created_at (created_at)
    )"
];

foreach ($queries as $query) {
    if (!$db->query($query)) {
        die("Error creating table: " . $db->error);
    }
}

// Insert initial data if needed
// $db->query("INSERT INTO ...");

echo "Database schema created successfully";
