<?php
/**
 * Night Stalker - Import All Coins from Multiple Data Sources
 * 
 * This script imports all available coins from CoinGecko, Messari, or CryptoCompare APIs into the database
 * It automatically switches between data sources when rate limits are hit
 * It should be run periodically (e.g., once a day) to keep the database updated
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdo_functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/CryptoDataSourceInterface.php';
require_once __DIR__ . '/../includes/CoinGeckoDataSource.php';
require_once __DIR__ . '/../includes/MessariDataSource.php';
require_once __DIR__ . '/../includes/CryptoCompareDataSource.php';
require_once __DIR__ . '/../includes/CryptoDataSourceManager.php';

use NS\DataSources\CoinGeckoDataSource;
use NS\DataSources\MessariDataSource;
use NS\DataSources\CryptoCompareDataSource;
use NS\DataSources\CryptoDataSourceManager;

// Set PHP execution time to unlimited for large imports
set_time_limit(0);
ini_set('memory_limit', '1G');

// Get API keys from config if available
$coingecko_api_key = defined('COINGECKO_API_KEY') && !empty(COINGECKO_API_KEY) ? COINGECKO_API_KEY : '';
$messari_api_key = defined('MESSARI_API_KEY') && !empty(MESSARI_API_KEY) ? MESSARI_API_KEY : '';
$cryptocompare_api_key = defined('CRYPTOCOMPARE_API_KEY') && !empty(CRYPTOCOMPARE_API_KEY) ? CRYPTOCOMPARE_API_KEY : '';

// Initialize data sources
$dataSources = [
    new CoinGeckoDataSource($coingecko_api_key),
    new MessariDataSource($messari_api_key),
    new CryptoCompareDataSource($cryptocompare_api_key)
];

// Initialize data source manager
$dataSourceManager = new CryptoDataSourceManager($dataSources);

// Log start of script execution
$log_prefix = "[" . date("Y-m-d H:i:s") . "] [ImportAllCoins] ";
echo $log_prefix . "Starting all coins import process using " . $dataSourceManager->getActiveSource() . "...\n";

// Function to fetch all available coins using the active data source
function fetchAllCoinsFromDataSource($dataSourceManager) {
    global $log_prefix;
    
    try {
        // Get the full list of coins
        $allCoins = $dataSourceManager->searchCoins([]);
        
        // Format coins to match expected structure
        $formattedCoins = [];
        foreach ($allCoins as $coin) {
            // Make sure we have the required fields
            if (isset($coin['symbol']) && isset($coin['name'])) {
                $formattedCoins[] = [
                    'id' => $coin['id'] ?? $coin['symbol'],
                    'symbol' => $coin['symbol'],
                    'name' => $coin['name']
                ];
            }
        }
        
        echo $log_prefix . "Fetched " . count($formattedCoins) . " coins from " . $dataSourceManager->getActiveSource() . "\n";
        return $formattedCoins;
    } catch (Exception $e) {
        echo $log_prefix . "Error fetching coin list: " . $e->getMessage() . "\n";
        return null;
    }
}

// Function to fetch coin details using the data source manager
function fetchCoinDetails($coinId, $symbol, $dataSourceManager) {
    global $log_prefix;
    
    try {
        // Add a small delay between requests to avoid hammering the API
        usleep(500000); // 500ms delay
        
        // Try to get details by ID first, then by symbol
        $coinDetails = $dataSourceManager->getCoinDetails($coinId);
        
        // If failed with ID, try with symbol
        if (!$coinDetails) {
            $coinDetails = $dataSourceManager->getCoinDetails($symbol);
        }
        
        if (!$coinDetails) {
            echo $log_prefix . "Failed to get details for coin $coinId ($symbol)\n";
            return null;
        }
        
        // Normalize the response format to match what we expect
        return normalizeResponseFormat($coinDetails, $dataSourceManager->getActiveSource());
        
    } catch (Exception $e) {
        echo $log_prefix . "Error fetching coin details: " . $e->getMessage() . "\n";
        return null;
    }
}

// Normalize response format from different data sources
function normalizeResponseFormat($data, $sourceType) {
    $result = [];
    
    // Basic coin info
    $result['name'] = $data['name'] ?? ($data['full_name'] ?? '');
    
    // Market data
    $result['market_data'] = [
        'current_price' => ['usd' => 0],
        'price_change_percentage_24h' => 0,
        'market_cap' => ['usd' => 0],
        'total_volume' => ['usd' => 0]
    ];
    
    // Set fields based on data source type
    switch ($sourceType) {
        case 'CoinGeckoDataSource':
            // CoinGecko format is our base format
            return $data;
            
        case 'MessariDataSource':
            if (isset($data['metrics']) && isset($data['metrics']['market_data'])) {
                $marketData = $data['metrics']['market_data'];
                $result['market_data']['current_price']['usd'] = $marketData['price_usd'] ?? 0;
                $result['market_data']['price_change_percentage_24h'] = $marketData['percent_change_usd_last_24_hours'] ?? 0;
                $result['market_data']['market_cap']['usd'] = $marketData['marketcap'] ?? 0;
                $result['market_data']['total_volume']['usd'] = $marketData['volume_last_24_hours'] ?? 0;
            }
            
            // Try to get genesis date
            if (isset($data['profile']) && isset($data['profile']['general']) && isset($data['profile']['general']['launched_at'])) {
                $result['genesis_date'] = substr($data['profile']['general']['launched_at'], 0, 10); // Get YYYY-MM-DD part
            }
            break;
            
        case 'CryptoCompareDataSource':
            if (isset($data['USD'])) {
                $usdData = $data['USD'];
                $result['market_data']['current_price']['usd'] = $usdData['PRICE'] ?? 0;
                $result['market_data']['price_change_percentage_24h'] = $usdData['CHANGEPCT24HOUR'] ?? 0;
                $result['market_data']['market_cap']['usd'] = $usdData['MKTCAP'] ?? 0;
                $result['market_data']['total_volume']['usd'] = $usdData['TOTALVOLUME24H'] ?? 0;
            }
            
            // For genesis date we might not have anything
            if (isset($data['CoinInfo']) && isset($data['CoinInfo']['AssetLaunchDate'])) {
                $result['genesis_date'] = $data['CoinInfo']['AssetLaunchDate'];
            }
            break;
    }
    
    return $result;
}

// Connect to database
$db = getDBConnection();
if (!$db) {
    echo $log_prefix . "Database connection failed. Aborting.\n";
    exit(1);
}

// Fetch all coins list from active data source
$allCoins = fetchAllCoinsFromDataSource($dataSourceManager);
if (!$allCoins) {
    echo $log_prefix . "Failed to fetch coins list. Aborting.\n";
    exit(1);
}

echo $log_prefix . "Fetched " . count($allCoins) . " coins from " . $dataSourceManager->getActiveSource() . "\n";

// Prepare database statement for inserting/updating coins
$stmt = $db->prepare(
    "INSERT INTO coins (
        id, symbol, coin_name, current_price, price_change_24h, 
        marketcap, date_added, volume_24h, last_updated, is_trending
    ) VALUES (
        NULL, ?, ?, ?, ?, 
        ?, ?, ?, NOW(), ?
    ) ON DUPLICATE KEY UPDATE
        coin_name = VALUES(coin_name),
        current_price = VALUES(current_price),
        price_change_24h = VALUES(price_change_24h),
        marketcap = VALUES(marketcap),
        volume_24h = VALUES(volume_24h),
        last_updated = NOW()"
);

// Set counters
$total_count = count($allCoins);
$processed = 0;
$updated = 0;
$skipped = 0;
$errors = 0;

// Process top coins by market cap (adjust based on needs and API limits)
$limit = 50; // Set a smaller limit for the first run
$counter = 0;

// Skip already processed coins (add offset to start from where we left off)
$offset = isset($argv[1]) ? (int)$argv[1] : 0;
echo $log_prefix . "Starting from offset: $offset\n";

// Get list of existing symbols to avoid duplicates
$existingSymbols = [];
$existingResult = $db->query("SELECT symbol FROM coins");
if ($existingResult) {
    while ($row = $existingResult->fetch(PDO::FETCH_ASSOC)) {
        $existingSymbols[] = strtolower($row['symbol']);
    }
}

// Process coins with offset support
$processCount = 0;
foreach ($allCoins as $coin) {
    $processCount++;
    
    // Skip coins before our offset
    if ($processCount <= $offset) {
        continue;
    }
    
    $counter++;
    $processed++;
    
    // Get coin ID and symbol
    $coinId = $coin['id'];
    $symbol = strtolower($coin['symbol']);
    
    echo $log_prefix . "[$processed/$total_count] Processing coin ID: $coinId, Symbol: $symbol (offset $offset+$counter)\n";
    
    // Skip if already processed this symbol (avoid duplicates)
    if (in_array($symbol, $existingSymbols)) {
        echo $log_prefix . "[$processed/$total_count] Skipping duplicate symbol: $symbol\n";
        $skipped++;
        continue;
    }
    
    // Add to tracking
    $existingSymbols[] = $symbol;
    
    // Implement additional coin filtering logic here if needed
    // e.g., skip coins with certain patterns in their names
    
    try {
        // Get detailed coin information
        $coinDetails = fetchCoinDetails($coinId, $symbol, $dataSourceManager);
        if (!$coinDetails) {
            echo $log_prefix . "[$processed/$total_count] Failed to get details for coin $coinId ($symbol). Skipping.\n";
            $errors++;
            continue;
        }
        
        // Report which data source provided the details
        echo $log_prefix . "[$processed/$total_count] Got details from " . $dataSourceManager->getActiveSource() . "\n";
    } catch (Exception $e) {
        echo $log_prefix . "[$processed/$total_count] Exception processing $coinId: " . $e->getMessage() . "\n";
        $errors++;
        continue;
    }
    
    try {
        // Extract coin data
        $name = $coinDetails['name'];
        $current_price = $coinDetails['market_data']['current_price']['usd'] ?? 0;
        $price_change_24h = $coinDetails['market_data']['price_change_percentage_24h'] ?? 0;
        $market_cap = $coinDetails['market_data']['market_cap']['usd'] ?? 0;
        $volume_24h = $coinDetails['market_data']['total_volume']['usd'] ?? 0;
        $date_added = $coinDetails['genesis_date'] ?? date('Y-m-d'); // Use genesis_date if available
        
        // Determine if coin should be marked as trending (high volume relative to market cap)
        $is_trending = ($volume_24h > 0 && $market_cap > 0 && ($volume_24h / $market_cap) > 0.1) ? 1 : 0;
        
        // Debug output
        echo $log_prefix . "[$processed/$total_count] Processing $name ($symbol): $current_price USD, Volume: $volume_24h\n";
        
        // Insert or update coin in database
        $stmt->bind_param(
            'ssdddssb', 
            $symbol, $name, $current_price, $price_change_24h, 
            $market_cap, $date_added, $volume_24h, $is_trending
        );
        
        $stmt->execute();
        $updated++;
        
    } catch (Exception $e) {
        echo $log_prefix . "[$processed/$total_count] Error processing $coinId: " . $e->getMessage() . "\n";
        $errors++;
    }
    
    // Respect API rate limits - wait between requests
    usleep(200000); // 200ms delay
    
    // Stop after limit is reached and provide instructions for continuing
    if ($counter >= $limit) {
        $nextOffset = $offset + $counter;
        echo $log_prefix . "Reached processing limit of $limit coins.\n";
        echo $log_prefix . "To continue from where we left off, run this script again with: php import_all_coins.php $nextOffset\n";
        break;
    }
}

// Close statement
$stmt->close();

// Log summary
echo $log_prefix . "Import complete. Processed: $processed, Updated: $updated, Skipped: $skipped, Errors: $errors\n";

// Close database connection
$db->close();

echo $log_prefix . "All done!\n";
