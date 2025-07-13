<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Suppress warnings for this script execution
error_reporting(E_ERROR | E_PARSE);

/**
 * Generate a unique ID for a coin
 * @param string $symbol The coin symbol
 * @param string $source The data source
 * @return string MD5 hash of symbol and source
 */
function generateCoinId($symbol, $source) {
    return md5(strtolower($symbol) . '_' . strtolower(str_replace(' ', '_', $source)));
}

echo "\n===== FETCHING CRYPTOCURRENCY DATA =====\n";

// Initialize the processed coins array
$processedCoins = [];
$sourceCount = [];

// Fetch from CoinMarketCap
echo "Fetching from CoinMarketCap...\n";
try {
    $cmcData = fetchFromCMC('/cryptocurrency/listings/latest', [
        'start' => 1,
        'limit' => 100,
        'convert' => 'USD'
    ]);
    
    if (!empty($cmcData)) {
        $source = 'CoinMarketCap';
        $sourceCount[$source] = 0;
        
        foreach ($cmcData as $symbol => $coin) {
            $coinId = generateCoinId($symbol, $source);
            
            $processedCoins[$coinId] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['price'] ?? 0,
                'change' => $coin['change'] ?? 0,
                'market_cap' => $coin['market_cap'] ?? 0,
                'volume' => $coin['volume'] ?? 0,
                'volume_24h' => $coin['volume_24h'] ?? $coin['volume'] ?? 0,
                'date_added' => $coin['date_added'] ?? null,
                'source' => $source
            ];
            
            $sourceCount[$source]++;
            echo "Added {$symbol} from {$source}\n";
        }
        
        echo "\n✓ Successfully fetched " . $sourceCount[$source] . " coins from CoinMarketCap\n";
    } else {
        echo "\n✗ No data returned from CoinMarketCap API\n";
    }
} catch (Exception $e) {
    echo "Error fetching from CoinMarketCap: " . $e->getMessage() . "\n";
}

// Try to fetch from CoinGecko
echo "\nFetching from CoinGecko...\n";
try {
    echo "Making API request to CoinGecko...\n";
    $geckoData = fetchFromCoinGecko();
    
    if (!empty($geckoData)) {
        $source = 'CoinGecko';
        $sourceCount[$source] = 0;
        
        foreach ($geckoData as $symbol => $coin) {
            $coinId = generateCoinId($symbol, $source);
            
            $processedCoins[$coinId] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['price'] ?? 0,
                'change' => $coin['change'] ?? 0,
                'market_cap' => $coin['market_cap'] ?? 0,
                'volume' => $coin['volume'] ?? 0,
                'volume_24h' => $coin['volume_24h'] ?? $coin['volume'] ?? 0,
                'date_added' => $coin['date_added'] ?? null,
                'source' => $source
            ];
            
            $sourceCount[$source]++;
            echo "Added {$symbol} from {$source}\n";
        }
        
        echo "\n✓ Successfully fetched " . $sourceCount[$source] . " coins from CoinGecko\n";
    } else {
        echo "\n✗ No data returned from CoinGecko API or API rate limit exceeded\n";
        echo "Note: CoinGecko may require an API key for production use\n";
    }
} catch (Exception $e) {
    echo "Error fetching from CoinGecko: " . $e->getMessage() . "\n";
}

// Try to fetch from Jupiter
echo "\nFetching from Jupiter...\n";
try {
    echo "Making API request to Jupiter...\n";
    $jupiterData = fetchFromJupiter();
    
    if (!empty($jupiterData)) {
        $source = 'Jupiter';
        $sourceCount[$source] = 0;
        
        foreach ($jupiterData as $symbol => $coin) {
            $coinId = generateCoinId($symbol, $source);
            
            $processedCoins[$coinId] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['price'] ?? 0,
                'change' => $coin['change'] ?? 0,
                'market_cap' => $coin['market_cap'] ?? 0,
                'volume' => $coin['volume'] ?? 0,
                'volume_24h' => $coin['volume_24h'] ?? $coin['volume'] ?? 0,
                'date_added' => $coin['date_added'] ?? null,
                'source' => $source
            ];
            
            $sourceCount[$source]++;
            echo "Added {$symbol} from {$source}\n";
        }
        
        echo "\n✓ Successfully fetched " . $sourceCount[$source] . " coins from Jupiter\n";
    } else {
        echo "\n✗ No data returned from Jupiter API\n";
        echo "Note: Jupiter API only provides limited token data\n";
    }
} catch (Exception $e) {
    echo "Error fetching from Jupiter: " . $e->getMessage() . "\n";
}

// Try to fetch from Bitvavo
echo "\nFetching from Bitvavo...\n";
try {
    echo "Making API request to Bitvavo...\n";
    $bitvavoData = fetchFromBitvavo();
    
    if (!empty($bitvavoData)) {
        $source = 'Bitvavo';
        $sourceCount[$source] = 0;
        
        foreach ($bitvavoData as $symbol => $coin) {
            $coinId = generateCoinId($symbol, $source);
            
            $processedCoins[$coinId] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['price'] ?? 0,
                'change' => $coin['change'] ?? 0,
                'market_cap' => $coin['market_cap'] ?? 0,
                'volume' => $coin['volume'] ?? 0,
                'volume_24h' => $coin['volume_24h'] ?? $coin['volume'] ?? 0,
                'date_added' => $coin['date_added'] ?? null,
                'source' => $source
            ];
            
            $sourceCount[$source]++;
            echo "Added {$symbol} from {$source}\n";
        }
        
        echo "\n✓ Successfully fetched " . $sourceCount[$source] . " coins from Bitvavo\n";
    } else {
        echo "\n✗ No data returned from Bitvavo API\n";
        echo "Note: Bitvavo API may have rate limits or require authentication\n";
    }
} catch (Exception $e) {
    echo "Error fetching from Bitvavo: " . $e->getMessage() . "\n";
}

