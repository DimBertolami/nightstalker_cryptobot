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
    
    // Create portfolio table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS portfolio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        coin_id VARCHAR(50) NOT NULL,
        amount DECIMAL(20,8) NOT NULL DEFAULT 0,
        avg_buy_price DECIMAL(20,8),
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
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
    
    // Get portfolio coins first - these are our priority
    $portfolioCoins = [];
    $portfolioResult = $db->query("SELECT DISTINCT coin_id FROM portfolio WHERE amount > 0");
    if ($portfolioResult) {
        while ($row = $portfolioResult->fetch_assoc()) {
            $portfolioCoins[] = $row['coin_id'];
        }
        $log("Found " . count($portfolioCoins) . " coins in portfolio");
    } else {
        $log("Failed to get portfolio coins: " . $db->error);
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
    $priorityCoins = [];
    $otherCoins = [];
    
    // Separate portfolio coins from other coins
    foreach ($marketData as $symbol => $coinData) {
        $cryptoId = strtolower($symbol);
        if (in_array($cryptoId, $portfolioCoins)) {
            $priorityCoins[$symbol] = $coinData;
        } else {
            $otherCoins[$symbol] = $coinData;
        }
    }
    
    $log("Processing " . count($priorityCoins) . " portfolio coins first");
    
    // Process portfolio coins first
    foreach ($priorityCoins as $symbol => $coinData) {
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
    
    // Now process other coins (if time permits)
    $log("Processing other coins");
    $maxOtherCoins = 50; // Limit to avoid processing too many coins
    $otherCoinsProcessed = 0;
    
    foreach ($otherCoins as $symbol => $coinData) {
        // Stop if we've processed enough other coins
        if ($otherCoinsProcessed >= $maxOtherCoins) {
            $log("Reached limit of $maxOtherCoins other coins, stopping");
            break;
        }
        
        // Extract data into variables for bind_param
        $cryptoId = strtolower($symbol);
        $cryptoSymbol = $symbol;
        $cryptoName = $coinData['name'] ?? $symbol;
        $price = $coinData['price'];
        $priceChange = $coinData['change'];
        $marketCap = $coinData['market_cap'];
        $volume = $coinData['volume'];
        
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
            continue;
        }
        
        // 2. Update coins table (old format but needed for price_history foreign key)
        $name = $cryptoName;
        $change = $priceChange;
        
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
        
        $otherCoinsProcessed++;
        $updated++;
    }
    
    // Update portfolio from trades
    updatePortfolioFromTrades($db, $log);
    
    $db->commit();
    $log("Successfully updated $updated coins (" . count($priorityCoins) . " portfolio coins and $otherCoinsProcessed other coins)");
    exit(0);

} catch (Exception $e) {
    $log("ERROR: " . $e->getMessage());
    if (isset($db) && $db->error) {
        $log("DB Error: " . $db->error);
    }
    if (isset($db)) {
        $db->rollback();
    }
}

/**
 * Updates the portfolio table based on trade history
 * This ensures the portfolio table is always in sync with actual trades
 */
function updatePortfolioFromTrades($db, $log) {
    $log("Updating portfolio from trades");
    
    // Commented out to prevent clearing the portfolio table
    // $db->query("TRUNCATE TABLE portfolio");
    
    // Get all trades grouped by coin_id
    $query = "SELECT coin_id, 
                     SUM(CASE WHEN trade_type = 'buy' THEN amount ELSE -amount END) as total_amount,
                     SUM(CASE WHEN trade_type = 'buy' THEN total_value ELSE 0 END) as total_buy_value,
                     SUM(CASE WHEN trade_type = 'buy' THEN amount ELSE 0 END) as total_buy_amount
              FROM trades 
              GROUP BY coin_id 
              HAVING total_amount > 0";
              
    $result = $db->query($query);
    
    if (!$result) {
        $log("Failed to get trade summary: " . $db->error);
        return;
    }
    
    $insertPortfolio = $db->prepare("INSERT INTO portfolio (coin_id, amount, avg_buy_price) VALUES (?, ?, ?)");
    
    if (!$insertPortfolio) {
        $log("Failed to prepare portfolio insert: " . $db->error);
        return;
    }
    
    $portfolioCount = 0;
    while ($row = $result->fetch_assoc()) {
        $coinId = $row['coin_id'];
        $amount = $row['total_amount'];
        
        // Calculate average buy price
        $avgBuyPrice = 0;
        if ($row['total_buy_amount'] > 0) {
            $avgBuyPrice = $row['total_buy_value'] / $row['total_buy_amount'];
        }
        
        $insertPortfolio->bind_param('sdd', $coinId, $amount, $avgBuyPrice);
        
        if (!$insertPortfolio->execute()) {
            $log("Failed to insert portfolio for $coinId: " . $insertPortfolio->error);
            continue;
        }
        
        $portfolioCount++;
    }
    
    $log("Updated portfolio with $portfolioCount coins");
}

// End of script