<?php
// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Get data from database
    $db = getDBConnection();
    
    // Check if we should fetch from all_coingecko_coins table
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == 1;
    
    if ($showAll) {
        // Fetch from all_coingecko_coins table
        $query = "SELECT 
                    id, 
                    symbol, 
                    name, 
                    platforms, 
                    last_updated,
                    NULL as current_price,
                    NULL as price_change_24h,
                    NULL as market_cap,
                    NULL as volume_24h,
                    'CoinGecko' as source,
                    0 as user_balance,
                    0 as is_trending,
                    0 as volume_spike
                  FROM all_coingecko_coins 
                  ORDER BY name ASC";
    } else {
        // Original query for coins table
        $query = "SELECT * FROM coins ORDER BY market_cap DESC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $coinsData = [];
    while ($row = $result->fetch_assoc()) {
        if (!isset($row['source'])) {
            $row['source'] = $showAll ? 'CoinGecko' : 'CoinMarketCap';
        }
        
        // Ensure all required fields are set with defaults
        $coinsData[] = array_merge([
            'id' => $row['id'],
            'symbol' => $row['symbol'],
            'name' => $row['name'],
            'current_price' => (float)($row['current_price'] ?? 0),
            'price_change_24h' => (float)($row['price_change_24h'] ?? 0),
            'market_cap' => (float)($row['market_cap'] ?? 0),
            'volume_24h' => (float)($row['volume_24h'] ?? 0),
            'last_updated' => $row['last_updated'] ?? date('Y-m-d H:i:s'),
            'source' => $row['source'],
            'user_balance' => 0,  // Initialize with 0 balance
            'is_trending' => (bool)($row['is_trending'] ?? false),
            'volume_spike' => (bool)($row['volume_spike'] ?? false)
        ], $row);
    }
    
    // Get user ID and balances
    $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing
    $userBalances = [];
    try {
        $userBalances = getUserBalance($userId);
    } catch (Exception $e) {
        error_log("Balance error: " . $e->getMessage());
    }
    
    // Merge live data with database data
    $coins = array_map(function($coin) use ($marketData, $userBalances) {
        $symbol = $coin['symbol'];
        
        // Try to find the coin data with suffixes (_CMC, _Gecko)
        $liveData = [];
        if (isset($marketData["{$symbol}_CMC"])) {
            $liveData = $marketData["{$symbol}_CMC"];
        } elseif (isset($marketData["{$symbol}_Gecko"])) {
            $liveData = $marketData["{$symbol}_Gecko"];
        } elseif (isset($marketData[$symbol])) {
            $liveData = $marketData[$symbol];
        }
        
        // Use the coin ID from the database
        $coinId = $coin['id'];
        
        // Map the fields from the coins table to the expected output format
        $mappedCoin = [
            'id' => $coinId,
            'name' => $coin['name'],
            'symbol' => $coin['symbol'],
            'price' => (float)$coin['current_price'],  // Convert to float to ensure it's a number
            'price_change_24h' => (float)($coin['price_change_24h'] ?? 0),
            'market_cap' => (float)($coin['market_cap'] ?? 0),
            'volume_24h' => (float)($coin['volume_24h'] ?? 0),
            'volume' => (float)($coin['volume_24h'] ?? 0),  // Add volume alias for compatibility
            'last_updated' => $coin['last_updated'] ?? date('Y-m-d H:i:s'),
            'is_trending' => (bool)($coin['is_trending'] ?? false),
            'volume_spike' => (bool)($coin['volume_spike'] ?? false),
            'date_added' => $coin['date_added'] ?? date('Y-m-d H:i:s'),
            'source' => 'CoinMarketCap',  // Default source
            'data_source' => 'CoinMarketCap', // Add data_source for compatibility
            'user_balance' => (float)($userBalances[$symbol]['balance'] ?? 0)
        ];
        
        // Override with live data if available
        if (!empty($liveData)) {
            $mappedCoin = array_merge($mappedCoin, $liveData);
        }
        
        return $mappedCoin;
    }, $coinsData);
    
    // Get user ID from session or use default for testing
    $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing

    // Check if we should show all coins without filters
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == 1;

    // Get user balances
    $balances = [];
    try {
        $balances = getUserBalance($userId);
    } catch (Exception $e) {
        error_log("Balance error: " . $e->getMessage());
    }
    
    // Filter out coins with zero price, volume, or market cap
    $coins = array_filter($coins, function($coin) {
        return $coin['price'] > 0 && $coin['volume_24h'] > 0 && $coin['market_cap'] > 0;
    });
    // Apply new coin strategy: remove coins older than maxCoinAge hours
    $configFile = __DIR__ . '/../config/new_coin_strategy.json';
    if (file_exists($configFile)) {
        $strategy = json_decode(file_get_contents($configFile), true);
        if (!empty($strategy['enabled']) && isset($strategy['maxCoinAge'])) {
            $maxAge = intval($strategy['maxCoinAge']);
            $threshold = time() - $maxAge * 3600;
            $coins = array_filter($coins, function($coin) use ($threshold) {
                $added = strtotime($coin['date_added'] ?? $coin['last_updated']);
                return $added >= $threshold;
            });
        }
    }
    
    // Clean any output that might have been generated
    ob_clean();
    
    // Return JSON response with all coins
    echo json_encode([
        'success' => true,
        'data' => array_values($coins),  // Ensure numeric keys
        'show_all' => true,  // Always show all coins
        'timestamp' => time()
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
} catch (Exception $e) {
    // Clean any output that might have been generated
    ob_clean();
    
    // Handle any errors
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get coin data: ' . $e->getMessage()
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
}
