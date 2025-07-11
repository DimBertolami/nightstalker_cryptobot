<?php
// Night Stalker Configuration File

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1304');
define('DB_NAME', 'night_stalker');

// API Keys Configuration
define('CMC_API_KEY', 'e2e746c1-169a-4778-90f7-a66458a6af00');
define('COINGECKO_API_KEY', 'CG-YXnGRuZPgUAyWZs14mHBJVyW');
define('BINANCE_API_KEY', 'X8HpKiRKv6fNCulGEV2ReFpgyeS4wT0SWgokopvObB6ICUADi5nOEUZNFbcWUP9I');
define('BINANCE_API_SECRET', 'qeJ3x3SByFxFepLXrBqkWkSYijPt2DjvNA1MVA7fykgOqgUw6Jrb0Cmmvm7DWqWs');
define('JUPITER_API_KEY', 'X8HpKiRKv6fNCulGEV2ReFpgyeS4wT0SWgokopvObB6ICUADi5nOEUZNFbcWUP9I');
define('JUPITER_API_SECRET', 'qeJ3x3SByFxFepLXrBqkWkSYijPt2DjvNA1MVA7fykgOqUw6Jrb0Cmmvm7DWqWs');
define('BITVAVO_API_KEY', 'ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37');
define('BITVAVO_API_SECRET', '28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b');
define('ALPACA_API_KEY', 'PK9F6B7LJRL7MLK2C0FB');
define('ALPACA_API_SECRET', '3DpoXdQWnuQKUkazf8bDAsSsgauQDqCEbqzZ8BOA');
define('KRAKEN_API_KEY', 'dmqjTdc9A25Pd83sk9kz/M+Z/3Zu9+kSRKoGR6o7IuKzBqcWEvHIPdVl');
define('KRAKEN_API_SECRET', 'gzDfB+URG1zE0vo0kZANmOydXSwIS9BxDz6/WAAtZ6X3m3W6jc/gIugLZJNkyHWj97Uo9cGsf6TOMWXPPpMurg==');

// API URLs
define('CMC_API_URL', 'https://pro-api.coinmarketcap.com');
define('COINGECKO_API_URL', 'https://api.coingecko.com/api/v3');
define('BINANCE_API_URL', 'https://api.binance.com');
define('JUPITER_API_URL', 'https://jup.ag/swap/EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v-So11111111111111111111111111111111111111112?inAmount=');
define('BITVAVO_API_URL', 'https://api.bitvavo.com/v2/order');
define('ALPACA_API_URL', 'https://paper-api.alpaca.markets');
define('KRAKEN_API_URL', 'https://api.kraken.com');

// Trading Constants
define('COIN_WHITELIST', ['COIN_DMC', 'COIN_POPCAT']);
define('MIN_VOLUME_THRESHOLD', 100000);
define('MAX_TRADE_AMOUNT', 500);

// Validate API Keys
//if (strpos(CMC_API_KEY, 'e2e746c1-169a-4778-90f7-a66458a6af00') !== false) {
//    die("ERROR: Please update CMC_API_KEY in config.php with your key from https://pro.coinmarketcap.com/account");
//}

if (strpos(COINGECKO_API_KEY, 'e2e746c1-169a-4778-90f7-a66458a6af00') !== false) {
    die("ERROR: Please update COINGECKO_API_KEY in config.php");
}

// CoinMarketCap Configuration
//define('CMC_API_SYMBOLS', 'BTC,ETH,BNB,SOL');  // Specific coins you track

// Trading Parameters
define('CHECK_INTERVAL', 3); // Check every 3 seconds (20x/minute)
define('MAX_COIN_AGE', 24); // Max age in hours
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
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
