<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Centralized logging function.
 *
 * @param string $message The message to log.
 * @param string $level The log level (e.g., 'info', 'warning', 'error').
 */
function log_message($message, $level = 'info') {
    $log_file = __DIR__ . '/../logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}


function logEvent($message, $level = 'info') {
    log_message($message, $level);
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
            $stats['total_trades'] = (int)$result->fetch(PDO::FETCH_ASSOC)['count'];
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
            $stats['active_trades'] = (int)$result->fetch(PDO::FETCH_ASSOC)['count'];
        }

        // Total profit/loss
        $result = $db->query("SELECT SUM(profit_loss) as total FROM trades WHERE profit_loss IS NOT NULL");
        if ($result) {
            $stats['total_profit'] = (float)($result->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }

        // Total volume
        $result = $db->query("SELECT SUM(total_value) as volume FROM trades");
        if ($result) {
            $stats['total_volume'] = (float)($result->fetch(PDO::FETCH_ASSOC)['volume'] ?? 0);
        }

        // Sync with TradingLogger if it exists
        syncTradesWithLogger($strategy);

        return $stats;

    } catch (Exception $e) {
        log_message("[getTradingStats] Error: " . $e->getMessage(), 'error');
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
            throw new Exception("Failed to fetch trades: " . $db->errorInfo()[2]);
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
        
        while ($trade = $result->fetch(PDO::FETCH_ASSOC)) {
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
        log_message("[syncTradesWithLogger] Error: " . $e->getMessage(), 'error');
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
        log_message("Coin {$coin['symbol']} volume: " . $coin['quote']['USD']['volume_24h']);
        
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
        log_message("CoinGecko API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (!$data || !is_array($data)) {
        log_message("Invalid response from CoinGecko", 'error');
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
        log_message("Jupiter API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['data']) || !is_array($data['data'])) {
        log_message("Invalid response from Jupiter", 'error');
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
        log_message("Bitvavo Markets API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $markets = json_decode($marketsResponse, true);
    
    if (!$markets || !is_array($markets)) {
        log_message("Invalid response from Bitvavo Markets API", 'error');
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
        log_message("Bitvavo Ticker API error: " . curl_error($ch), 'error');
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);
    $tickers = json_decode($tickerResponse, true);
    
    if (!$tickers || !is_array($tickers)) {
        log_message("Invalid response from Bitvavo Ticker API", 'error');
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
        log_message("Error fetching from CoinMarketCap: " . $e->getMessage(), 'error');
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
        log_message("Error fetching from CoinGecko: " . $e->getMessage(), 'error');
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
        log_message("Error fetching from Jupiter: " . $e->getMessage(), 'error');
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
        log_message("Error fetching from Bitvavo: " . $e->getMessage(), 'error');
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
function getUserBalance($userId = 1): array {
    try {
        $db = getDbConnection();
        
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Check if the trades table exists
        $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'trades'");
        $tableCheckStmt->execute();
        $tradesTableExists = $tableCheckStmt->rowCount() > 0;
        
        if (!$tradesTableExists) {
            // Create trades table if it doesn't exist
            $db->exec("CREATE TABLE IF NOT EXISTS trades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                coin_id INT NOT NULL,
                trade_type ENUM('buy', 'sell') NOT NULL,
                amount DECIMAL(18,8) NOT NULL,
                price DECIMAL(18,8) NOT NULL,
                total DECIMAL(18,8) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Return demo data if no trades exist yet
            return [
                'BTC' => [
                    'balance' => 0.5,
                    'name' => 'Bitcoin',
                    'symbol' => 'BTC',
                    'coin_id' => 1
                ],
                'ETH' => [
                    'balance' => 5.0,
                    'name' => 'Ethereum',
                    'symbol' => 'ETH',
                    'coin_id' => 2
                ]
            ];
        }

        // First get all trades grouped by coin_id
        $stmt = $db->prepare("SELECT 
                    t.coin_id,
                    ROUND(SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount ELSE -t.amount END), 8) AS balance
                FROM trades t
                GROUP BY t.coin_id
                HAVING balance > 0");
        
        $stmt->execute();
        $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($trades)) {
            return [];
        }
        
        // Check if cryptocurrencies table exists
        $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'cryptocurrencies'");
        $tableCheckStmt->execute();
        $cryptoTableExists = $tableCheckStmt->rowCount() > 0;
        
        $cryptoData = [];
        if ($cryptoTableExists) {
            // Get cryptocurrency data to map coin_id to symbol and name
            $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
            $cryptoStmt->execute();
            $cryptoResult = $cryptoStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cryptoResult as $row) {
                $cryptoData[$row['id']] = $row;
            }
        } else {
            // Fallback data if table doesn't exist
            $cryptoData = [
                1 => ['id' => 1, 'symbol' => 'BTC', 'name' => 'Bitcoin'],
                2 => ['id' => 2, 'symbol' => 'ETH', 'name' => 'Ethereum'],
                3 => ['id' => 3, 'symbol' => 'SOL', 'name' => 'Solana'],
                4 => ['id' => 4, 'symbol' => 'BNB', 'name' => 'Binance Coin']
            ];
        }
        
        $balances = [];
        foreach ($trades as $row) {
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
        log_message("Error getting user balance: " . $e->getMessage(), 'error');
        
        // Return demo data on error for better user experience
        return [
            'BTC' => [
                'balance' => 0.5,
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'coin_id' => 1
            ],
            'ETH' => [
                'balance' => 5.0,
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'coin_id' => 2
            ]
        ];
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
        $stmt->execute([$limit]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [];
        }
        
        // Get cryptocurrency data to map coin_id to symbol
        $cryptoData = [];
        $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
        $cryptoStmt->execute();
        $cryptoResult = $cryptoStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cryptoResult as $row) {
            $cryptoData[$row['id']] = $row;
        }
        
        $trades = [];
        foreach ($result as $row) {
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
        log_message("[getRecentTrades] " . $e->getMessage(), 'error');
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
            log_message("[getNewCryptocurrencies] Database connection failed", 'error');
            throw new Exception("Database connection failed");
        }

        $maxAge = MAX_COIN_AGE; // Store constant in variable
        log_message("[getNewCryptocurrencies] MAX_COIN_AGE: $maxAge");
        
        // Calculate age in hours using TIMESTAMPDIFF since we don't have an age_hours column
        $query = "SELECT *, 
                 TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                 FROM coins 
                 WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY date_added DESC";
                 
        log_message("[getNewCryptocurrencies] Query: $query");
        
        try {
            $stmt = $db->prepare($query);
            if (!$stmt) {
                log_message("[getNewCryptocurrencies] Prepare failed", 'error');
                throw new Exception("Prepare failed");
            }
            
            $executed = $stmt->execute([$maxAge]);
            if (!$executed) {
                log_message("[getNewCryptocurrencies] Execute failed", 'error');
                throw new Exception("Execute failed");
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            log_message("[getNewCryptocurrencies] Found " . count($data) . " coins");
            if (!empty($data)) {
                log_message("[getNewCryptocurrencies] First coin: " . json_encode($data[0]));
            }
            
            return $data;
        } catch (PDOException $e) {
            log_message("[getNewCryptocurrencies] PDO Error: " . $e->getMessage(), 'error');
            throw new Exception("Database error: " . $e->getMessage());
        }
    } catch (Exception $e) {
        log_message("[getNewCryptocurrencies] " . $e->getMessage(), 'error');
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

        if (!defined('MIN_VOLUME_THRESHOLD')) {
            define('MIN_VOLUME_THRESHOLD', 1000000);
        }
        
        $minVolume = MIN_VOLUME_THRESHOLD;
        $stmt = $db->prepare("SELECT *, 
                              TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                              FROM coins 
                              WHERE volume_24h >= ? 
                              AND is_trending = TRUE 
                              ORDER BY volume_24h DESC");
        
        $stmt->execute([$minVolume]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        log_message("[getTrendingCoins] " . $e->getMessage(), 'error');
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
            log_message("Error fetching market data: " . $e->getMessage(), 'error');
        }
        // Normalize marketData: strip suffixes for easier lookup by symbol
        $normalizedMarketData = [];
        foreach ($marketData as $key => $data) {
            if (isset($data['symbol'])) {
                $normalizedMarketData[$data['symbol']] = $data;
            }
        }
        $marketData = $normalizedMarketData;
        
        // FIFO matching for correct Sell trade P/L and invested calculation
        // Build per-coin trade lists for FIFO processing
        $tradesByCoin = [];
        foreach (array_reverse($trades) as $trade) { // process oldest to newest
            $tradesByCoin[$trade['coin_id']][] = $trade;
        }
        $fifoResults = [];
        foreach ($tradesByCoin as $coinId => $coinTrades) {
            $openBuys = [];
            foreach ($coinTrades as $trade) {
                $symbol = $cryptoData[$trade['coin_id']]['symbol'] ?? 'UNKNOWN';
                $name = $cryptoData[$trade['coin_id']]['name'] ?? 'Unknown';
                $currentPrice = $marketData[$symbol]['price'] ?? 0;
                $priceChange24h = $marketData[$symbol]['change'] ?? 0;
                $marketCap = $marketData[$symbol]['market_cap'] ?? 0;
                $volume24h = $marketData[$symbol]['volume'] ?? 0;
                $currentValue = $trade['amount'] * $currentPrice;
                if ($trade['trade_type'] === 'buy') {
                    // Track open buy
                    $openBuys[] = [
                        'id' => $trade['id'],
                        'amount' => $trade['amount'],
                        'price' => $trade['price'],
                        'remaining' => $trade['amount'],
                        'total_value' => $trade['total_value'],
                        'trade_time' => $trade['trade_time'],
                    ];
                    $fifoResults[] = [
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
                        'profit_loss' => $currentValue - $trade['total_value'],
                        'profit_loss_percent' => $trade['total_value'] > 0 ? (($currentValue - $trade['total_value']) / $trade['total_value']) * 100 : 0,
                        'invested' => $trade['total_value'],
                        'entry_price' => $trade['price'],
                        'realized_pl' => null,
                    ];
                } elseif ($trade['trade_type'] === 'sell') {
                    // FIFO match to open buys
                    $sellAmount = $trade['amount'];
                    $invested = 0;
                    $entryPrice = 0;
                    $realizedPL = 0;
                    $matchedAmount = 0;
                    $buyPrices = [];
                    foreach ($openBuys as &$buy) {
                        if ($buy['remaining'] <= 0) continue;
                        $match = min($buy['remaining'], $sellAmount);
                        if ($match <= 0) continue;
                        $invested += $match * $buy['price'];
                        $realizedPL += ($trade['price'] - $buy['price']) * $match;
                        $buyPrices[] = $buy['price'];
                        $buy['remaining'] -= $match;
                        $sellAmount -= $match;
                        $matchedAmount += $match;
                        if ($sellAmount <= 0) break;
                    }
                    unset($buy);
                    $entryPrice = count($buyPrices) > 0 ? array_sum($buyPrices) / count($buyPrices) : 0;
                    $fifoResults[] = [
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
                        'profit_loss' => $realizedPL,
                        'profit_loss_percent' => $invested > 0 ? ($realizedPL / $invested) * 100 : 0,
                        'invested' => $invested,
                        'entry_price' => $entryPrice,
                        'realized_pl' => $realizedPL,
                    ];
                }
            }
        }
        // Return most recent $limit trades
        return array_slice(array_reverse($fifoResults), 0, $limit);
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
function syncPortfolioCoinsToCryptocurrencies() {
    $db = getDBConnection();
    if (!$db) return false;

    // Get distinct coin_ids from portfolio
    $query = "SELECT DISTINCT coin_id FROM portfolio";
    $stmt = $db->prepare($query);
    if (!$stmt) {
        log_message("[syncPortfolioCoinsToCryptocurrencies] Failed to prepare portfolio query: " . $db->errorInfo()[2], 'error');
        return false;
    }
    if (!$stmt->execute()) {
        log_message("[syncPortfolioCoinsToCryptocurrencies] Failed to execute portfolio query: " . $stmt->errorInfo()[2], 'error');
        return false;
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$result) {
        log_message("[syncPortfolioCoinsToCryptocurrencies] Failed to fetch all results");
        return false;
    }

    foreach ($result as $row) {
        $coinId = $row['coin_id'];

        // Check if coinId is numeric (from coins table)
        if (is_numeric($coinId)) {
            // Get symbol and name from coins table
            $stmt = $db->prepare("SELECT symbol, coin_name FROM coins WHERE id = ?");
            if (!$stmt) {
                log_message("[syncPortfolioCoinsToCryptocurrencies] Failed to prepare coins query: " . $db->errorInfo()[2], 'error');
                continue;
            }
            $stmt->execute([$coinId]);
            $coinResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($coinResult && count($coinResult) > 0) {
                $coinRow = $coinResult[0];
                $symbol = $coinRow['symbol'];
                $name = $coinRow['coin_name'];

                // Check if symbol exists in cryptocurrencies
                $checkStmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
                if (!$checkStmt) {
                    log_message("[syncPortfolioCoinsToCryptocurrencies] Failed to prepare crypto check query: " . $db->errorInfo()[2], 'error');
                    continue;
                }
                $checkStmt->execute([$symbol]);
                $checkResult = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($checkResult && count($checkResult) == 0) {
                    // Insert into cryptocurrencies
                    $insertStmt = $db->prepare("INSERT INTO cryptocurrencies (id, symbol, name, created_at) VALUES (?, ?, ?, NOW())");
                    if ($insertStmt) {
                        $insertStmt->execute([$symbol, $symbol, $name]);
                        log_message("[syncPortfolioCoinsToCryptocurrencies] Inserted coin $symbol into cryptocurrencies");
                    }
                }
            }
        }
    }
    return true;
}

function ensurePriceUpdaterRunning() {
    $script_path = '/opt/lampp/htdocs/NS/includes/unified_price_updater.py';
    $python_executable = '/usr/bin/python3'; // Explicitly define the Python executable

    // Check if the script is already running
    $pgrep_command = "/usr/bin/pgrep -f " . escapeshellarg($script_path);
    $process_check = shell_exec($pgrep_command);

    if (empty($process_check)) {
        // Not running, so launch it
        $command = $python_executable . ' ' . escapeshellarg($script_path) . ' > /dev/null 2>&1 &';
        log_message("[ensurePriceUpdaterRunning] Launching Python script: " . $command);
        exec($command, $output, $return_var);
        if ($return_var === 0) {
            log_message("[ensurePriceUpdaterRunning] Python script '" . $script_path . "' launched successfully.");
        } else {
            log_message("[ensurePriceUpdaterRunning] Failed to launch Python script '" . $script_path . "'. Return var: " . $return_var, 'error');
        }
    } else {
        log_message("[ensurePriceUpdaterRunning] Python script '" . $script_path . "' is already running (PID: " . trim($process_check) . "). Not launching a new instance.");
    }
}

function executeBuy($coinId, $amount, $price) {
    $db = getDBConnection();
    if (!$db) return false;

    // Sync portfolio coins to cryptocurrencies before buying
    syncPortfolioCoinsToCryptocurrencies();

    // Calculate total value
    $totalValue = $amount * $price;

    // First, we need to get the correct coin_id for the cryptocurrencies table
    // If the coinId is numeric, it's from the coins table and we need to get the symbol
    $cryptoCoinId = $coinId;
    $symbol = null;

    if (is_numeric($coinId)) {
        // Get the symbol from the coins table
        $symbolStmt = $db->prepare("SELECT symbol FROM coins WHERE id = ?");
        if (!$symbolStmt) {
            log_message("[executeBuy] Failed to prepare symbol query: " . $db->errorInfo()[2], 'error');
            return false;
        }

        $symbolStmt->execute([$coinId]);
        $symbolResult = $symbolStmt->fetch(PDO::FETCH_ASSOC);

        if ($symbolResult) {
            $symbol = $symbolResult['symbol'];

            // Now get the corresponding ID from the cryptocurrencies table
            $cryptoStmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
            if (!$cryptoStmt) {
                log_message("[executeBuy] Failed to prepare crypto query: " . $db->errorInfo()[2], 'error');
                return false;
            }

            $cryptoStmt->execute([$symbol]);
            $cryptoResult = $cryptoStmt->fetch(PDO::FETCH_ASSOC);

            if ($cryptoResult) {
                $cryptoCoinId = $cryptoResult['id'];
            } else {
                // If no matching cryptocurrency found, create one
                $cryptoCoinId = "COIN_" . $symbol;
                $insertStmt = $db->prepare("INSERT IGNORE INTO cryptocurrencies 
                                           (id, symbol, name, created_at, price) 
                                           VALUES (?, ?, ?, NOW(), ?)");
                if ($insertStmt) {
                    $name = $symbol; // Use symbol as name if we don't have the actual name
                    $insertStmt->execute([$cryptoCoinId, $symbol, $name, $price]);
                }
            }
        } else {
            log_message("[executeBuy] Could not find symbol for coin ID: " . $coinId, 'error');
            return false;
        }
    }

    // Now insert the trade with the correct cryptocurrency ID
    $stmt = $db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, trade_time) VALUES (?, 'buy', ?, ?, ?, NOW())");
    $stmt->execute([$cryptoCoinId, $amount, $price, $totalValue]);

    if ($stmt->rowCount() > 0) {
        $tradeId = $db->lastInsertId();

        // Update the portfolio table
        $portfolioStmt = $db->prepare("INSERT INTO portfolio (user_id, coin_id, amount, avg_buy_price, last_updated) 
                                     VALUES (1, ?, ?, ?, NOW()) 
                                     ON DUPLICATE KEY UPDATE 
                                     amount = amount + VALUES(amount),
                                     avg_buy_price = ((amount * avg_buy_price) + (VALUES(amount) * ?)) / (amount + VALUES(amount)),
                                     last_updated = NOW()");

        if ($portfolioStmt) {
            $portfolioStmt->execute([$cryptoCoinId, $amount, $price, $price]);
            log_message("[executeBuy] Updated portfolio for coin: $cryptoCoinId, amount: $amount, price: $price");

            

            // Check if coin symbol exists in newcoins table, insert if not
            try {
                $checkNewCoinStmt = $db->prepare("SELECT 1 FROM newcoins WHERE symbol = ? LIMIT 1");
                $checkNewCoinStmt->execute([$symbol]);
                $existsNewCoin = $checkNewCoinStmt->fetchColumn();

                if (!$existsNewCoin) {
                    $insertNewCoinStmt = $db->prepare("INSERT INTO newcoins (symbol) VALUES (?)");
                    $insertNewCoinStmt->execute([$symbol]);
                    log_message("[executeBuy] Inserted new coin symbol into newcoins table: $symbol");
                }
            } catch (Exception $e) {
                log_message("[executeBuy] Error checking/inserting new coin symbol in newcoins table: " . $e->getMessage(), 'error');
            }
        } else {
            log_message("[executeBuy] Failed to update portfolio: " . $db->errorInfo()[2], 'error');
        }

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
        log_message("[executeBuy] Failed to insert trade: " . $stmt->errorInfo()[2], 'error');
        return false;
    }
}

/**
 * Get user's current balance for a specific coin from the portfolio table
 * @param string|int $coinId The coin ID to check balance for
 * @param int $userId Optional user ID (defaults to current user)
 * @return array Returns an array with amount, avg_buy_price and other portfolio data
 */
function getUserCoinBalance($coinId) {
    $db = getDBConnection();
    if (!$db) return ['amount' => 0, 'avg_buy_price' => 0];
    
    // First try exact match with COIN_ prefix
    $stmt = $db->prepare("SELECT coin_id, amount, avg_buy_price FROM portfolio WHERE coin_id = ?");
    if ($stmt) {
        $fullCoinId = 'COIN_' . strtoupper($coinId);
        $stmt->execute([$fullCoinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'amount' => (float)$result['amount'],
                'avg_buy_price' => (float)$result['avg_buy_price'],
                'coin_id' => $result['coin_id']
            ];
        }
    }
    
    // If not found, try case-insensitive search
    $stmt = $db->prepare("SELECT coin_id, amount, avg_buy_price FROM portfolio WHERE UPPER(coin_id) LIKE UPPER(CONCAT('%', ?, '%')) LIMIT 1");
    if ($stmt) {
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'amount' => (float)$result['amount'],
                'avg_buy_price' => (float)$result['avg_buy_price'],
                'coin_id' => $result['coin_id']
            ];
        }
    }
    
    return ['amount' => 0, 'avg_buy_price' => 0];
}

/**
 * Execute a sell trade: Insert a sell trade row and return profit/loss info
 * @param string|int $coinId
 * @param float $amount
 * @param float $price
 * @param int $buyTradeId Optional reference to the buy trade
 * @return array Result with success status, message, profit_loss and profit_percentage
 */
function executeSell($coinId, $amount, $price, $buyTradeId = null) {
    $db = getDBConnection();
    if (!$db) return [
        "success" => false,
        "message" => "Database connection failed",
        "profit_loss" => 0, 
        "profit_percentage" => 0
    ];
    
    // Get current portfolio balance
    $portfolioData = getUserCoinBalance($coinId);
    $userBalance = $portfolioData['amount'];
    $avgBuyPrice = $portfolioData['avg_buy_price'];
    
    // Handle both prefixed and non-prefixed coin IDs
    $cleanCoinId = strtoupper(str_replace('COIN_', '', $coinId));
    $portfolioId = $portfolioData['coin_id'] ?? $cleanCoinId;
    
    // Log for debugging
    log_message("Sell - Original ID: $coinId, Clean ID: $cleanCoinId, Portfolio ID: $portfolioId");
    
    // Special case for 'all' amount
    if ($amount === 'all') {
        $amount = $userBalance;
    }
    
    // Check if user has enough to sell
    if ($userBalance < $amount) {
        return [
            "success" => false,
            "message" => "Insufficient balance. You only have {$userBalance} coins available to sell.",
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // FIFO matching to calculate profit_loss and profit_percentage
        $stmtBuys = $db->prepare("SELECT id, amount, price FROM trades WHERE coin_id = ? AND trade_type = 'buy' ORDER BY trade_time ASC");
        $stmtBuys->execute([$cleanCoinId]);
        $buyTrades = $stmtBuys->fetchAll(PDO::FETCH_ASSOC);
        
        $remainingSellAmount = $amount;
        $totalInvested = 0;
        $totalProfitLoss = 0;
        
        foreach ($buyTrades as &$buyTrade) {
            if ($remainingSellAmount <= 0) break;
            $availableAmount = $buyTrade['amount'];
            $matchAmount = min($availableAmount, $remainingSellAmount);
            
            $invested = $matchAmount * $buyTrade['price'];
            $revenue = $matchAmount * $price;
            $profitLoss = $revenue - $invested;
            
            $totalInvested += $invested;
            $totalProfitLoss += $profitLoss;
            
            $buyTrade['amount'] -= $matchAmount;
            $remainingSellAmount -= $matchAmount;
        }
        unset($buyTrade);
        
        $profitPercentage = $totalInvested > 0 ? ($totalProfitLoss / $totalInvested) * 100 : 0;
        $totalValue = $amount * $price;
        
        // Insert sell trade with profit_loss and profit_percentage
        $stmt = $db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, profit_loss, profit_percentage, trade_time) VALUES (?, 'sell', ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$cleanCoinId, $amount, $price, $totalValue, $totalProfitLoss, $profitPercentage]);
        
        // Update portfolio
        $remainingAmount = $userBalance - $amount;
        
        if ($remainingAmount <= 0.00000001) { // Effectively zero
            $stmt = $db->prepare("DELETE FROM portfolio WHERE coin_id IN (?, ?)");
            $stmt->execute([$portfolioId, $cleanCoinId]);
        } else {
            $stmt = $db->prepare("UPDATE portfolio SET amount = ? WHERE coin_id IN (?, ?)");
            $stmt->execute([$remainingAmount, $portfolioId, $cleanCoinId]);
        }
        
        $db->commit();
        
        return [
            "success" => true,
            "message" => "Successfully sold $amount coins",
            "profit_loss" => $totalProfitLoss,
            "profit_percentage" => $profitPercentage
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return [
            "success" => false,
            "message" => "Error executing sell: " . $e->getMessage(),
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
}

/**
 * Format a currency value with the specified number of decimal places
 * 
 * @param float $value The value to format
 * @param int $decimals The number of decimal places
 * @return string The formatted value
 */
function formatCurrency($value, $decimals = 2) {
    return number_format($value, $decimals);
}

/**
 * Get coin data from either the coins or cryptocurrencies table
 * 
 * @param mixed $coinId Either a numeric ID (for coins table) or a symbol (for cryptocurrencies table)
 * @return array An array of coin data or empty array if not found
 */
function getCoinData($coinId) {
    $db = getDBConnection();
    $data = [];
    
    // Log what we're looking for
    log_message("getCoinData looking for coin: $coinId");
    
    // Suppress any errors to ensure we don't break JSON output
    try {
        // First try direct ID match in coins table if numeric
        if (is_numeric($coinId)) {
            log_message("Looking for numeric ID $coinId in coins table");
            $stmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_24h, 
                                    volume_24h as volume, market_cap, date_added, is_trending as trending 
                                    FROM coins WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $coinId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $data = $result->fetch_assoc();
                    log_message("Found coin in coins table: " . json_encode($data));
                    $stmt->close();
                    return $data;
                }
                $stmt->close();
            }
        }
        
        // Try direct match in cryptocurrencies table by ID
        log_message("Looking for ID $coinId in cryptocurrencies table");
        $cryptoIdStmt = $db->prepare("SELECT id, symbol, name, price, price_change_24h, 
                                    volume, market_cap, created_at, is_trending as trending 
                                    FROM cryptocurrencies WHERE id = ? LIMIT 1");
        if ($cryptoIdStmt) {
            $cryptoIdStmt->bind_param('s', $coinId);
            $cryptoIdStmt->execute();
            $cryptoIdResult = $cryptoIdStmt->get_result();
            
            if ($cryptoIdResult && $cryptoIdResult->num_rows > 0) {
                $data = $cryptoIdResult->fetch_assoc();
                log_message("Found coin in cryptocurrencies table by ID: " . json_encode($data));
                $cryptoIdStmt->close();
                return $data;
            }
            $cryptoIdStmt->close();
        }
        
        // Try by symbol in cryptocurrencies table
        $symbol = is_numeric($coinId) ? null : $coinId;
        
        // If we have a numeric ID but no direct match, try to get its symbol
        if (is_numeric($coinId) && $symbol === null) {
            log_message("Getting symbol for numeric ID $coinId");
            $symbolStmt = $db->prepare("SELECT symbol FROM coins WHERE id = ? LIMIT 1");
            if ($symbolStmt) {
                $symbolStmt->bind_param('i', $coinId);
                $symbolStmt->execute();
                $symbolResult = $symbolStmt->get_result();
                
                if ($symbolResult && $symbolResult->num_rows > 0) {
                    $row = $symbolResult->fetch_assoc();
                    $symbol = $row['symbol'];
                    log_message("Found symbol $symbol for ID $coinId");
                }
                $symbolStmt->close();
            }
        }
        
        // Try by symbol in cryptocurrencies table
        if ($symbol !== null) {
            log_message("Looking for symbol $symbol in cryptocurrencies table");
            $cryptoStmt = $db->prepare("SELECT id, symbol, name, price, price_change_24h, 
                                        volume, market_cap, created_at, is_trending as trending 
                                        FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
            if ($cryptoStmt) {
                $cryptoStmt->bind_param('s', $symbol);
                $cryptoStmt->execute();
                $cryptoResult = $cryptoStmt->get_result();
                
                if ($cryptoResult && $cryptoResult->num_rows > 0) {
                    $data = $cryptoResult->fetch_assoc();
                    log_message("Found coin in cryptocurrencies table by symbol: " . json_encode($data));
                    $cryptoStmt->close();
                    return $data;
                }
                $cryptoStmt->close();
            }
            
            // Try by symbol in coins table as last resort
            log_message("Looking for symbol $symbol in coins table");
            $coinSymbolStmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_24h, 
                                           volume_24h as volume, market_cap, date_added, is_trending as trending 
                                           FROM coins WHERE symbol = ? LIMIT 1");
            if ($coinSymbolStmt) {
                $coinSymbolStmt->bind_param('s', $symbol);
                $coinSymbolStmt->execute();
                $coinSymbolResult = $coinSymbolStmt->get_result();
                
                if ($coinSymbolResult && $coinSymbolResult->num_rows > 0) {
                    $data = $coinSymbolResult->fetch_assoc();
                    log_message("Found coin in coins table by symbol: " . json_encode($data));
                    $coinSymbolStmt->close();
                    return $data;
                }
                $coinSymbolStmt->close();
            }
        }
        
        // If we got here, we didn't find anything
        log_message("Could not find coin data for: $coinId", 'error');
        
    } catch (Exception $e) {
        log_message("Error in getCoinData: " . $e->getMessage(), 'error');
    }
    
    return $data;
}

/**
 * Format a number as percentage
 * 
 * @param float $value The value to format
 * @return string Formatted percentage string
 */
function formatPercentage($value) {
    return number_format((float)$value, 2, '.', ',') . '%';
}

/**
 * Format seconds into a human-readable duration
 * 
 * @param int $seconds Number of seconds
 * @return string Formatted duration string
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . ' minutes';
    } elseif ($seconds < 86400) {
        return floor($seconds / 3600) . ' hours ' . floor(($seconds % 3600) / 60) . ' minutes';
    } else {
        return floor($seconds / 86400) . ' days ' . floor(($seconds % 86400) / 3600) . ' hours';
    }
}
