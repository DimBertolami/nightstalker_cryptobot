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
    $debugOutput = true; // Set to true to see debug output
    
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

