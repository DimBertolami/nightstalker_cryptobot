<?php
// Night Stalker Configuration File

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1304');
define('DB_NAME', 'night_stalker');

// API Keys (replace with your actual keys)
define('COINGECKO_API_KEY', 'CG-YXnGRuZPgUAyWZs14mHBJVyW');
define('COINGECKO_API_URL', 'https://api.coingecko.com/api/v3');

// CoinMarketCap Configuration
define('CMC_API_KEY', '1758e18b-1744-4ad6-a2a9-908af2f33c8a');
define('CMC_API_URL', 'https://pro-api.coinmarketcap.com/v1/cryptocurrency');
define('CMC_API_SYMBOLS', 'BTC,ETH,BNB,SOL');  // Specific coins you track

//binance config
define('BINANCE_API_KEY', 'X8HpKiRKv6fNCulGEV2ReFpgyeS4wT0SWgokopvObB6ICUADi5nOEUZNFbcWUP9I');
define('BINANCE_API_SECRET', 'qeJ3x3SByFxFepLXrBqkWkSYijPt2DjvNA1MVA7fykgOqgUw6Jrb0Cmmvm7DWqWs');
define('BINANCE_API_URL', 'https://api.binance.com');

//jupiter api config
define('JUPITER_API_KEY', 'X8HpKiRKv6fNCulGEV2ReFpgyeS4wT0SWgokopvObB6ICUADi5nOEUZNFbcWUP9I');
define('JUPITER_API_SECRET', 'qeJ3x3SByFxFepLXrBqkWkSYijPt2DjvNA1MVA7fykgOqUw6Jrb0Cmmvm7DWqWs');
define('JUPITER_API_URL', 'https://jup.ag/swap/EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v-So11111111111111111111111111111111111111112?inAmount=');


//bitvavo api config
define('BITVAVO_API_KEY', 'ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37');
define('BITVAVO_API_SECRET', '28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b');
define('BITVAVO_API_URL', 'https://api.bitvavo.com/v2/order');

//alpaca api config
define('ALPACA_API_KEY', 'PK9F6B7LJRL7MLK2C0FB');
define('ALPACA_API_SECRET', '3DpoXdQWnuQKUkazf8bDAsSsgauQDqCEbqzZ8BOA');
define('ALPACA_API_URL', 'https://paper-api.alpaca.markets');

//kraken api config
define('KRAKEN_API_KEY', 'dmqjTdc9A25Pd83sk9kz/M+Z/3Zu9+kSRKoGR6o7IuKzBqcWEvHIPdVl');
define('KRAKEN_API_SECRET', 'gzDfB+URG1zE0vo0kZANmOydXSwIS9BxDz6/WAAtZ6X3m3W6jc/gIugLZJNkyHWj97Uo9cGsf6TOMWXPPpMurg==');
define('KRAKEN_API_URL', 'https://api.kraken.com');



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
