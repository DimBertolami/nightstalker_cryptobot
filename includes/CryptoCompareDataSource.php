<?php
namespace NS\DataSources;

use DateTime;
use Exception;

/**
 * CryptoCompare API data source adapter
 * https://min-api.cryptocompare.com/documentation
 */
class CryptoCompareDataSource implements CryptoDataSourceInterface {
    private $apiKey;
    private $baseUrl = 'https://min-api.cryptocompare.com/data';
    
    /**
     * Initialize with API key
     * 
     * @param string $apiKey CryptoCompare API key
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
            // Calculate the date threshold
            $dateThreshold = new DateTime();
            $dateThreshold->modify("-$days days");
            $timestampThreshold = $dateThreshold->getTimestamp();
            
            // First get all coins' general info
            $allCoinsData = $this->makeApiCall("/all/coinlist", "GET", ['summary' => true]);
            
            if (!isset($allCoinsData['Data']) || empty($allCoinsData['Data'])) {
                return [];
            }
            
            $coinList = $allCoinsData['Data'];
            $potentialNewCoins = [];
            
            // First filter based on dates
            foreach ($coinList as $symbol => $coinData) {
                // Check if we have the necessary data
                if (!isset($coinData['Symbol']) || empty($coinData['Symbol'])) {
                    continue;
                }
                
                // Not all coins have creation date information
                // If not available, we'll check full data in next step
                if (isset($coinData['AssetLaunchDate'])) {
                    $launchDateStr = $coinData['AssetLaunchDate'];
                    if (empty($launchDateStr)) {
                        continue;
                    }
                    
                    $launchDate = new DateTime($launchDateStr);
                    $launchTimestamp = $launchDate->getTimestamp();
                    
                    // Skip if older than threshold
                    if ($launchTimestamp < $timestampThreshold) {
                        continue;
                    }
                }
                
                // Keep for further analysis
                $potentialNewCoins[] = $coinData['Symbol'];
            }
            
            // If no potential new coins, return empty array
            if (empty($potentialNewCoins)) {
                return [];
            }
            
            // Now get price and volume data for filtered list
            // CryptoCompare has limits on how many coins we can request at once
            // Split into batches of 100
            $newCoins = [];
            $batches = array_chunk($potentialNewCoins, 100);
            
            foreach ($batches as $batch) {
                $fsyms = implode(',', $batch);
                $priceData = $this->makeApiCall("/pricemultifull", "GET", [
                    'fsyms' => $fsyms,
                    'tsyms' => 'USD'
                ]);
                
                if (!isset($priceData['RAW'])) {
                    continue;
                }
                
                foreach ($priceData['RAW'] as $symbol => $data) {
                    if (!isset($data['USD'])) {
                        continue;
                    }
                    
                    $usdData = $data['USD'];
                    
                    // Filter by market cap and volume
                    $marketCap = $usdData['MKTCAP'] ?? 0;
                    $volume = $usdData['TOTALVOLUME24H'] ?? 0;
                    
                    if ($marketCap < $minMarketCap || $volume < $minVolume) {
                        continue;
                    }
                    
                    // Check if we have full coin info
                    $coinInfo = $coinList[$symbol] ?? null;
                    if (!$coinInfo) {
                        continue;
                    }
                    
                    // Format and add to new coins
                    $newCoins[] = [
                        'id' => $coinInfo['Id'] ?? '',
                        'symbol' => $symbol,
                        'name' => $coinInfo['Name'] ?? $symbol,
                        'market_cap' => $marketCap,
                        'volume_24h' => $volume,
                        'price_usd' => $usdData['PRICE'] ?? 0,
                        'percent_change_24h' => $usdData['CHANGEPCT24HOUR'] ?? 0,
                        'launch_date' => $coinInfo['AssetLaunchDate'] ?? 'Unknown'
                    ];
                }
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
     * @param string $symbol Coin symbol (e.g., BTC, ETH)
     * @return array|null Coin details or null if not found
     */
    public function getCoinDetails(string $symbol): ?array {
        try {
            // Get coin general info
            $coinData = $this->makeApiCall("/all/coinlist", "GET", [
                'fsym' => $symbol
            ]);
            
            if (!isset($coinData['Data'][$symbol])) {
                return null;
            }
            
            $coinInfo = $coinData['Data'][$symbol];
            
            // Get price and market data
            $priceData = $this->makeApiCall("/pricemultifull", "GET", [
                'fsyms' => $symbol,
                'tsyms' => 'USD'
            ]);
            
            $marketData = [];
            if (isset($priceData['RAW'][$symbol]['USD'])) {
                $marketData = $priceData['RAW'][$symbol]['USD'];
            }
            
            // Combine results
            return [
                'id' => $coinInfo['Id'] ?? '',
                'symbol' => $symbol,
                'name' => $coinInfo['FullName'] ?? $symbol,
                'description' => $coinInfo['Description'] ?? '',
                'algorithm' => $coinInfo['Algorithm'] ?? '',
                'proof_type' => $coinInfo['ProofType'] ?? '',
                'start_date' => $coinInfo['AssetLaunchDate'] ?? '',
                'website' => $coinInfo['Url'] ?? '',
                'twitter' => $coinInfo['Twitter'] ?? '',
                'github' => $coinInfo['Github'] ?? '',
                'reddit' => $coinInfo['Reddit'] ?? '',
                'image_url' => $coinInfo['ImageUrl'] ?? '',
                'current_price' => $marketData['PRICE'] ?? 0,
                'market_cap' => $marketData['MKTCAP'] ?? 0,
                'volume_24h' => $marketData['VOLUME24HOUR'] ?? 0,
                'change_24h' => $marketData['CHANGE24HOUR'] ?? 0,
                'change_pct_24h' => $marketData['CHANGEPCT24HOUR'] ?? 0,
                'supply' => $marketData['SUPPLY'] ?? 0,
                'circulating_supply' => $marketData['CIRCULATINGSUPPLY'] ?? 0
            ];
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
            $results = [];
            
            // CryptoCompare has limits, so process in batches if needed
            $batches = array_chunk($symbols, 100);
            
            foreach ($batches as $batch) {
                $fsyms = implode(',', $batch);
                $priceData = $this->makeApiCall("/pricemultifull", "GET", [
                    'fsyms' => $fsyms,
                    'tsyms' => 'USD'
                ]);
                
                if (!isset($priceData['RAW'])) {
                    continue;
                }
                
                foreach ($priceData['RAW'] as $symbol => $data) {
                    if (isset($data['USD'])) {
                        $results[$symbol] = [
                            'price' => $data['USD']['PRICE'] ?? 0,
                            'volume_24h' => $data['USD']['VOLUME24HOUR'] ?? 0,
                            'market_cap' => $data['USD']['MKTCAP'] ?? 0,
                            'change_pct_24h' => $data['USD']['CHANGEPCT24HOUR'] ?? 0,
                            'high_24h' => $data['USD']['HIGH24HOUR'] ?? 0,
                            'low_24h' => $data['USD']['LOW24HOUR'] ?? 0,
                        ];
                    }
                }
            }
            
            return $results;
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
            // Get all coins first
            $allCoinsData = $this->makeApiCall("/all/coinlist", "GET", ['summary' => true]);
            
            if (!isset($allCoinsData['Data']) || empty($allCoinsData['Data'])) {
                return [];
            }
            
            $coinList = $allCoinsData['Data'];
            $matchingCoins = [];
            
            // Apply filters
            foreach ($coinList as $symbol => $coinData) {
                $match = true;
                
                // Filter by name or symbol
                if (isset($filters['search'])) {
                    $searchTerm = strtolower($filters['search']);
                    $name = strtolower($coinData['CoinName'] ?? '');
                    $symbol = strtolower($coinData['Symbol'] ?? '');
                    
                    if (strpos($name, $searchTerm) === false && strpos($symbol, $searchTerm) === false) {
                        $match = false;
                    }
                }
                
                // Filter by algorithm
                if (isset($filters['algorithm']) && $coinData['Algorithm'] !== $filters['algorithm']) {
                    $match = false;
                }
                
                // Filter by proof type
                if (isset($filters['proof_type']) && $coinData['ProofType'] !== $filters['proof_type']) {
                    $match = false;
                }
                
                // If all filters passed, add to results
                if ($match) {
                    $matchingCoins[] = [
                        'id' => $coinData['Id'] ?? '',
                        'symbol' => $coinData['Symbol'],
                        'name' => $coinData['CoinName'] ?? $coinData['Symbol'],
                        'algorithm' => $coinData['Algorithm'] ?? '',
                        'proof_type' => $coinData['ProofType'] ?? '',
                        'image_url' => $coinData['ImageUrl'] ?? '',
                        'launch_date' => $coinData['AssetLaunchDate'] ?? ''
                    ];
                }
            }
            
            return $matchingCoins;
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
            // Map metrics
            $metricMap = [
                'volume_change_24h' => 'VOLUMEDAYTO',
                'price_change_24h' => 'CHANGEPCT24HOUR'
            ];
            
            $cryptoCompareMetric = $metricMap[$metric] ?? $metric;
            
            // Get top coins by total volume
            $topCoinsData = $this->makeApiCall("/top/totalvolfull", "GET", [
                'limit' => 100, // Get a larger set first to filter down
                'tsym' => 'USD'
            ]);
            
            if (!isset($topCoinsData['Data']) || empty($topCoinsData['Data'])) {
                return [];
            }
            
            $coins = $topCoinsData['Data'];
            
            // Sort by our metric
            usort($coins, function($a, $b) use ($cryptoCompareMetric) {
                $aValue = $a['RAW']['USD'][$cryptoCompareMetric] ?? 0;
                $bValue = $b['RAW']['USD'][$cryptoCompareMetric] ?? 0;
                return $bValue <=> $aValue; // Descending order
            });
            
            // Limit and format results
            $coins = array_slice($coins, 0, $limit);
            $result = [];
            
            foreach ($coins as $coin) {
                $raw = $coin['RAW']['USD'] ?? [];
                $display = $coin['DISPLAY']['USD'] ?? [];
                
                $result[] = [
                    'symbol' => $coin['CoinInfo']['Name'] ?? '',
                    'name' => $coin['CoinInfo']['FullName'] ?? '',
                    'price_usd' => $raw['PRICE'] ?? 0,
                    'market_cap' => $raw['MKTCAP'] ?? 0,
                    'volume_24h' => $raw['VOLUME24HOUR'] ?? 0,
                    'metric_value' => $raw[$cryptoCompareMetric] ?? 0,
                    'metric_display' => $display[$cryptoCompareMetric] ?? '',
                    'change_pct_24h' => $raw['CHANGEPCT24HOUR'] ?? 0
                ];
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
            $response = $this->makeApiCall("/price", "GET", [
                'fsym' => 'BTC',
                'tsyms' => 'USD'
            ]);
            
            return isset($response['USD']);
        } catch (Exception $e) {
            $this->logError("API connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make API call to CryptoCompare
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method
     * @param array $params Additional parameters
     * @return array Response data
     * @throws Exception on error
     */
    private function makeApiCall(string $endpoint, string $method = 'GET', array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        
        // Add API key to params if provided
        if (!empty($this->apiKey)) {
            $params['api_key'] = $this->apiKey;
        }
        
        // Append params to URL for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
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
        
        if ($httpCode >= 400 || isset($decodedResponse['Response']) && $decodedResponse['Response'] === 'Error') {
            $errorMessage = $decodedResponse['Message'] ?? "HTTP error code: $httpCode";
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
            $logMessage = "[$timestamp] $message" . PHP_EOL;
            
            // Ensure log directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}