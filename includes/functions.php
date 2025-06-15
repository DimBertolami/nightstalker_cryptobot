<?php
require_once __DIR__ . '/config.php';

function logEvent($message) {
    file_put_contents('/opt/lampp/htdocs/NS/logs/events.log', 
        date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 
        FILE_APPEND);
}

/**
 * Get trading statistics
 */
function getTradingStats(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        $stats = [
            'total_trades' => 0,
            'active_trades' => 0,
            'total_profit' => 0,
            'total_volume' => 0
        ];

        // Total trades count
        $result = $db->query("SELECT COUNT(*) as count FROM trades");
        if ($result) {
            $stats['total_trades'] = (int)$result->fetch_assoc()['count'];
        }

        // Active trades (buy orders without corresponding sell)
        $result = $db->query("
            SELECT COUNT(*) as count FROM trades t1
            WHERE t1.type = 'buy'
            AND NOT EXISTS (
                SELECT 1 FROM trades t2 
                WHERE t2.coin_id = t1.coin_id 
                AND t2.type = 'sell'
                AND t2.created_at > t1.created_at
            )
        ");
        if ($result) {
            $stats['active_trades'] = (int)$result->fetch_assoc()['count'];
        }

        // Total profit/loss
        $result = $db->query("SELECT SUM(profit_loss) as total FROM trades WHERE profit_loss IS NOT NULL");
        if ($result) {
            $stats['total_profit'] = (float)$result->fetch_assoc()['total'] ?? 0;
        }

        // Total trading volume
        $result = $db->query("SELECT SUM(amount * price) as volume FROM trades");
        if ($result) {
            $stats['total_volume'] = (float)$result->fetch_assoc()['volume'] ?? 0;
        }

        return $stats;

    } catch (Exception $e) {
        error_log("[getTradingStats] Error: " . $e->getMessage());
        return [
            'total_trades' => 0,
            'active_trades' => 0,
            'total_profit' => 0,
            'total_volume' => 0
        ];
    }
}



/**
 * Fetch data from CoinMarketCap API with enhanced debugging
 */
function fetchFromCMC(): array {
    require_once __DIR__ . '/config.php';
    
    $endpoint = 'listings/latest';
    $parameters = [
        'start' => '1',
        'limit' => '10',
        'convert' => 'USD'
    ];
    
    $url = rtrim(CMC_API_URL, '/') . '/' . ltrim($endpoint, '/') . '?' . http_build_query($parameters);
    $headers = [
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . CMC_API_KEY
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FAILONERROR => true
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("CURL Error: " . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP $httpCode");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    if (!isset($data['data'])) {
        file_put_contents('api_error.log', print_r($data, true));
        throw new Exception("Invalid API response structure");
    }
    
    $formattedData = [];
    foreach ($data['data'] as $coin) {
        // Debug the volume data
        error_log("Coin {$coin['symbol']} volume: " . $coin['quote']['USD']['volume_24h']);
        
        $formattedData[$coin['symbol']] = [
            'name' => $coin['name'],
            'price' => $coin['quote']['USD']['price'],
            'change' => $coin['quote']['USD']['percent_change_24h'],
            'market_cap' => $coin['quote']['USD']['market_cap'],
            'volume' => $coin['quote']['USD']['volume_24h'],
            'volume_24h' => $coin['quote']['USD']['volume_24h'], // Add both keys for compatibility
            'date_added' => $coin['date_added'] ?? null // Add date_added field
        ];
    }
    
    return $formattedData;
}




/**
 * Get user's cryptocurrency balances
 */
/**
 * Get user's cryptocurrency balances with proper number formatting
 */
function getUserBalance(int $userId): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Create trades table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            coin_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            price DECIMAL(18,2) NOT NULL,
            type ENUM('buy', 'sell') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $db->prepare("SELECT 
                    t.symbol,
                    t.symbol as name,
                    ROUND(SUM(CASE WHEN t.type = 'buy' THEN t.amount ELSE -t.amount END), 8) AS balance
                FROM trades t
                WHERE t.user_id = ?
                GROUP BY t.symbol
                HAVING balance > 0");
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $balances = [];
        
        while ($row = $result->fetch_assoc()) {
            $balances[$row['symbol']] = [
                'balance' => (float)$row['balance'],  // Ensure float type
                'name' => $row['name'],
                'symbol' => $row['symbol']
            ];
        }
        
        return $balances;
        
    } catch (Exception $e) {
        error_log("[getUserBalance] Error for user $userId: " . $e->getMessage());
        return [];
    }
}
/**
 * Get recent trades from database
 */
