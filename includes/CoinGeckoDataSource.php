<?php
namespace NS\DataSources;

use DateTime;
use Exception;

/**
 * CoinGecko API data source adapter
 * https://www.coingecko.com/api/documentation
 */
class CoinGeckoDataSource implements CryptoDataSourceInterface {
    private $apiKey;
    private $baseUrl = 'https://api.coingecko.com/api/v3';
    
    /**
     * Initialize with API key
     * 
     * @param string $apiKey CoinGecko API key
     */
    public function __construct(string $apiKey = '') {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Get new coins listed within a specified timeframe
     * 
     * @param int $days Maximum age of coins in days
     * @param float $minMarketCap Minimum market cap in USD
     * @param float $minVolume Minimum 24h volume in USD
     * @return array List of coins with their details
     */
    public function getNewCoins(int $days = 30, float $minMarketCap = 1000000, float $minVolume = 100000): array {
        try {
            // Calculate the date from days ago
            $dateThreshold = new DateTime();
            $dateThreshold->modify("-$days days");
            
            // Get all coins with market data
            $params = [
                'vs_currency' => 'usd',
                'order' => 'market_cap_desc',
                'per_page' => 250,
                'page' => 1,
                'sparkline' => false
            ];
            
            $newCoins = [];
            $totalPages = 4; // Limit to first 1000 coins (4 pages × 250)
            
            for ($page = 1; $page <= $totalPages; $page++) {
                $params['page'] = $page;
                $coinsData = $this->makeApiCall('/coins/markets', 'GET', $params);
                
                if (empty($coinsData)) {
                    break;
                }
                
                foreach ($coinsData as $coin) {
                    // Skip if market cap or volume is below threshold
                    if ($coin['market_cap'] < $minMarketCap || $coin['total_volume'] < $minVolume) {
                        continue;
                    }
                    
                    // Get detailed info to check genesis date
                    $coinDetails = $this->getCoinDetails($coin['id']);
                    
                    if (!$coinDetails || empty($coinDetails['genesis_date'])) {
                        // If no genesis date, use other methods to determine if it's new
                        // For CoinGecko, we can use the 'last_updated' field as a proxy or check when it was first tracked
                        if (isset($coinDetails['last_updated'])) {
                            $lastUpdated = new DateTime($coinDetails['last_updated']);
                            if ($lastUpdated < $dateThreshold) {
                                continue; // Older than our threshold
                            }
                        } else {
                            continue; // Skip if we can't determine age
                        }
                    } else {
                        // Check genesis date
                        $genesisDate = new DateTime($coinDetails['genesis_date']);
                        if ($genesisDate < $dateThreshold) {
                            continue; // Older than our threshold
                        }
                    }
                    
                    // Add coin to results
                    $newCoins[] = [
                        'id' => $coin['id'],
                        'symbol' => $coin['symbol'],
                        'name' => $coin['name'],
                        'market_cap' => $coin['market_cap'],
                        'volume_24h' => $coin['total_volume'],
                        'price_usd' => $coin['current_price'],
                        'percent_change_24h' => $coin['price_change_percentage_24h'],
                        'launch_date' => $coinDetails['genesis_date'] ?? date('Y-m-d')
                    ];
                }
                
                // Respect API rate limits
                sleep(1);
            }
            
            return $newCoins;
        } catch (Exception $e) {
            $this->logError("Error in getNewCoins: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed information for a specific coin
     * 
     * @param string $symbol Coin symbol or ID (e.g., BTC, ETH, bitcoin)
     * @return array|null Coin details or null if not found
     */
    public function getCoinDetails(string $symbol): ?array {
        try {
            // CoinGecko accepts IDs, not symbols directly
            // If input looks like a symbol, we need to find its ID first
            $coinId = $symbol;
            
            // If it's a short symbol (like BTC, ETH), try to find its ID
            if (strlen($symbol) <= 5) {
                $coinsList = $this->makeApiCall('/coins/list');
                
                foreach ($coinsList as $coin) {
                    if (strtolower($coin['symbol']) === strtolower($symbol)) {
                        $coinId = $coin['id'];
                        break;
                    }
                }
            }
            
            // Now get the details with the ID
            $response = $this->makeApiCall("/coins/$coinId", 'GET', [
                'localization' => 'false',
                'tickers' => 'false',
                'market_data' => 'true',
                'community_data' => 'false',
                'developer_data' => 'false',
                'sparkline' => 'false'
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logError("Error in getCoinDetails for $symbol: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get current market metrics for a list of coins
     * 
     * @param array $symbols List of coin symbols
     * @return array Market data keyed by symbol
     */
    public function getMarketMetrics(array $symbols): array {
        try {
            // Convert symbols to IDs if needed
            $ids = [];
            $symbolToId = [];
            
            $coinsList = $this->makeApiCall('/coins/list');
            
            foreach ($coinsList as $coin) {
                if (in_array(strtolower($coin['symbol']), array_map('strtolower', $symbols))) {
                    $ids[] = $coin['id'];
                    $symbolToId[strtolower($coin['symbol'])] = $coin['id'];
                }
            }
            
            if (empty($ids)) {
                return [];
            }
            
            // Get market data
            $idsString = implode(',', $ids);
            $marketData = $this->makeApiCall('/coins/markets', 'GET', [
                'vs_currency' => 'usd',
                'ids' => $idsString,
                'order' => 'market_cap_desc',
                'per_page' => 250,
                'page' => 1,
                'sparkline' => false
            ]);
            
            $result = [];
            
            foreach ($marketData as $coin) {
                $symbol = strtolower($coin['symbol']);
                $result[$symbol] = [
                    'price_usd' => $coin['current_price'],
                    'market_cap_usd' => $coin['market_cap'],
                    'volume_24h_usd' => $coin['total_volume'],
                    'percent_change_24h' => $coin['price_change_percentage_24h']
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logError("Error in getMarketMetrics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search for coins matching specific criteria
     * 
     * @param array $filters Associative array of filter criteria
     * @return array Matching coins
     */
    public function searchCoins(array $filters): array {
        try {
            // If no filters, return all coins (with basic info)
            if (empty($filters)) {
                return $this->makeApiCall('/coins/list');
            }
            
            // If we have specific filters, process them
            $params = [
                'vs_currency' => 'usd',
                'order' => 'market_cap_desc',
                'per_page' => 250,
                'page' => 1,
                'sparkline' => false
            ];
            
            // Apply filters if they match CoinGecko parameters
            if (isset($filters['min_volume'])) {
                // We'll filter post-request as CoinGecko doesn't have this as a parameter
                $minVolume = $filters['min_volume'];
            }
            
            if (isset($filters['min_market_cap'])) {
                // We'll filter post-request
                $minMarketCap = $filters['min_market_cap'];
            }
            
            if (isset($filters['ids']) && is_array($filters['ids'])) {
                $params['ids'] = implode(',', $filters['ids']);
            }
            
            $results = [];
            $page = 1;
            $maxPages = 4; // Limit to 1000 results for performance
            
            while ($page <= $maxPages) {
                $params['page'] = $page;
                $coins = $this->makeApiCall('/coins/markets', 'GET', $params);
                
                if (empty($coins)) {
                    break;
                }
                
                foreach ($coins as $coin) {
                    // Apply post-request filters
                    if (isset($minVolume) && $coin['total_volume'] < $minVolume) {
                        continue;
                    }
                    
                    if (isset($minMarketCap) && $coin['market_cap'] < $minMarketCap) {
                        continue;
                    }
                    
                    $results[] = [
                        'id' => $coin['id'],
                        'symbol' => $coin['symbol'],
                        'name' => $coin['name'],
                        'market_cap' => $coin['market_cap'],
                        'volume_24h' => $coin['total_volume'],
                        'price_usd' => $coin['current_price'],
                        'percent_change_24h' => $coin['price_change_percentage_24h']
                    ];
                }
                
                $page++;
                sleep(1); // Respect rate limits
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logError("Error in searchCoins: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get trending coins based on specified metrics
     * 
     * @param string $metric Metric to sort by (e.g., 'volume_change_24h', 'price_change_24h')
     * @param int $limit Maximum number of results
     * @return array List of trending coins
     */
    public function getTrendingCoins(string $metric = 'volume_change_24h', int $limit = 20): array {
        try {
            // Map our metric to CoinGecko's format
            $coingeckoOrder = 'market_cap_desc'; // default
            
            switch ($metric) {
                case 'volume_change_24h':
                    // CoinGecko doesn't have volume change directly, use volume instead
                    $coingeckoOrder = 'volume_desc';
                    break;
                case 'price_change_24h':
                    $coingeckoOrder = 'price_change_percentage_24h_desc';
                    break;
                case 'price_change_1h':
                    $coingeckoOrder = 'price_change_percentage_1h_desc';
                    break;
                case 'market_cap':
                    $coingeckoOrder = 'market_cap_desc';
                    break;
            }
            
            // Get trending coins based on the specified metric
            $coins = $this->makeApiCall('/coins/markets', 'GET', [
                'vs_currency' => 'usd',
                'order' => $coingeckoOrder,
                'per_page' => $limit,
                'page' => 1,
                'sparkline' => false
            ]);
            
            $result = [];
            
            foreach ($coins as $coin) {
                // For volume_change_24h metric, calculate a derived value
                if ($metric === 'volume_change_24h') {
                    $volumeToMarketCapRatio = 0;
                    if ($coin['market_cap'] > 0) {
                        $volumeToMarketCapRatio = $coin['total_volume'] / $coin['market_cap'];
                    }
                    
                    $result[] = [
                        'symbol' => $coin['symbol'],
                        'name' => $coin['name'],
                        'price_usd' => $coin['current_price'],
                        'market_cap' => $coin['market_cap'],
                        'volume_24h' => $coin['total_volume'],
                        'metric_value' => $volumeToMarketCapRatio,
                        'percent_change_24h' => $coin['price_change_percentage_24h']
                    ];
                } else {
                    $metricValue = 0;
                    
                    switch ($metric) {
                        case 'price_change_24h':
                            $metricValue = $coin['price_change_percentage_24h'];
                            break;
                        case 'price_change_1h':
                            $metricValue = $coin['price_change_percentage_1h'] ?? 0;
                            break;
                        case 'market_cap':
                            $metricValue = $coin['market_cap'];
                            break;
                    }
                    
                    $result[] = [
                        'symbol' => $coin['symbol'],
                        'name' => $coin['name'],
                        'price_usd' => $coin['current_price'],
                        'market_cap' => $coin['market_cap'],
                        'volume_24h' => $coin['total_volume'],
                        'metric_value' => $metricValue,
                        'percent_change_24h' => $coin['price_change_percentage_24h']
                    ];
                }
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logError("Error in getTrendingCoins: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Test the connection to the API
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool {
        try {
            $response = $this->makeApiCall('/ping');
            return isset($response['gecko_says']);
        } catch (Exception $e) {
            $this->logError("API connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make API call to CoinGecko
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method
     * @param array $params Additional parameters
     * @return array Response data
     * @throws Exception on error
     */
    private function makeApiCall(string $endpoint, string $method = 'GET', array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        $headers = ['Accept: application/json'];
        
        // Add API key if provided
        if (!empty($this->apiKey)) {
            $headers[] = "x-cg-pro-api-key: {$this->apiKey}";
        }
        
        // Append params to URL for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NightStalker/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = "HTTP error code: $httpCode";
            if (isset($decodedResponse['error'])) {
                $errorMessage = $decodedResponse['error'];
            }
            throw new Exception($errorMessage);
        }
        
        return $decodedResponse;
    }
    
    /**
     * Log errors to file
     * 
     * @param string $message Error message to log
     */
    private function logError(string $message): void {
        // Use the project's logging function if available
        if (function_exists('logEvent')) {
            logEvent($message, 'error');
        } else {
            // Fallback to file logging
            $logFile = __DIR__ . '/../logs/datasource_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] [CoinGeckoDataSource] $message" . PHP_EOL;
            
            // Ensure log directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}