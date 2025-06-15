<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Get fresh data from CoinMarketCap API
    $marketData = fetchFromCMC();
    
    // Get data from database
    $db = db_connect();
    
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
    
    // Merge live data with database data
    $coins = array_map(function($coin) use ($marketData) {
        $symbol = $coin['symbol'];
        $liveData = $marketData[$symbol] ?? [];
        
        return [
            'id' => $coin['id'],
            'name' => $coin['name'],
            'symbol' => $symbol,
            'price' => (float)($liveData['price'] ?? $coin['current_price'] ?? 0),
            'price_change_24h' => (float)($liveData['change'] ?? $coin['price_change_24h'] ?? 0),
            'volume' => (float)($liveData['volume'] ?? $coin['volume_24h'] ?? 0),
            'market_cap' => (float)($liveData['market_cap'] ?? $coin['market_cap'] ?? 0),
            'date_added' => $liveData['date_added'] ?? $coin['date_added'] ?? null,
            'is_trending' => (bool)($coin['is_trending'] ?? false),
            'volume_spike' => (bool)($coin['volume_spike'] ?? false),
            'data_source' => !empty($liveData) ? 'CoinMarketCap' : 'Local DB',
            'last_updated' => date('Y-m-d H:i:s')
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
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => array_values($coinsWithBalances), // Reset array keys
        'show_all' => $showAll,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