function getRecentTrades(int $limit = 100): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        $stmt = $db->prepare("SELECT 
                    t.id, 
                    t.coin_id,
                    t.symbol,
                    t.amount,
                    t.price,
                    t.type AS trade_type,
                    t.created_at,
                    (t.amount * t.price) AS total_value
                FROM trades t
                ORDER BY t.created_at DESC
                LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("[getRecentTrades] " . $e->getMessage());
        return [];
    }
}


/**
 * Get new cryptocurrencies (fixed constant binding issue)
 */
function getNewCryptocurrencies(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        $maxAge = MAX_COIN_AGE; // Store constant in variable
        $stmt = $db->prepare("SELECT * FROM cryptocurrencies 
                            WHERE age_hours <= ? 
                            ORDER BY created_at DESC");
        $stmt->bind_param("i", $maxAge);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("[getNewCryptocurrencies] " . $e->getMessage());
        return [];
    }
}

/**
 * Get trending coins (fixed constant binding issue)
 */
function getTrendingCoins(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        $minVolume = MIN_VOLUME_THRESHOLD;
        $stmt = $db->prepare("SELECT * FROM cryptocurrencies 
                            WHERE volume >= ? 
                            AND is_trending = TRUE 
                            ORDER BY volume DESC");
        $stmt->bind_param("d", $minVolume);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("[getTrendingCoins] " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent trades with market data
 */
function getRecentTradesWithMarketData(int $limit = 100): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Create trades table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            coin_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            price DECIMAL(18,2) NOT NULL,
            type ENUM('buy', 'sell') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $db->prepare("SELECT 
                    t.id, t.user_id, t.coin_id, t.symbol, 
                    t.amount, t.price, t.type AS trade_type,
                    t.created_at AS trade_time,
                    (t.amount * t.price) AS total_value
                FROM trades t
                ORDER BY t.created_at DESC
                LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        $trades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (empty($trades)) {
            return [];
        }

        $marketData = fetchFromCoinMarketCap();
        
        return array_map(function($trade) use ($marketData) {
            $symbol = $trade['symbol'];
            $currentData = $marketData[$symbol] ?? null;
            
            return [
                ...$trade,
                'current_price' => $currentData['price'] ?? 0,
                'price_change_24h' => $currentData['change'] ?? 0,
                'coin_name' => $currentData['name'] ?? 'Unknown',
                'market_cap' => $currentData['market_cap'] ?? 0,
                'volume_24h' => $currentData['volume_24h'] ?? 0,
                'current_value' => $trade['amount'] * ($currentData['price'] ?? 0),
                'profit_loss' => ($trade['amount'] * ($currentData['price'] ?? 0)) - $trade['total_value'],
                'profit_loss_percent' => $trade['total_value'] > 0 
                    ? ((($trade['amount'] * ($currentData['price'] ?? 0)) - $trade['total_value']) / $trade['total_value']) * 100 
                    : 0
            ];
        }, $trades);
    } catch (Exception $e) {
        error_log("[getRecentTradesWithMarketData] " . $e->getMessage());
        return [];
    }
}

// ... [Keep all other existing functions from previous versions] ...

/**
 * Test API Connection (for debugging)
 */
function testCoinMarketCapConnection(): array {
    $testParams = [
        'start' => '1',
        'limit' => '1',
        'convert' => 'USD'
    ];

    $curl = curl_init();
    try {
        curl_setopt_array($curl, [
            CURLOPT_URL => CMC_API_URL . '?' . http_build_query($testParams),
            CURLOPT_HTTPHEADER => [
                'Accepts: application/json',
                'X-CMC_PRO_API_KEY: ' . CMC_API_KEY
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        return [
            'status' => $httpCode,
            'response' => json_decode($response, true),
            'error' => curl_error($curl)
        ];
    } finally {
        curl_close($curl);
    }
}

function validateMarketData(array $marketData): bool {
    $requiredKeys = ['price', 'change', 'market_cap', 'volume'];
    
    foreach ($marketData as $symbol => $data) {
        // Fixed missing parenthesis
        if (!is_string($symbol)) {
            return false;
        }
        
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
            
            if (!is_numeric($data[$key])) {
                return false;
            }
        }
    }
    return true;
}

