<?php
require_once __DIR__ . '/config.php';

function logEvent($message) {
    $logFile = '/opt/lampp/htdocs/NS/logs/events.log';
    
    // Check if directory exists, if not try to create it
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Try to write to log file, but don't fail if we can't
    @file_put_contents($logFile, 
        date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 
        FILE_APPEND);
    
    // If in debug mode, also output to PHP error log
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log($message);
    }
}

/**
 * Get trading statistics
 * @param string $strategy The strategy to get stats for
 */
function getTradingStats(string $strategy = 'new_coin_strategy'): array {
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
            WHERE t1.trade_type = 'buy'
            AND NOT EXISTS (
                SELECT 1 FROM trades t2 
                WHERE t2.coin_id = t1.coin_id 
                AND t2.trade_type = 'sell'
                AND t2.trade_time > t1.trade_time
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

        // Total volume
        $result = $db->query("SELECT SUM(total_value) as volume FROM trades");
        if ($result) {
            $stats['total_volume'] = (float)$result->fetch_assoc()['volume'] ?? 0;
        }

        // Sync with TradingLogger if it exists
        syncTradesWithLogger($strategy);

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
 * Sync trades data with the TradingLogger system
 * This ensures that the dashboard displays the correct trade data
 */
function syncTradesWithLogger($strategy = 'main_strategy') {
    // This function synchronizes the trades table with the TradingLogger system
    // to ensure dashboard statistics are accurate
    try {
        // Check if TradingLogger class exists
        if (!class_exists('TradingLogger')) {
            return false;
        }
        
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Get all trades
        $query = "SELECT t.*, c.symbol, c.name 
                 FROM trades t 
                 LEFT JOIN cryptocurrencies c ON t.coin_id = c.id 
                 ORDER BY t.trade_time ASC";
        
        $result = $db->query($query);
        if (!$result) {
            throw new Exception("Failed to fetch trades: " . $db->error);
        }
        
        // Initialize TradingLogger
        $logger = new TradingLogger();
        
        // Reset the logger statistics for this strategy to start fresh
        $logger->resetStatistics($strategy);
        
        // Process each trade and log it
        $stats = [
            'trades_executed' => 0,
            'successful_trades' => 0,
            'failed_trades' => 0,
            'total_profit' => 0,
            'win_rate' => 0,
            'avg_profit_percentage' => 0
        ];
        
        $totalTrades = 0;
        $successfulTrades = 0;
        $totalProfit = 0;
        
        while ($trade = $result->fetch_assoc()) {
            $eventType = $trade['trade_type']; // 'buy' or 'sell'
            $symbol = $trade['symbol'] ?? 'UNKNOWN';
            
            $eventData = [
                'trade_id' => $trade['id'],
                'symbol' => $symbol,
                'amount' => (float)$trade['amount'],
                'price' => (float)$trade['price_per_coin'],
                'total' => (float)$trade['total_value'],
                'timestamp' => strtotime($trade['trade_time'])
            ];
            
            // Add profit data for sell events
            if ($eventType == 'sell' && isset($trade['profit'])) {
                $eventData['profit'] = (float)$trade['profit'];
                $eventData['profit_percentage'] = (float)$trade['profit_percentage'];
                
                // Update stats
                $totalTrades++;
                if ($trade['profit'] > 0) {
                    $successfulTrades++;
                }
                $totalProfit += (float)$trade['profit'];
            }
            
            // Log the event
            $logger->logEvent($strategy, $eventType, $eventData, $trade['trade_time']);
        }
        
        // Update statistics
        if ($totalTrades > 0) {
            $stats['trades_executed'] = $totalTrades;
            $stats['successful_trades'] = $successfulTrades;
            $stats['failed_trades'] = $totalTrades - $successfulTrades;
            $stats['total_profit'] = $totalProfit;
            $stats['win_rate'] = ($successfulTrades / $totalTrades) * 100;
            $stats['avg_profit_percentage'] = $totalProfit / $totalTrades;
            
            $logger->updateStats($strategy, $stats);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("[syncTradesWithLogger] Error: " . $e->getMessage());
        return false;
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
            'date_added' => $coin['date_added'] ?? null, // Add date_added field
            'source' => 'CoinMarketCap' // Add source field
        ];
    }
    
    return $formattedData;
}

/**
 * Fetch cryptocurrency data from CoinGecko
 * 
 * @return array Formatted cryptocurrency data
 */
function fetchFromCoinGecko() {
    $url = "https://api.coingecko.com/api/v3/coins/markets";
    $params = [
        'vs_currency' => 'usd',
        'order' => 'market_cap_desc',
        'per_page' => 100,
        'page' => 1,
        'sparkline' => 'false',
        'price_change_percentage' => '24h'
    ];
    
    // Add API key if defined
    if (defined('COINGECKO_API_KEY') && COINGECKO_API_KEY) {
        $params['x_cg_pro_api_key'] = COINGECKO_API_KEY;
    }
    
    $queryString = http_build_query($params);
    $fullUrl = $url . '?' . $queryString;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        logEvent("CoinGecko API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (!$data || !is_array($data)) {
        logEvent("Invalid response from CoinGecko", 'error');
        return [];
    }
    
    $formattedData = [];
    foreach ($data as $coin) {
        if (!isset($coin['symbol'])) continue;
        
        // Format the data
        $formattedData[strtoupper($coin['symbol'])] = [
            'name' => $coin['name'],
            'price' => $coin['current_price'],
            'change' => $coin['price_change_percentage_24h'],
            'market_cap' => $coin['market_cap'],
            'volume' => $coin['total_volume'],
            'volume_24h' => $coin['total_volume'],
            'date_added' => $coin['genesis_date'] ?? null,
            'source' => 'CoinGecko'
        ];
    }
    
    return $formattedData;
}

/**
 * Fetch cryptocurrency data from Jupiter (Solana)
 * 
 * @return array Formatted cryptocurrency data
 */
function fetchFromJupiter() {
    $url = "https://quote-api.jup.ag/v4/price";
    $params = ["ids" => "SOL,BTC,ETH,USDC,USDT"];
    
    $queryString = http_build_query($params);
    $fullUrl = $url . '?' . $queryString;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        logEvent("Jupiter API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['data']) || !is_array($data['data'])) {
        logEvent("Invalid response from Jupiter", 'error');
        return [];
    }
    
    $formattedData = [];
    foreach ($data['data'] as $tokenId => $tokenData) {
        $formattedData[strtoupper($tokenId)] = [
            'name' => $tokenId, // Jupiter doesn't provide full names
            'price' => $tokenData['price'],
            'change' => 0, // Jupiter doesn't provide change data
            'market_cap' => 0, // Jupiter doesn't provide market cap
            'volume' => 0, // Jupiter doesn't provide volume data
            'volume_24h' => 0,
            'date_added' => null,
            'source' => 'Jupiter'
        ];
    }
    
    return $formattedData;
}

/**
 * Fetch cryptocurrency data from Bitvavo
 * 
 * @return array Formatted cryptocurrency data
 */
function fetchFromBitvavo() {
    // First get markets
    $marketsUrl = "https://api.bitvavo.com/v2/markets";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $marketsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $marketsResponse = curl_exec($ch);
    
    if (curl_errno($ch)) {
        logEvent("Bitvavo Markets API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $markets = json_decode($marketsResponse, true);
    
    if (!$markets || !is_array($markets)) {
        logEvent("Invalid response from Bitvavo Markets API", 'error');
        return [];
    }
    
    // Then get ticker data
    $tickerUrl = "https://api.bitvavo.com/v2/ticker/24h";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tickerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $tickerResponse = curl_exec($ch);
    
    if (curl_errno($ch)) {
        logEvent("Bitvavo Ticker API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $tickers = json_decode($tickerResponse, true);
    
    if (!$tickers || !is_array($tickers)) {
        logEvent("Invalid response from Bitvavo Ticker API", 'error');
        return [];
    }
    
    $formattedData = [];
    foreach ($markets as $market) {
        $symbol = $market['base'];
        
        // Find matching ticker
        $ticker = null;
        foreach ($tickers as $t) {
            if ($t['market'] === ($market['symbol'] ?? '')) {
                $ticker = $t;
                break;
            }
        }
        
        if ($ticker) {
            $formattedData[strtoupper($symbol)] = [
                'name' => $market['base'],
                'price' => floatval($ticker['last'] ?? 0),
                'change' => floatval($ticker['changePercentage'] ?? 0),
                'market_cap' => 0, // Bitvavo doesn't provide market cap
                'volume' => floatval($ticker['volume'] ?? 0),
                'volume_24h' => floatval($ticker['volume'] ?? 0),
                'date_added' => null,
                'source' => 'Bitvavo'
            ];
        }
    }
    
    return $formattedData;
}

/**
 * Fetch cryptocurrency data from multiple sources and merge them
 * 
 * @return array Merged cryptocurrency data from all sources
 */
function fetchFromAllSources() {
    $allData = [];
    $debugOutput = false; // Set to false to prevent text output in JSON response
    
    // Fetch from CoinMarketCap
    try {
        $cmcData = fetchFromCMC('/cryptocurrency/listings/latest', [
            'start' => 1,
            'limit' => 100,
            'convert' => 'USD'
        ]);
        
        if ($cmcData && isset($cmcData['data'])) {
            $formattedCmcData = [];
            foreach ($cmcData['data'] as $coin) {
                // Make sure symbol exists
                if (!isset($coin['symbol']) || empty($coin['symbol'])) {
                    continue;
                }
                
                $symbol = $coin['symbol'];
                $formattedCmcData["{$symbol}_CMC"] = [
                    'name' => $coin['name'] ?? 'Unknown',
                    'symbol' => $symbol,
                    'price' => $coin['quote']['USD']['price'] ?? 0,
                    'change' => $coin['quote']['USD']['percent_change_24h'] ?? 0,
                    'market_cap' => $coin['quote']['USD']['market_cap'] ?? 0,
                    'volume' => $coin['quote']['USD']['volume_24h'] ?? 0,
                    'volume_24h' => $coin['quote']['USD']['volume_24h'] ?? 0,
                    'date_added' => $coin['date_added'] ?? null,
                    'source' => 'CoinMarketCap'
                ];
                
                if ($debugOutput) {
                    echo "Added {$symbol} from CoinMarketCap\n";
                }
            }
            $allData = array_merge($allData, $formattedCmcData);
            if ($debugOutput) {
                echo "Added " . count($formattedCmcData) . " coins from CoinMarketCap\n";
            }
        }
    } catch (Exception $e) {
        logEvent("Error fetching from CoinMarketCap: " . $e->getMessage(), 'error');
    }
    
    // Fetch from CoinGecko
    try {
        $geckoData = fetchFromCoinGecko();
        if (!empty($geckoData)) {
            // Rename keys to avoid collisions
            $formattedGeckoData = [];
            foreach ($geckoData as $symbol => $coin) {
                $formattedGeckoData["{$symbol}_Gecko"] = $coin;
                if ($debugOutput) {
                    echo "Added {$symbol} from CoinGecko\n";
                }
            }
            $allData = array_merge($allData, $formattedGeckoData);
            if ($debugOutput) {
                echo "Added " . count($formattedGeckoData) . " coins from CoinGecko\n";
            }
        }
    } catch (Exception $e) {
        logEvent("Error fetching from CoinGecko: " . $e->getMessage(), 'error');
    }
    
    // Fetch from Jupiter
    try {
        $jupiterData = fetchFromJupiter();
        if (!empty($jupiterData)) {
            // Rename keys to avoid collisions
            $formattedJupiterData = [];
            foreach ($jupiterData as $symbol => $coin) {
                $formattedJupiterData["{$symbol}_Jupiter"] = $coin;
                if ($debugOutput) {
                    echo "Added {$symbol} from Jupiter\n";
                }
            }
            $allData = array_merge($allData, $formattedJupiterData);
            if ($debugOutput) {
                echo "Added " . count($formattedJupiterData) . " coins from Jupiter\n";
            }
        }
    } catch (Exception $e) {
        logEvent("Error fetching from Jupiter: " . $e->getMessage(), 'error');
    }
    
    // Fetch from Bitvavo
    try {
        $bitvavoData = fetchFromBitvavo();
        if (!empty($bitvavoData)) {
            // Rename keys to avoid collisions
            $formattedBitvavoData = [];
            foreach ($bitvavoData as $symbol => $coin) {
                $formattedBitvavoData["{$symbol}_Bitvavo"] = $coin;
                if ($debugOutput) {
                    echo "Added {$symbol} from Bitvavo\n";
                }
            }
            $allData = array_merge($allData, $formattedBitvavoData);
            if ($debugOutput) {
                echo "Added " . count($formattedBitvavoData) . " coins from Bitvavo\n";
            }
        }
    } catch (Exception $e) {
        logEvent("Error fetching from Bitvavo: " . $e->getMessage(), 'error');
    }
    
    if ($debugOutput) {
        echo "Total coins after merging: " . count($allData) . "\n";
    }
    
    // If we have no data from any source, use the CoinMarketCap data directly
    if (empty($allData) && isset($cmcData) && isset($cmcData['data'])) {
        foreach ($cmcData['data'] as $coin) {
            if (!isset($coin['symbol']) || empty($coin['symbol'])) {
                continue;
            }
            
            $symbol = $coin['symbol'];
            $allData[$symbol] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['quote']['USD']['price'] ?? 0,
                'change' => $coin['quote']['USD']['percent_change_24h'] ?? 0,
                'market_cap' => $coin['quote']['USD']['market_cap'] ?? 0,
                'volume' => $coin['quote']['USD']['volume_24h'] ?? 0,
                'volume_24h' => $coin['quote']['USD']['volume_24h'] ?? 0,
                'date_added' => $coin['date_added'] ?? null,
                'source' => 'CoinMarketCap'
            ];
            
            if ($debugOutput) {
                echo "Added {$symbol} directly from CoinMarketCap\n";
            }
        }
        
        if ($debugOutput) {
            echo "Total coins after direct addition: " . count($allData) . "\n";
        }
    }
    
    return $allData;
}




/**
 * Get user's cryptocurrency balances
 */
function getUserBalance(int $userId = 1): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // First get all trades grouped by coin_id
        $stmt = $db->prepare("SELECT 
                    t.coin_id,
                    ROUND(SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount ELSE -t.amount END), 8) AS balance
                FROM trades t
                GROUP BY t.coin_id
                HAVING balance > 0");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [];
        }
        
        // Get cryptocurrency data to map coin_id to symbol and name
        $cryptoData = [];
        $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
        $cryptoStmt->execute();
        $cryptoResult = $cryptoStmt->get_result();
        while ($row = $cryptoResult->fetch_assoc()) {
            $cryptoData[$row['id']] = $row;
        }
        $cryptoStmt->close();
        
        $balances = [];
        while ($row = $result->fetch_assoc()) {
            $coinId = $row['coin_id'];
            $symbol = $cryptoData[$coinId]['symbol'] ?? 'UNKNOWN';
            $name = $cryptoData[$coinId]['name'] ?? 'Unknown';
            
            $balances[$symbol] = [
                'balance' => (float)$row['balance'],  // Ensure float type
                'name' => $name,
                'symbol' => $symbol,
                'coin_id' => $coinId
            ];
        }
        
        return $balances;
        
    } catch (Exception $e) {
        error_log("[getUserBalance] Error: " . $e->getMessage());
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

        // Query using the actual database structure
        $stmt = $db->prepare("SELECT 
                    t.id, 
                    t.coin_id,
                    t.amount,
                    t.price,
                    t.trade_type,
                    t.trade_time,
                    t.total_value
                FROM trades t
                ORDER BY t.trade_time DESC
                LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [];
        }
        
        // Get cryptocurrency data to map coin_id to symbol
        $cryptoData = [];
        $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
        $cryptoStmt->execute();
        $cryptoResult = $cryptoStmt->get_result();
        while ($row = $cryptoResult->fetch_assoc()) {
            $cryptoData[$row['id']] = $row;
        }
        $cryptoStmt->close();
        
        $trades = [];
        while ($row = $result->fetch_assoc()) {
            $coinId = $row['coin_id'];
            $symbol = $cryptoData[$coinId]['symbol'] ?? 'UNKNOWN';
            
            $trades[] = [
                'id' => $row['id'],
                'coin_id' => $coinId,
                'symbol' => $symbol,
                'amount' => $row['amount'],
                'price' => $row['price'],
                'trade_type' => $row['trade_type'],
                'trade_time' => $row['trade_time'],
                'total_value' => $row['total_value']
            ];
        }
        
        return $trades;
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
        
        // Query using the actual database structure
        $stmt = $db->prepare("SELECT 
                    t.id, t.coin_id, 
                    t.amount, t.price, t.trade_type,
                    t.trade_time, t.total_value, t.profit_loss
                FROM trades t
                ORDER BY t.trade_time DESC
                LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        $trades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (empty($trades)) {
            return [];
        }

        // Get cryptocurrency data to enhance trade information
        $cryptoData = [];
        $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
        $cryptoStmt->execute();
        $cryptoResult = $cryptoStmt->get_result();
        while ($row = $cryptoResult->fetch_assoc()) {
            $cryptoData[$row['id']] = $row;
        }
        $cryptoStmt->close();
        
        // Get current market data for calculating current values
        $marketData = [];
        try {
            $marketData = fetchFromAllSources();
        } catch (Exception $e) {
            error_log("Error fetching market data: " . $e->getMessage());
        }
        
        return array_map(function($trade) use ($cryptoData, $marketData) {
            // Get symbol and name from crypto data
            $symbol = $cryptoData[$trade['coin_id']]['symbol'] ?? 'UNKNOWN';
            $name = $cryptoData[$trade['coin_id']]['name'] ?? 'Unknown';
            
            // Find current price from market data
            $currentPrice = 0;
            $priceChange24h = 0;
            $marketCap = 0;
            $volume24h = 0;
            
            if (isset($marketData[$symbol])) {
                $currentPrice = $marketData[$symbol]['price'] ?? 0;
                $priceChange24h = $marketData[$symbol]['change'] ?? 0;
                $marketCap = $marketData[$symbol]['market_cap'] ?? 0;
                $volume24h = $marketData[$symbol]['volume'] ?? 0;
            }
            
            // Calculate current value and profit/loss
            $currentValue = $trade['amount'] * $currentPrice;
            $profitLoss = $currentValue - $trade['total_value'];
            $profitLossPercent = $trade['total_value'] > 0 
                ? ($profitLoss / $trade['total_value']) * 100 
                : 0;
            
            return [
                'id' => $trade['id'],
                'coin_id' => $trade['coin_id'],
                'symbol' => $symbol,
                'amount' => $trade['amount'],
                'price' => $trade['price'],
                'trade_type' => $trade['trade_type'],
                'trade_time' => $trade['trade_time'],
                'total_value' => $trade['total_value'],
                'current_price' => $currentPrice,
                'price_change_24h' => $priceChange24h,
                'coin_name' => $name,
                'market_cap' => $marketCap,
                'volume_24h' => $volume24h,
                'current_value' => $currentValue,
                'profit_loss' => $profitLoss,
                'profit_loss_percent' => $profitLossPercent
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


/**
 * Execute a buy trade: Insert a trade row and return the trade ID
 * @param string|int $coinId
 * @param float $amount
 * @param float $price
 * @return int|false Trade ID or false on failure
 */
function executeBuy($coinId, $amount, $price) {
    $db = getDBConnection();
    if (!$db) return false;
    
    // Calculate total value
    $totalValue = $amount * $price;
    
    // Insert trade record using the actual table structure
    $stmt = $db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, trade_time) 
                         VALUES (?, 'buy', ?, ?, ?, NOW())");
    $stmt->bind_param("sddd", $coinId, $amount, $price, $totalValue);
    
    if ($stmt->execute()) {
        $tradeId = $stmt->insert_id;
        $stmt->close();
        
        // Log the trade using TradingLogger
        require_once __DIR__ . '/TradingLogger.php';
        $logger = new TradingLogger();
        
        // Get additional coin data if available
        $coinData = getCoinData($coinId);
        $marketCap = $coinData['market_cap'] ?? 0;
        $volume = $coinData['volume'] ?? 0;
        
        // Prepare event data for logging
        $eventData = [
            'symbol' => $coinId,
            'amount' => $amount,
            'price' => $price,
            'cost' => $totalValue,
            'currency' => 'USD',
            'market_cap' => $marketCap,
            'volume' => $volume
        ];
        
        // Log the buy event
        $logger->logEvent('new_coin_strategy', 'buy', $eventData);
        
        return $tradeId;
    } else {
        error_log("[executeBuy] Failed to insert trade: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Execute a sell trade: Insert a sell trade row and return profit/loss info
 * @param string|int $coinId
 * @param float $amount
 * @param float $price
 * @param int $buyTradeId Optional reference to the buy trade
 * @return array Result with success status, message, profit_loss and profit_percentage
 */
/**
 * Get user's current balance for a specific coin
 * @param string|int $coinId The coin ID to check balance for
 * @return float The current balance of the specified coin
 */
function getUserCoinBalance($coinId) {
    $db = getDBConnection();
    if (!$db) return 0;
    
    // Calculate the difference between buys and sells for this coin
    $query = "SELECT 
                SUM(CASE WHEN trade_type = 'buy' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN trade_type = 'sell' THEN amount ELSE 0 END) as balance 
              FROM trades 
              WHERE coin_id = ?";
              
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $coinId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return (float)($row['balance'] ?? 0);
    }
    
    return 0;
}

function executeSell($coinId, $amount, $price, $buyTradeId = null) {
    $db = getDBConnection();
    if (!$db) return [
        "success" => false,
        "message" => "Database connection failed",
        "profit_loss" => 0, 
        "profit_percentage" => 0
    ];
    
    // Check if user has enough of this coin to sell
    $userBalance = getUserCoinBalance($coinId);
    
    if ($userBalance < $amount) {
        return [
            "success" => false,
            "message" => "Insufficient balance. You only have {$userBalance} coins available to sell.",
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
    
    // Calculate total value
    $totalValue = $amount * $price;
    
    // Calculate average buy price from previous buy trades
    $stmt = $db->prepare("SELECT AVG(price) as avg_price, SUM(amount * price) / SUM(amount) as weighted_avg 
                         FROM trades 
                         WHERE coin_id = ? AND trade_type = 'buy'");
    $stmt->bind_param("s", $coinId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $buyPrice = $row['weighted_avg'] ?? $price; // Use weighted average price if available
    $stmt->close();
    
    // Calculate profit/loss based on average buy price
    $profitLoss = ($price - $buyPrice) * $amount;
    $profitPercentage = $buyPrice > 0 ? (($price - $buyPrice) / $buyPrice) * 100 : 0;
    
    // Begin transaction
    $db->begin_transaction();
    
    try {
        // Insert the sell trade
        $stmt = $db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, trade_time) 
        VALUES (?, 'sell', ?, ?, ?, NOW())");
        $stmt->bind_param("sddd", $coinId, $amount, $price, $totalValue);
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            throw new Exception("Failed to record sell trade");
        }
        
        // Commit transaction
        $db->commit();
        
        // Log the trade using TradingLogger
        require_once __DIR__ . '/TradingLogger.php';
        $logger = new TradingLogger();
        
        // Prepare event data for logging
        $eventData = [
            'symbol' => $coinId,
            'amount' => $amount,
            'sell_price' => $price,
            'buy_price' => $buyPrice,
            'profit' => $profitLoss,
            'profit_percentage' => $profitPercentage,
            'currency' => 'USD',
            'holding_time_seconds' => 0 // You could calculate this if you have the buy time
        ];
        
        // Log the sell event
        $logger->logEvent('new_coin_strategy', 'sell', $eventData);
        
        // Also log with the simple logger for backward compatibility
        logEvent('Trade', "Sold {$amount} of coin {$coinId} at price {$price}");
        
        return [
            "success" => true,
            "message" => "Successfully sold {$amount} coins",
            "profit_loss" => $profitLoss,
            "profit_percentage" => $profitPercentage
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        
        return [
            "success" => false,
            "message" => "Error executing sell: " . $e->getMessage(),
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
}
