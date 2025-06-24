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

    // Prepare statements for database operations
    // For cryptocurrencies table
    $getCrypto = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? ORDER BY last_updated DESC LIMIT 1");
    $updateCrypto = $db->prepare("UPDATE cryptocurrencies SET 
        name = ?, price = ?, price_change_24h = ?, market_cap = ?, 
        volume = ?, last_updated = NOW() 
        WHERE id = ?");
    $insertCrypto = $db->prepare("INSERT INTO cryptocurrencies 
        (id, symbol, name, price, price_change_24h, market_cap, volume, last_updated, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    
    // For coins table (old format)
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
    
    // For price history
    $insertHistory = $db->prepare("INSERT INTO price_history 
        (coin_id, price, volume, market_cap) 
        VALUES (?, ?, ?, ?)");
        
    if (!$getCrypto || !$updateCrypto || !$insertCrypto || !$upsertCoin || !$insertHistory) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    $db->autocommit(FALSE); // Start transaction mode
    
    // Process updates
    $updated = 0;
    foreach ($marketData as $symbol => $coinData) {
        // Extract data into variables for bind_param
        $cryptoSymbol = $symbol;
        $cryptoName = $coinData['name'] ?? $symbol;
        $price = $coinData['price'];
        $priceChange = $coinData['change']; // API returns 'change' not 'percent_change_24h'
        $marketCap = $coinData['market_cap'];
        $volume = $coinData['volume']; // API returns 'volume' not 'volume_24h'
        
        // Output volume for debugging
        echo "Coin $symbol volume: $volume\n";
        
        // First, check if this cryptocurrency already exists
        $getCrypto->bind_param('s', $cryptoSymbol);
        if (!$getCrypto->execute()) {
            $log("Failed to check if cryptocurrency exists: " . $getCrypto->error);
            continue;
        }
        
        $getCrypto->store_result();
        
        if ($getCrypto->num_rows > 0) {
            // Cryptocurrency exists, get its ID
            $getCrypto->bind_result($cryptoId);
            $getCrypto->fetch();
            $getCrypto->free_result();
            
            $log("Found existing cryptocurrency with ID: $cryptoId for symbol: $cryptoSymbol");
            
            // Update the existing record
            $updateCrypto->bind_param('sdddds',
                $cryptoName,       // name
                $price,            // price
                $priceChange,      // price_change_24h
                $marketCap,        // market_cap
                $volume,           // volume
                $cryptoId          // id
            );
            
            if (!$updateCrypto->execute()) {
                $log("Update cryptocurrencies failed for $cryptoSymbol: " . $updateCrypto->error);
                continue; // Skip to next coin if update fails
            }
        } else {
            // Cryptocurrency doesn't exist, create a new ID and insert
            $cryptoId = md5($cryptoSymbol . time()); // Generate a unique ID
            $log("Creating new cryptocurrency with ID: $cryptoId for symbol: $cryptoSymbol");
            
            // Insert new cryptocurrency
            $insertCrypto->bind_param('sssdddd',
                $cryptoId,          // id
                $cryptoSymbol,      // symbol
                $cryptoName,        // name
                $price,             // price
                $priceChange,       // price_change_24h
                $marketCap,         // market_cap
                $volume             // volume
            );
            
            if (!$insertCrypto->execute()) {
                $log("Insert cryptocurrencies failed for $cryptoSymbol: " . $insertCrypto->error);
                continue; // Skip to next coin if we can't insert this one
            }
        }
        
        // Update coins table (old format)
        $upsertCoin->bind_param('ssdddd',
            $symbol,      // symbol
            $cryptoName,  // name
            $price,       // current_price
            $priceChange, // price_change_24h
            $marketCap,   // market_cap
            $volume       // volume_24h
        );
        
        if (!$upsertCoin->execute()) {
            $log("Upsert coins failed for $symbol: " . $upsertCoin->error);
            // Continue anyway, this is not critical
        }
        
        // Insert price history record using the cryptocurrency ID
        $insertHistory->bind_param("sddd",
            $cryptoId,   // Use string ID from cryptocurrencies table
            $price,
            $volume,
            $marketCap
        );
        
        if (!$insertHistory->execute()) {
            $log("Insert history failed for $symbol: " . $insertHistory->error);
            // Continue anyway, we've already updated the main tables
        } else {
            $log("Successfully inserted price history for $symbol with ID $cryptoId");
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