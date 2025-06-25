<?php
namespace NS\DataSources;

use DateTime;
use Exception;

/**
 * Messari API data source adapter
 * https://messari.io/api/docs
 */
class MessariDataSource implements CryptoDataSourceInterface {
    private $apiKey = '';
    private $baseUrl = 'https://data.messari.io/api/v1';
    private $apiVersion = 'v1';

    /**
     * Initialize with API key
     * 
     * @param string $apiKey Messari API key
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
            $formattedDate = $dateThreshold->format('Y-m-d');
            
            // First get all assets with metrics
            $assetsData = $this->makeApiCall("/assets?fields=id,symbol,name,slug,metrics/market_data");
            
            $newCoins = [];
            if (isset($assetsData['data'])) {
                foreach ($assetsData['data'] as $asset) {
                    // Check if we have the necessary data
                    if (!isset($asset['metrics']['market_data'])) {
                        continue;
                    }
                    
                    $marketData = $asset['metrics']['market_data'];
                    
                    // Filter by market cap and volume
                    $marketCap = $marketData['marketcap'] ?? 0;
                    $volume = $marketData['volume_last_24_hours'] ?? 0;
                    
                    if ($marketCap < $minMarketCap || $volume < $minVolume) {
                        continue;
                    }
                    
                    // Get asset profile to check when it was added
                    $assetProfile = $this->getCoinDetails($asset['symbol']);
                    
                    // Skip if we couldn't get profile data or if no launch date available
                    if (!$assetProfile || !isset($assetProfile['profile']['general']['launched_at'])) {
                        continue;
                    }
                    
                    $launchDate = new DateTime($assetProfile['profile']['general']['launched_at']);
                    
                    // Only include coins newer than the threshold date
                    if ($launchDate >= $dateThreshold) {
                        $newCoins[] = [
                            'id' => $asset['id'],
                            'symbol' => $asset['symbol'],
                            'name' => $asset['name'],
                            'market_cap' => $marketCap,
                            'volume_24h' => $volume,
                            'launch_date' => $launchDate->format('Y-m-d'),
                            'price_usd' => $marketData['price_usd'] ?? null,
                            'percent_change_24h' => $marketData['percent_change_usd_last_24_hours'] ?? null,
                        ];
                    }
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
            $response = $this->makeApiCall("/assets/$symbol/profile");
            return $response['data'] ?? null;
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
        $results = [];
        
        foreach ($symbols as $symbol) {
            try {
                $response = $this->makeApiCall("/assets/$symbol/metrics/market-data");
                if (isset($response['data'])) {
                    $results[$symbol] = $response['data'];
                }
            } catch (Exception $e) {
                $this->logError("Error in getMarketMetrics for $symbol: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Search for coins matching specific criteria
     * 
     * @param array $filters Associative array of filter criteria
     * @return array Matching coins
     */
    public function searchCoins(array $filters): array {
        try {
            // Build query string from filters
            $queryParams = http_build_query($filters);
            $response = $this->makeApiCall("/assets?$queryParams");
            return $response['data'] ?? [];
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
            // Map our generic metric names to Messari-specific ones
            $metricMap = [
                'volume_change_24h' => 'percent_change_volume_last_24_hours',
                'price_change_24h' => 'percent_change_usd_last_24_hours'
            ];
            
            $messariMetric = $metricMap[$metric] ?? $metric;
            
            // Get all assets with market data
            $response = $this->makeApiCall("/assets?fields=id,symbol,name,metrics/market_data");
            
            if (!isset($response['data'])) {
                return [];
            }
            
            $assets = $response['data'];
            
            // Filter assets that have the metric we're looking for
            $filteredAssets = array_filter($assets, function($asset) use ($messariMetric) {
                $marketData = $asset['metrics']['market_data'] ?? null;
                return $marketData && isset($marketData[$messariMetric]);
            });
            
            // Sort by the metric in descending order
            usort($filteredAssets, function($a, $b) use ($messariMetric) {
                $aValue = $a['metrics']['market_data'][$messariMetric] ?? 0;
                $bValue = $b['metrics']['market_data'][$messariMetric] ?? 0;
                return $bValue <=> $aValue; // Descending order
            });
            
            // Limit results and format the response
            $result = array_slice($filteredAssets, 0, $limit);
            
            return array_map(function($asset) use ($messariMetric) {
                $marketData = $asset['metrics']['market_data'];
                return [
                    'symbol' => $asset['symbol'],
                    'name' => $asset['name'],
                    'metric_value' => $marketData[$messariMetric] ?? 0,
                    'price_usd' => $marketData['price_usd'] ?? 0,
                    'market_cap' => $marketData['marketcap'] ?? 0,
                    'volume_24h' => $marketData['volume_last_24_hours'] ?? 0
                ];
            }, $result);
            
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
            $response = $this->makeApiCall('/assets/bitcoin');
            return isset($response['data']);
        } catch (Exception $e) {
            $this->logError("API connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make API call to Messari
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method
     * @param array $params Additional parameters
     * @return array Response data
     * @throws Exception on error
     */
    private function makeApiCall(string $endpoint, string $method = 'GET', array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        // Add API key header if provided
        if (!empty($this->apiKey)) {
            $headers[] = "x-messari-api-key: {$this->apiKey}";
        }
        
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
        
        if ($httpCode >= 400) {
            $errorMessage = isset($decodedResponse['status']['error_message']) 
                ? $decodedResponse['status']['error_message'] 
                : "HTTP error code: $httpCode";
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