#!/opt/lampp/bin/php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Initialize logging
$logFile = __DIR__ . '/../logs/price_updates.log';
$log = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
};

try {
    $log("Starting price update");

    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Note: We're not creating tables anymore as they already exist with different schema
    // Just logging the existing table structure for reference
    $log("Using existing cryptocurrencies table structure");
    $db->query("CREATE TABLE IF NOT EXISTS price_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coin_id INT NOT NULL,
        price DECIMAL(20,8) NOT NULL,
        volume DECIMAL(30,2) NOT NULL,
        market_cap DECIMAL(30,2) NOT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (coin_id) REFERENCES cryptocurrencies(id)
    )");

    // Get market data
    $marketData = fetchFromCMC();
    if (empty($marketData) || !validateMarketData($marketData)) {
        // Log raw data for debugging
        file_put_contents(
            __DIR__ . '/../logs/api_debug.log',
            date('Y-m-d H:i:s') . " - Invalid API response format: " . 
            print_r($marketData, true) . "\n",
            FILE_APPEND
        );
        throw new Exception("Invalid market data format received");
    }

    // Prepare statements for cryptocurrencies table (new format)
    $upsertCryptoCoin = $db->prepare("INSERT INTO cryptocurrencies 
        (id, symbol, name, price, price_change_24h, market_cap, volume, last_updated, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE 
            name=VALUES(name), 
            price=VALUES(price), 
            price_change_24h=VALUES(price_change_24h), 
            market_cap=VALUES(market_cap), 
            volume=VALUES(volume), 
            last_updated=NOW()");
    
    // Prepare statements for coins table (old format but needed for price_history foreign key)
    $upsertCoin = $db->prepare("INSERT INTO coins 
        (symbol, name, current_price, price_change_24h, market_cap, volume_24h, last_updated) 
        VALUES (?, ?, ?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            name=VALUES(name), 
            current_price=VALUES(current_price), 
            price_change_24h=VALUES(price_change_24h), 
            market_cap=VALUES(market_cap), 
            volume_24h=VALUES(volume_24h), 
            last_updated=NOW()");
    
    // Get coin ID from coins table for price history
    $getCoinId = $db->prepare("SELECT id FROM coins WHERE symbol = ?");
    $insertHistory = $db->prepare("INSERT INTO price_history 
        (coin_id, price, volume, market_cap) 
        VALUES (?, ?, ?, ?)");
        
    if (!$upsertCryptoCoin || !$upsertCoin || !$getCoinId || !$insertHistory) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    $db->autocommit(FALSE); // Start transaction mode

    // Log market data for debugging
    $log("Market data received: " . print_r($marketData, true));
    
    // Process updates
    $updated = 0;
    foreach ($marketData as $symbol => $coinData) {
        // Extract data into variables for bind_param (must be variables, not array elements)
        $cryptoId = strtolower($symbol);
        $cryptoSymbol = $symbol;
        $cryptoName = $coinData['name'] ?? $symbol; // Use actual name from API data
        $price = $coinData['price'];
        $priceChange = $coinData['change']; // API returns 'change' not 'percent_change_24h'
        $marketCap = $coinData['market_cap'];
        $volume = $coinData['volume']; // API returns 'volume' not 'volume_24h'
        
        // 1. Update cryptocurrencies table (new format)
        $upsertCryptoCoin->bind_param('sssdddd',
            $cryptoId,          // id (using symbol as ID)
            $cryptoSymbol,      // symbol
            $cryptoName,        // name
            $price,             // price
            $priceChange,       // price_change_24h
            $marketCap,         // market_cap
            $volume             // volume
        );
        
        if (!$upsertCryptoCoin->execute()) {
            $log("Upsert cryptocurrencies failed for $symbol: " . $upsertCryptoCoin->error);
            // Continue anyway to try the coins table
        }
        
        // 2. Update coins table (old format but needed for price_history foreign key)
        $name = $cryptoName; // Use the same name as in cryptocurrencies table
        $change = $priceChange; // Use the same price change as in cryptocurrencies table
        
        $upsertCoin->bind_param('ssdddd',
            $symbol,      // symbol
            $name,        // name
            $price,       // current_price
            $change,      // price_change_24h
            $marketCap,   // market_cap
            $volume       // volume_24h
        );
        
        if (!$upsertCoin->execute()) {
            $log("Upsert coins failed for $symbol: " . $upsertCoin->error);
            continue;
        }
        
        // Get the numeric ID for this coin
        $getCoinId->bind_param('s', $symbol);
        if (!$getCoinId->execute()) {
            $log("Failed to get coin ID for $symbol: " . $getCoinId->error);
            continue;
        }
        
        $getCoinId->bind_result($coinId);
        if (!$getCoinId->fetch()) {
            $log("No ID found for coin $symbol");
            $getCoinId->free_result();
            continue;
        }
        $getCoinId->free_result();
        
        // Insert price history with numeric ID
        $insertHistory->bind_param("iddd",
            $coinId,
            $price,
            $volume,
            $marketCap
        );
        
        if (!$insertHistory->execute()) {
            $log("Insert history failed for $symbol: " . $insertHistory->error);
        }
        
        $updated++;
    }
    
    $db->commit();
    $log("Successfully updated $updated coins");
    exit(0);

} catch (Exception $e) {
    $log("ERROR: " . $e->getMessage());
    if (isset($db) && $db->error) {
        $log("DB Error: " . $db->error);
    }
    if (isset($db)) {
        $db->rollback();
    }
    exit(1);
}