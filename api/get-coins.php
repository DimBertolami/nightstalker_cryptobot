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
    // Get fresh data from all cryptocurrency sources
    $marketData = fetchFromAllSources();
    
    // Get data from database
    $db = getDBConnection();
    
    // Check if we have the new cryptocurrencies table
    $hasNewTable = false;
    try {
        $result = $db->query("SHOW TABLES LIKE 'cryptocurrencies'");
        $hasNewTable = $result->num_rows > 0;
    } catch (Exception $e) {
        // Table doesn't exist
    }
    
    $coinsData = [];
    
    if ($hasNewTable) {
        // Use new table structure
        $stmt = $db->prepare("SELECT * FROM cryptocurrencies ORDER BY market_cap DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Check if source column exists, if not, default to CoinMarketCap
            if (!isset($row['source'])) {
                $row['source'] = 'CoinMarketCap';
            }
            $coinsData[] = $row;
        }
    } else {
        // Fall back to old coins table
        $stmt = $db->prepare("SELECT * FROM coins ORDER BY market_cap DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $coinsData[] = $row;
        }
    }
    
    // Get user portfolio balances for each coin
    $userBalances = [];
    try {
        $db2 = getDBConnection(); // Use our standard connection function
        
        // Query to get balance for each coin (buys - sells)
        $balanceQuery = "SELECT 
                            coin_id,
                            SUM(CASE WHEN trade_type = 'buy' THEN amount ELSE 0 END) - 
                            SUM(CASE WHEN trade_type = 'sell' THEN amount ELSE 0 END) as balance 
                          FROM trades 
                          GROUP BY coin_id";
        
        $balanceResult = $db2->query($balanceQuery);
        if ($balanceResult) {
            while ($row = $balanceResult->fetch_assoc()) {
                if ($row['balance'] > 0) { // Only store positive balances
                    $userBalances[$row['coin_id']] = (float)$row['balance'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting user balances: " . $e->getMessage());
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
        
        $coinId = $coin['id'];
        
        return [
            'id' => $coinId,
            'name' => $coin['name'],
            'symbol' => $symbol,
            'price' => (float)($liveData['price'] ?? $coin['price'] ?? 0),
            'price_change_24h' => (float)($liveData['change'] ?? $coin['price_change_24h'] ?? 0),
            'volume' => (float)($liveData['volume'] ?? $coin['volume_24h'] ?? 0),
            'market_cap' => (float)($liveData['market_cap'] ?? $coin['market_cap'] ?? 0),
            'date_added' => $liveData['date_added'] ?? $coin['date_added'] ?? null,
            'is_trending' => (bool)($coin['is_trending'] ?? false),
            'volume_spike' => (bool)($coin['volume_spike'] ?? false),
            'data_source' => $liveData['source'] ?? $coin['source'] ?? 'Local DB',
            'last_updated' => date('Y-m-d H:i:s'),
            'user_balance' => $userBalances[$coinId] ?? 0 // Add user balance for this coin
        ];
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
    
    // Apply filters if not showing all coins
    if (!$showAll) {
        // Filter coins based on market cap, volume, or age
        $coins = array_filter($coins, function($coin) {
            // Filter out coins with low market cap (less than $1M)
            if (isset($coin['market_cap']) && $coin['market_cap'] < 1000000) {
                return false;
            }
            
            // Filter out coins with low volume (less than $100K)
            if (isset($coin['volume']) && $coin['volume'] < 100000) {
                return false;
            }
            
            return true;
        });
    }
    
    // Add user balance to each coin
    $coinsWithBalances = array_map(function($coin) use ($balances) {
        $symbol = $coin['symbol'];
        $coin['user_balance'] = $balances[$symbol] ?? 0;
        return $coin;
    }, $coins);
    
    // Make sure we have an array with numeric keys
    $coinsWithBalances = array_values($coinsWithBalances);
    
    // Clean any output that might have been generated
    ob_clean();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $coinsWithBalances,
        'show_all' => $showAll ?? false,
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
