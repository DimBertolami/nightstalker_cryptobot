<?php
namespace NS\DataSources;

use Exception;

/**
 * Data Source Manager with automatic failover between multiple cryptocurrency data sources
 * Handles rate limiting by automatically switching between data sources when needed
 */
class CryptoDataSourceManager implements CryptoDataSourceInterface {
    // Available data sources
    private $dataSources = [];
    
    // Current active data source
    private $activeSource = null;
    
    // Last used data source index
    private $currentSourceIndex = 0;
    
    // Tracking failed attempts
    private $failedAttempts = [];
    
    // Error logs
    private $errors = [];
    
    // Data source priorities (lower number = higher priority)
    private $priorities = [
        'CoinGeckoDataSource' => 1,
        'MessariDataSource' => 2,
        'CryptoCompareDataSource' => 3,
        'JupiterDataSource' => 4
    ];
    
    /**
     * Initialize with available data sources
     * 
     * @param array $dataSources Array of data source instances
     */
    public function __construct(array $dataSources = []) {
        // Add default data sources if none provided
        if (empty($dataSources)) {
            $dataSources = [
                new CoinGeckoDataSource(),
                new MessariDataSource(),
                new JupiterDataSource()
            ];
        }
        
        // Add provided data sources
        foreach ($dataSources as $source) {
            if ($source instanceof CryptoDataSourceInterface) {
                $className = get_class($source);
                $shortName = substr($className, strrpos($className, '\\') + 1);
                $this->dataSources[$shortName] = $source;
            }
        }
        
        // Set initial active source (highest priority)
        $this->selectInitialSource();
        
        // Initialize failure tracking
        foreach (array_keys($this->dataSources) as $sourceName) {
            $this->failedAttempts[$sourceName] = 0;
        }
    }
    
    /**
     * Select initial data source based on priority
     */
    private function selectInitialSource() {
        if (empty($this->dataSources)) {
            throw new Exception("No data sources available");
        }
        
        // Sort sources by priority
        $sources = [];
        foreach ($this->dataSources as $name => $source) {
            $priority = $this->priorities[$name] ?? 999;
            $sources[$name] = $priority;
        }
        
        asort($sources);
        
        // Select highest priority source
        $sourceNames = array_keys($sources);
        $this->activeSource = reset($sourceNames);
        $this->logEvent("Initial data source selected: {$this->activeSource}");
    }
    
    /**
     * Switch to the next available data source
     * 
     * @return bool True if switch successful, false if no more sources available
     */
    private function switchDataSource() {
        // Mark current source as failed
        if ($this->activeSource) {
            $this->failedAttempts[$this->activeSource]++;
            $this->logEvent("Data source {$this->activeSource} marked as failed (attempts: {$this->failedAttempts[$this->activeSource]})");
        }
        
        // Get available sources that haven't failed too many times
        $availableSources = [];
        foreach ($this->dataSources as $name => $source) {
            if ($name !== $this->activeSource && $this->failedAttempts[$name] < 3) {
                $priority = $this->priorities[$name] ?? 999;
                $availableSources[$name] = $priority;
            }
        }
        
        // If no available sources, reset failure counts and try again
        if (empty($availableSources)) {
            $this->logEvent("No available data sources. Resetting failure counts.");
            foreach (array_keys($this->failedAttempts) as $source) {
                $this->failedAttempts[$source] = 0;
            }
            
            // Reset to the highest priority source
            $this->selectInitialSource();
            return false;
        }
        
        // Sort by priority
        asort($availableSources);
        $sourceNames = array_keys($availableSources);
        $this->activeSource = reset($sourceNames);
        
        $this->logEvent("Switched to data source: {$this->activeSource}");
        return true;
    }
    
