<?php
// Night Stalker Configuration File

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'ns_admin');
define('DB_PASS', 'password');
define('DB_NAME', 'night_stalker');

// API Keys (replace with your actual keys)
// define('COINGECKO_API_KEY', 'CG-YXnGRuZPgUAyWZs14mHBJVyW');
// define('COINGECKO_API_URL', 'https://api.coingecko.com/api/v3');
// CoinMarketCap Configuration

// Replace these lines in config.php
define('CMC_API_KEY', '1758e18b-1744-4ad6-a2a9-908af2f33c8a');
define('CMC_API_URL', 'https://pro-api.coinmarketcap.com/v1/cryptocurrency');
define('CMC_API_SYMBOLS', 'BTC,ETH,BNB,SOL');  // Specific coins you track

// Trading Parameters
define('MIN_VOLUME_THRESHOLD', 1500000); // $1.5 million
define('CHECK_INTERVAL', 3); // Check every 3 seconds (20x/minute)
define('MAX_COIN_AGE', 48); // Max age in hours
define('MIN_PROFIT_PERCENTAGE', 5); // Minimum 5% profit target
define('STOP_LOSS_PERCENTAGE', 3); // 3% stop loss

// Path configuration
define('BASE_URL', 'http://localhost/NS');
define('LOG_PATH', __DIR__ . '/../logs/ns_log_' . date('Y-m-d') . '.log');
define('TRADE_LOG_PATH', __DIR__ . '/../logs/trades_' . date('Y-m-d') . '.log');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
//if (session_status() === PHP_SESSION_NONE) {
//    session_start();
//}
