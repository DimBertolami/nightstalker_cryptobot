<?php
// Start output buffering at the very beginning
while (ob_get_level()) ob_end_clean();
ob_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-error.log');

// Set JSON content type header
header('Content-Type: application/json; charset=utf-8');

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $json = json_encode($data);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'JSON encoding error: ' . json_last_error_msg(),
            'data' => null
        ]);
    }
    echo $json;
    exit;
}

// Handle any uncaught exceptions
set_exception_handler(function($e) {
    error_log("Uncaught Exception in " . basename(__FILE__) . ": " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while fetching coin data',
        'error' => $e->getMessage()
    ], 500);
});

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}, E_ALL);

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Get data from database
    $db = getDBConnection();
    
    // Check if we should fetch from all_coingecko_coins table
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == 1;

    // If 'show all' is enabled, attempt to fetch all coins directly from CoinGecko public API
    if ($showAll) {
        $list = [];
        $useCached = false;
        $cacheDir = __DIR__ . '/../../cache';
        
        // Ensure cache directory exists and is writable
        try {
            if (!is_dir($cacheDir)) {
                if (!@mkdir($cacheDir, 0755, true)) {
                    $error = error_get_last();
                    throw new Exception("Failed to create cache directory: " . ($error['message'] ?? 'Unknown error'));
                }
                // Set proper permissions after creation
                @chmod($cacheDir, 0755);
                error_log("Created cache directory: $cacheDir");
            }
            
            // Verify directory is writable
            if (!is_writable($cacheDir)) {
                if (!@chmod($cacheDir, 0755)) {
                    throw new Exception("Failed to set permissions on cache directory");
                }
                if (!is_writable($cacheDir)) {
                    throw new Exception("Cache directory is not writable after chmod");
                }
            }
        } catch (Exception $e) {
            error_log("Cache directory error: " . $e->getMessage());
            // Fall back to system temp directory
            $cacheDir = sys_get_temp_dir() . '/night_stalker_cache';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            $cacheFile = $cacheDir . '/coingecko_coins_cache.json';
            error_log("Falling back to temp directory: $cacheDir");
        }
        
        $cacheFile = $cacheDir . '/coingecko_coins_cache.json';
        $cacheTime = 3600; // 1 hour cache
        
        // If we have a valid cache file and it's fresh enough, use it
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
            $cachedData = @file_get_contents($cacheFile);
            if ($cachedData !== false) {
                $list = json_decode($cachedData, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($list)) {
                    $useCached = true;
                    error_log("Using cached CoinGecko data");
                } else {
                    error_log("Invalid or empty cache data: " . json_last_error_msg());
                }
            }
        }

        // If we don't have cached data, try to fetch from API
        if (!$useCached) {
            $apiUrl = 'https://api.coingecko.com/api/v3/coins/list';
            $userAgent = 'NightStalkerBot/1.0 (https://yourdomain.com; support@yourdomain.com)';
            
            // Try to get from database first
            try {
                $stmt = $db->prepare("SELECT * FROM `all_coingecko_coins` LIMIT 1");
                if ($stmt && $stmt->execute()) {
                    $dbData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    if (!empty($dbData)) {
                        $list = $dbData;
                        $useCached = true;
                        error_log("Using database fallback for CoinGecko data");
                    }
                }
            } catch (Exception $e) {
                error_log("Database fallback error: " . $e->getMessage());
            }

            // Only try API if we don't have database data
            if (!$useCached && function_exists('curl_init')) {
                try {
                    $rateLimitFile = $cacheDir . '/coingecko_rate_limit.txt';
                    $rateLimitTime = file_exists($rateLimitFile) ? (int)file_get_contents($rateLimitFile) : 0;
                    $currentTime = time();
                    
                    // Respect rate limit cooldown (60 seconds)
                    if ($currentTime < $rateLimitTime + 60) {
                        error_log("Rate limit cooldown active, using database data");
                        $useCached = true;
                    } else {
                        $ch = curl_init($apiUrl);
                        
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_HTTPHEADER => [
                                'Accept: application/json',
                                'Content-Type: application/json',
                                'User-Agent: ' . $userAgent
                            ],
                            CURLOPT_ENCODING => 'gzip, deflate',
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_MAXREDIRS => 3
                        ]);
                        
                        error_log("Fetching fresh data from CoinGecko API...");
                        $json = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlError = curl_error($ch);
                        
                        // Handle rate limiting
                        if ($httpCode === 429) {
                            // Set rate limit cooldown
                            file_put_contents($rateLimitFile, $currentTime);
                            error_log("Rate limited by CoinGecko API. Will retry after cooldown.");
                            $useCached = true;
                        }
                        
                        curl_close($ch);
                    }
                    
                    if (isset($json) && $httpCode === 200 && $json !== false) {
                        $list = json_decode($json, true);
                        // Cache the successful response
                        if (!is_dir(dirname($cacheFile))) {
                            mkdir(dirname($cacheFile), 0755, true);
                        }
                        file_put_contents($cacheFile, $json);
                    } else {
                        throw new Exception(sprintf(
                            'CoinGecko API error: %s (HTTP %d)', 
                            $curlError ?: 'No error message',
                            $httpCode
                        ));
                    }
                } catch (Exception $e) {
                    error_log('CoinGecko API request failed: ' . $e->getMessage());
                }
            }
            
            // Fallback to file_get_contents if cURL failed or not available
            if ($list === null && ini_get('allow_url_fopen')) {
                try {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 15,
                            'header' => "Accept: application/json\r\n"
                        ]
                    ]);
                    
                    $apiUrl = 'https://api.coingecko.com/api/v3/coins/list';
                    $json = @file_get_contents($apiUrl, false, $context);
                    
                    if ($json !== false) {
                        $list = json_decode($json, true);
                        // Cache the successful response
                        if (!is_dir(dirname($cacheFile))) {
                            mkdir(dirname($cacheFile), 0755, true);
                        }
                        file_put_contents($cacheFile, $json);
                    } else {
                        throw new Exception('Failed to fetch data from CoinGecko API');
                    }
                } catch (Exception $e) {
                    error_log('CoinGecko fallback fetch failed: ' . $e->getMessage());
                }
            }
        }
        // If fetch succeeded, return results
        if (is_array($list)) {
            $coinsData = [];
            foreach ($list as $item) {
                $coinsData[] = [
                    'id' => $item['id'],
                    'symbol' => strtoupper($item['symbol']),
                    'name' => $item['name'],
                    'current_price' => null,
                    'price_change_24h' => null,
                    'market_cap' => null,
                    'volume_24h' => null,
                    'last_updated' => null,
                    'source' => 'CoinGecko',
                    'user_balance' => 0,
                    'is_trending' => false,
                    'volume_spike' => false
                ];
            }
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $coinsData,
                'show_all' => true,
                'timestamp' => time()
            ]);
            ob_end_flush();
            exit;
        }
    }
    
    if ($showAll) {
        // Fetch from all_coingecko_coins table with proper escaping
        $query = "SELECT 
                    `id`, 
                    `symbol`, 
                    `name`, 
                    `platforms`, 
                    `last_updated`,
                    NULL as `current_price`,
                    NULL as `price_change_24h`,
                    NULL as `market_cap`,
                    NULL as `volume_24h`,
                    'CoinGecko' as `source`,
                    0 as `user_balance`,
                    0 as `is_trending`,
                    0 as `volume_spike`
                  FROM `all_coingecko_coins` 
                  ORDER BY `name` ASC";
    } else {
        // Original query for coins table with proper escaping
        $query = "SELECT * FROM `coins` ORDER BY `market_cap` DESC";
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
    $marketData = []; // Initialize marketData as an empty array
    
    try {
        $userBalances = getUserBalance($userId);
        
        // If we need to fetch market data, we can do it here
        // For now, we'll leave it as an empty array
        // $marketData = fetchMarketData(); // Uncomment and implement this function if needed
        
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