// Check if we have any data
if (empty($processedCoins)) {
    echo "\n✗ ERROR: Failed to fetch cryptocurrency data from any source\n";
    exit;
}

// We already have sourceCount from the fetching process

// Display summary of fetched data
echo "\n===== CRYPTOCURRENCY DATA SUMMARY =====\n";
echo "Total coins fetched: " . count($processedCoins) . "\n";
echo "Coins by source:\n";
foreach ($sourceCount as $source => $count) {
    echo "- {$source}: {$count} coins\n";
}
echo "====================================\n\n";

// Database connection function
function connectDB() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        die("Database connection failed: " . $mysqli->connect_error);
    }
    return $mysqli;
}

$db = connectDB();

// First, check if we need to alter the table to add the source column
addSourceColumnIfNeeded($db);

echo "\n===== PROCESSING COINS =====\n";
echo "Total coins to process: " . count($processedCoins) . "\n\n";

// Process each coin
foreach ($processedCoins as $coinId => $coin) {
    try {
        // Get the symbol and source
        $symbol = $coin['symbol'];
        $source = $coin['source'];
        
        // Convert datetime to MySQL format if available
        $createdAt = isset($coin['date_added']) && $coin['date_added'] 
            ? date('Y-m-d H:i:s', strtotime($coin['date_added']))
            : date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $ageHours = isset($coin['date_added']) && $coin['date_added'] 
            ? round((time() - strtotime($coin['date_added'])) / 3600)
            : 24;
        
        // Check if this coin already exists in the database
        $stmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? AND source = ?");
        $stmt->bind_param("ss", $symbol, $source);
        $stmt->execute();
        $result = $stmt->get_result();
        $coinExists = $result->num_rows > 0;
        
        if (!$coinExists) {
            // Insert new coin
            $stmt = $db->prepare("INSERT INTO cryptocurrencies 
                (id, name, symbol, created_at, age_hours, market_cap, volume, price, price_change_24h, last_updated, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            // Prepare variables for binding
            $name = $coin['name'];
            $marketCap = $coin['market_cap'] ?? 0;
            $volume = $coin['volume'] ?? 0;
            $price = $coin['price'] ?? 0;
            $change = $coin['change'] ?? 0;
            
            $stmt->bind_param(
                'ssssiiddds',
                $coinId,
                $name,
                $symbol,
                $createdAt,
                $ageHours,
                $marketCap,
                $volume,
                $price,
                $change,
                $source
            );
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            
            logEvent("Added new coin: {$coin['name']} from {$source}", 'info');
        } else {
            // Update existing coin
            $existingCoin = $result->fetch_assoc();
            $coinId = $existingCoin['id'];
            
            $stmt = $db->prepare("UPDATE cryptocurrencies SET 
                name = ?, 
                market_cap = ?, 
                volume = ?, 
                price = ?, 
                price_change_24h = ?, 
                last_updated = NOW(),
                source = ?
                WHERE id = ?");
            // Prepare variables for binding
            $name = $coin['name'];
            $marketCap = $coin['market_cap'] ?? 0;
            $volume = $coin['volume'] ?? 0;
            $price = $coin['price'] ?? 0;
            $change = $coin['change'] ?? 0;
            
            $stmt->bind_param(
                'sddddss',
                $name,
                $marketCap,
                $volume,
                $price,
                $change,
                $source,
                $coinId
            );
            $stmt->execute();
        }
        
        // Update price history
        $stmt = $db->prepare("INSERT INTO price_history 
            (coin_id, price, volume, market_cap)
            VALUES (?, ?, ?, ?)");
        // Prepare variables for binding
        $price = $coin['price'] ?? 0;
        $volume = $coin['volume'] ?? 0;
        $marketCap = $coin['market_cap'] ?? 0;
        
        $stmt->bind_param(
            'sddd',
            $coinId,
            $price,
            $volume,
            $marketCap
        );
        $stmt->execute();
        
        // Check for volume spikes
        if ($coin['volume'] >= MIN_VOLUME_THRESHOLD) {
            $db->query("UPDATE cryptocurrencies SET is_trending=1 WHERE id='{$coinId}'");
        }
        
        // Output detailed coin information
        echo "Processed: {$symbol} from {$source} - Price: $" . number_format($coin['price'] ?? 0, 4) . ", Volume: $" . number_format($coin['volume'] ?? 0, 2) . "\n";
        
    } catch (Exception $e) {
        logEvent("Error processing {$symbol}: " . $e->getMessage(), 'error');
        continue;
    }
}

// Display summary after processing
echo "\n===== PROCESSING SUMMARY =====\n";
echo "Successfully updated cryptocurrency data from multiple sources\n";

echo "Coins in database by source:\n";
foreach ($sourceCount as $source => $count) {
    echo "- {$source}: {$count} coins\n";
}

echo "============================\n";

logEvent("Successfully updated cryptocurrency data from multiple sources", 'info');

/**
 * Add source column to cryptocurrencies table if it doesn't exist
 */
function addSourceColumnIfNeeded($db) {
    $result = $db->query("SHOW COLUMNS FROM cryptocurrencies LIKE 'source'");
    if ($result->num_rows === 0) {
        // Add the source column if it doesn't exist
        $db->query("ALTER TABLE cryptocurrencies ADD COLUMN source VARCHAR(50) DEFAULT 'CoinMarketCap' AFTER symbol");
        logEvent("Added 'source' column to cryptocurrencies table", 'info');
    }
}
// Function moved to the top of the file