    /**
     * Execute a method with automatic failover
     * 
     * @param string $method Method to call
     * @param array $params Parameters for the method
     * @return mixed Result of the method call
     * @throws Exception If all data sources fail
     */
    private function executeWithFailover(string $method, array $params = []) {
        $maxAttempts = count($this->dataSources) * 2;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            try {
                if (!$this->activeSource || !isset($this->dataSources[$this->activeSource])) {
                    $this->switchDataSource();
                }
                
                $source = $this->dataSources[$this->activeSource];
                
                // Skip if source doesn't implement the method
                if (!method_exists($source, $method)) {
                    $this->switchDataSource();
                    $attempts++;
                    continue;
                }
                
                $result = call_user_func_array([$source, $method], $params);
                
                // Reset failure count on success
                $this->failedAttempts[$this->activeSource] = 0;
                
                return $result;
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                $this->errors[] = "Error from {$this->activeSource}: {$errorMessage}";
                $this->logEvent("API error: {$errorMessage}");
                
                // Check for rate limiting error patterns
                if (
                    stripos($errorMessage, 'rate limit') !== false || 
                    stripos($errorMessage, '429') !== false || 
                    stripos($errorMessage, 'too many requests') !== false
                ) {
                    $this->logEvent("Rate limit detected. Switching data source.");
                    $this->switchDataSource();
                } else {
                    // For other errors, increment attempts but don't switch automatically
                    $attempts++;
                    $this->logEvent("Non-rate-limit error. Attempt {$attempts}/{$maxAttempts}");
                    
                    // For persistent errors, switch anyway after 2 attempts
                    if ($attempts % 2 === 0) {
                        $this->switchDataSource();
                    }
                }
            }
        }
        
        throw new Exception("All data sources failed: " . implode("; ", $this->errors));
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
        return $this->executeWithFailover('getNewCoins', [$days, $minMarketCap, $minVolume]);
    }
    
    /**
     * Get detailed information for a specific coin
     * 
     * @param string $symbol Coin symbol (e.g., BTC, ETH)
     * @return array|null Coin details or null if not found
     */
    public function getCoinDetails(string $symbol): ?array {
        return $this->executeWithFailover('getCoinDetails', [$symbol]);
    }
    
    /**
     * Get current market metrics for a list of coins
     * 
     * @param array $symbols List of coin symbols
     * @return array Market data keyed by symbol
     */
    public function getMarketMetrics(array $symbols): array {
        return $this->executeWithFailover('getMarketMetrics', [$symbols]);
    }
    
    /**
     * Search for coins matching specific criteria
     * 
     * @param array $filters Associative array of filter criteria
     * @return array Matching coins
     */
    public function searchCoins(array $filters): array {
        return $this->executeWithFailover('searchCoins', [$filters]);
    }
    
    /**
     * Get trending coins based on specified metrics
     * 
     * @param string $metric Metric to sort by (e.g., 'volume_change_24h', 'price_change_24h')
     * @param int $limit Maximum number of results
     * @return array List of trending coins
     */
    public function getTrendingCoins(string $metric = 'volume_change_24h', int $limit = 20): array {
        return $this->executeWithFailover('getTrendingCoins', [$metric, $limit]);
    }
    
    /**
     * Test the connection to the API
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool {
        try {
            return $this->executeWithFailover('testConnection', []);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get current active data source name
     * 
     * @return string Active data source name
     */
    public function getActiveSource(): string {
        return $this->activeSource;
    }
    
    /**
     * Get all available data sources
     * 
     * @return array Available data sources
     */
    public function getDataSources(): array {
        return array_keys($this->dataSources);
    }
    
    /**
     * Get error logs
     * 
     * @return array Error logs
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get Jupiter swap quote with source validation
     */
    public function getJupiterSwapQuote(string $inputToken, string $outputToken, float $amount): array {
        // Only use sources that implement SwapDataSourceInterface
        $swapSources = array_filter($this->dataSources, function($source) {
            return $source instanceof SwapDataSourceInterface;
        });
        
        if (empty($swapSources)) {
            throw new \Exception('No swap data sources available');
        }
        
        // Temporarily replace data sources with only swap-capable ones
        $originalSources = $this->dataSources;
        $this->dataSources = $swapSources;
        
        try {
            $result = $this->executeWithFailover('getSwapQuote', [$inputToken, $outputToken, $amount]);
            $this->dataSources = $originalSources;
            return $result;
        } catch (\Exception $e) {
            $this->dataSources = $originalSources;
            throw $e;
        }
    }
    
    /**
     * Log event to file
     * 
     * @param string $message Message to log
     */
    private function logEvent(string $message): void {
        if (function_exists('logEvent')) {
            logEvent($message, 'data_source');
        } else {
            // Fallback to file logging
            $logFile = __DIR__ . '/../logs/datasource_manager.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] [DataSourceManager] $message" . PHP_EOL;
            
            // Ensure log directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}
